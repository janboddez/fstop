<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Colors\Rgb\Channels\Blue;
use Intervention\Image\Colors\Rgb\Channels\Green;
use Intervention\Image\Colors\Rgb\Channels\Red;
use Intervention\Image\Colors\Rgb\Color as RgbColor;
use Intervention\Image\Colors\Rgb\Colorspace as RgbColorspace;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use kornrunner\Blurhash\Blurhash;

class AttachmentController extends Controller
{
    public function index(Request $request): View
    {
        $attachments = Attachment::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->with('entry')
            ->paginate();

        return view('admin.attachments.index', compact('attachments'));
    }

    public function store(Request $request): View
    {
        $validated = $request->validate([
            'file' => 'required|mimes:gif,jpg,pdf,png|max:5120',
        ]);

        $filename = $validated['file']->getClientOriginalName();
        $filename = Str::limit(
            Str::replaceEnd('.' . $validated['file']->getClientOriginalExtension(), '', $filename),
            250,
            ''
        );

        if ($extension = $validated['file']->extension()) { // Extension based on the file's MIME type.
            $filename .= (! empty($extension) ? ".$extension" : '');
        }

        $path = $validated['file']->storeAs(
            gmdate('Y/m'), // Add year and month, WordPress-style.
            $filename,
            'public'
        );

        $attachment = Attachment::updateOrCreate(
            [
                'path' => $path,
                'user_id' => $request->user()->id,
            ],
            $validated
        );

        /** @todo Move to model? */
        static::createThumbnails($attachment);
        static::storeBlurhash($attachment);

        return view('admin.attachments.edit', compact('attachment'));
    }

    public function edit(Attachment $attachment)
    {
        $attachment->load('entry');

        return view('admin.attachments.edit', compact('attachment'));
    }

    public function update(Request $request, Attachment $attachment)
    {
        $validated = $request->validate([
            'name' => 'nullable|max:250',
            'alt' => 'nullable|max:250',
            'caption' => 'nullable|max:250',
        ]);

        $attachment->update($validated);

        return back()
            ->withSuccess(__('Changes saved!'));
        ;
    }

    public function destroy(Attachment $attachment)
    {
        $files = (array) $attachment->path;

        // Remove auto-generated thumbnails, too.
        if ($thumbnails = $attachment->meta->firstWhere('key', 'sizes')) {
            $files = array_merge($files, $thumbnails->value);
        }

        Storage::disk('public')->delete($files);
        $attachment->meta()->delete();
        $attachment->delete();

        /** @todo If we ever add a "Delete" button to the "Edit Attachment" screen, make this smarter. */
        return back()
            ->withSucces(__('Deleted!'));
    }

    public static function createThumbnails(Attachment $attachment): void
    {
        // Intervention Image doesn't do PDFs, and GD doesn't support them, either.
        if (Str::endsWith($attachment->mime_type, 'pdf') && extension_loaded('imagick') && class_exists('Imagick')) {
            static::createPdfThumbnail($attachment);

            return;
        }

        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $manager = new ImageManager(new ImagickDriver());
        } elseif (extension_loaded('gd') && function_exists('gd_info')) {
            $manager = new ImageManager(new GdDriver());
        } else {
            Log::error('Imagick nor GD installed');

            return;
        }

        $fullPath = Storage::disk('public')->path($attachment->path);

        // Load original image.
        $image = $manager->read($fullPath);

        $width = $image->width();
        $height = $image->height();

        // Generate thumbnails.
        $sizes = [];

        foreach (Attachment::SIZES as $size => $newWidth) {
            if ($newWidth >= $width) {
                // Avoid generating larger versions.
                continue;
            }

            $copy = clone $image;

            if ($size === 'thumbnail') {
                // Always crop thumbnails.
                $newHeight = $newWidth;
                $copy->cover($newWidth, $newHeight);
            } else {
                $newHeight = (int) $height * $newWidth / $width;
                $copy->resize($newWidth, $newHeight); // Could use `::scale()` but we anyway need `$newHeight`.
            }

            $fullThumbnailPath = sprintf(
                '%s-%dx%d.%s',
                Str::replaceEnd('.' . pathinfo($fullPath, PATHINFO_EXTENSION), '', $fullPath),
                $newWidth,
                $newHeight,
                pathinfo($fullPath, PATHINFO_EXTENSION)
            );

            $copy->save($fullThumbnailPath);

            // Free up memory.
            $copy = null;
            unset($copy);

            $sizes[$size] = static::getRelativePath($fullThumbnailPath);
        }

