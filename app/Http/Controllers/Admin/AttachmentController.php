<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class AttachmentController extends Controller
{
    public function index(Request $request)
    {
        $attachments = Attachment::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->with('entry')
            ->paginate();

        return view('admin.attachments.index', compact('attachments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file',
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

        static::createThumbnails($attachment);

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
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $manager = new ImageManager(new ImagickDriver());
        } elseif (extension_loaded('gd') && function_exists('gd_info')) {
            $manager = new ImageManager(new GdDriver());
        } else {
            Log::error('Imagick nor GD installed');

            return;
        }

        $fullPath = Storage::disk('public')->path($attachment->path);

        // Load image.
        $image = $manager->read($fullPath);

        $width = $image->width();
        $height = $image->height();

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
            unset($copy);

            $sizes[$size] = static::getRelativePath($fullThumbnailPath);
        }

        // Free up memory.
        unset($image);

        $meta = prepare_meta(array_combine(['width', 'height', 'sizes'], [$width, $height, $sizes]), $attachment);

        foreach ($meta as $key => $value) {
            $attachment->meta()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    protected static function getRelativePath(string $absolutePath, string $disk = 'public'): string
    {
        return Str::replaceStart(Storage::disk($disk)->path(''), '', $absolutePath);
    }
}