        // Free up memory.
        $image = null;
        unset($image);

        // Store meta.
        add_meta(
            array_combine(['width', 'height', 'sizes'], [$width, $height, $sizes]),
            $attachment
        );

        // Force reload meta.
        $attachment->load('meta');
    }

    public static function createPdfThumbnail(Attachment $attachment): void
    {
        if (! extension_loaded('imagick') || ! class_exists('Imagick')) {
            Log::error('Imagick not installed');

            return;
        }

        try {
            $imagick = new \Imagick();

            $fullPath = Storage::disk('public')->path($attachment->path);
            $imagick->readimage($fullPath . '[0]'); // Read the first page.

            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            $imagick->setImageFormat('png');

            $sizes = [];

            foreach (Attachment::SIZES as $size => $newWidth) {
                if ($newWidth >= $width) {
                    // Avoid generating larger versions.
                    continue;
                }

                $copy = clone $imagick;

                if ($size === 'thumbnail') {
                    // Always crop thumbnails.
                    $newHeight = $newWidth;
                    $copy->cropThumbnailImage($newWidth, $newHeight);
                } else {
                    $newHeight = (int) $height * $newWidth / $width;
                    $copy->resizeImage($newWidth, $newHeight, \Imagick::FILTER_CATROM, 1);
                }

                $copy->setImagePage(0, 0, 0, 0);

                $fullThumbnailPath = sprintf(
                    '%s-%dx%d.%s',
                    preg_replace('~.' . pathinfo($fullPath, PATHINFO_EXTENSION) . '$~', '', $fullPath),
                    $newWidth,
                    $newHeight,
                    'png'
                );

                $copy->writeImage($fullThumbnailPath);
                $copy->destroy();

                $relativeThumbnailPath = preg_replace(
                    '~^' . Storage::disk('public')->path('') . '~',
                    '',
                    $fullThumbnailPath
                );

                $sizes[$size] = $relativeThumbnailPath;
            }

            $imagick->destroy();

            // Store meta.
            add_meta(
                array_combine(['width', 'height', 'sizes'], [$width, $height, $sizes]),
                $attachment
            );

            // Force reload meta.
            $attachment->load('meta');
        } catch (\Exception $e) {
            Log::warning('Could not generate thumbnails: ' . $e->getMessage());
        }
    }

    public static function storeBlurhash(Attachment $attachment): void
    {
        if (Str::endsWith($attachment->path, '.pdf')) {
            return;
        }

        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $manager = new ImageManager(new ImagickDriver());
        } elseif (extension_loaded('gd') && function_exists('gd_info')) {
            $manager = new ImageManager(new GdDriver());
        } else {
            Log::error('Imagick nor GD installed');

            return;
        }

        try {
            $thumbnail = $manager->read($attachment->thumbnail);

            $width = $thumbnail->width();
            $height = $thumbnail->height();

            if ($width > 200 || $height > 200) {
                return; // Prevent memory issues.
            }

            $pixels = [];

            for ($y = 0; $y < $height; ++$y) {
                $row = [];

                for ($x = 0; $x < $width; ++$x) {
                    $colors = $thumbnail->pickColor($x, $y);

                    if (! ($colors instanceof RgbColor)) {
                        $colors = $colors->convertTo(new RgbColorspace());
                    }

                    $row[] = [
                        $colors->channel(Red::class)->value(),
                        $colors->channel(Green::class)->value(),
                        $colors->channel(Blue::class)->value(),
                    ];
                }

                $pixels[] = $row;
            }

            // Free up memory.
            $thumbnail = null;
            unset($thumbnail);

            $componentsX = 4;
            $componentsY = 3;

            if ($height > $width) {
                $componentsX = 3;
                $componentsY = 4;
            }

            $attachment->meta()->updateOrCreate(
                ['key' => 'blurhash'],
                ['value' => (array) Blurhash::encode($pixels, $componentsX, $componentsY)]
            );
        } catch (\Exception $e) {
            Log::warning('Could not generate blurhash: ' . $e->getMessage());
        }
    }

    protected static function getRelativePath(string $absolutePath, string $disk = 'public'): string
    {
        return Str::replaceStart(Storage::disk($disk)->path(''), '', $absolutePath);
    }
}
