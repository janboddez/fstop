<?php

namespace Plugins\PreviewCards\Jobs;

use App\Models\Entry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Symfony\Component\DomCrawler\Crawler;
use TorMorten\Eventy\Facades\Events as Eventy;

class GetPreviewCard implements ShouldQueue
{
    use Queueable;

    public Entry $entry;

    /**
     * Create a new job instance.
     */
    public function __construct(Entry $entry)
    {
        $this->entry = $entry->withoutRelations();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->entry->status !== 'published') {
            return;
        }

        if ($this->entry->visibility === 'private') {
            return;
        }

        if (! in_array($this->entry->type, ['note', 'like'], true)) {
            return;
        }

        $crawler = new Crawler($this->entry->content);
        $nodes = $crawler->filterXPath('//a[starts-with(@href, "http")]');
        if ($nodes->count() > 0) {
            $url = $nodes->attr('href');
        }

        if (empty($url)) {
            return;
        }

        if ($this->entry->meta->firstWhere('key', 'preview_card')) {
            return;
        }

        // Fetch the remote page.
        $hash = md5($url);
        $body = Cache::remember("preview-cards:$hash", 60 * 60, function () use ($url): string {
            $response = Http::withHeaders([
                'User-Agent' => Eventy::filter(
                    'preview-cards:user_agent',
                    'F-Stop/' . config('app.version') . '; ' . url('/'),
                    $url
                )])
                ->get($url);

            if (! $response->successful()) {
                Log::error('[Preview Cards] Failed to fetch the page at ' . $url);

                return '';
            }

            $body = $response->body();

            if (empty($body)) {
                Log::error('[Preview Cards] Missing page body');

                return '';
            }

            return $body;
        });

        if (empty($body)) {
            return;
        }

        // Parse for a title, possible thumnail.
        $crawler = new Crawler($body);
        $nodes = $crawler->filterXPath('//title');
        $name = $nodes->count() > 0
            ? $nodes->text(null)
            : null;

        $nodes = $crawler->filterXPath('//meta[@property="og:image"] | //meta[@property="twitter:image"]');
        $thumbnailUrl = $nodes->count() > 0
            ? $nodes->attr('content', null)
            : null;

        $previewCard = array_filter([
            'url' => $url,
            'title' => $name,
            // If a thumbnail was found, save it locally.
            'thumbnail' => $thumbnailUrl
                ? $this->cacheThumbnail($thumbnailUrl)
                : null,
        ]);

        $this->entry->meta()->updateOrCreate(
            ['key' => 'preview_card'],
            ['value' => $previewCard]
        );
    }

    /**
     * Attempts to download and resize an image file, then return its local URL.
     */
    protected function cacheThumbnail(string $thumbnailUrl, int $size = 150): ?string
    {
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $manager = new ImageManager(new ImagickDriver());
        } elseif (extension_loaded('gd') && function_exists('gd_info')) {
            $manager = new ImageManager(new GdDriver());
        } else {
            Log::warning('[Preview Cards] Imagick nor GD installed');

            return null;
        }

        // Download image.
        $hash = md5($thumbnailUrl);
        $blob = Cache::remember("preview-cards:$hash", 60 * 60, function () use ($thumbnailUrl): string {
            $response = Http::withHeaders([
                'User-Agent' => Eventy::filter(
                    'preview-cards:user_agent',
                    'F-Stop/' . config('app.version') . '; ' . url('/'),
                    $thumbnailUrl
                )])
                ->get($thumbnailUrl);

            if (! $response->successful()) {
                Log::warning('[Preview Cards] Something went wrong fetching the image at ' . $thumbnailUrl);

                return '';
            }

            $blob = $response->body();

            if (empty($blob)) {
                Log::warning('[Preview Cards] Missing image data');

                return '';
            }

            return $blob;
        });

        if (empty($blob)) {
            return null;
        }

        // Load image.
        $image = $manager->read($blob);
        $image->cover($size, $size);

        // Generate filename.
        $hash = md5($thumbnailUrl);
        $relativeThumbnailPath = 'preview-cards/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        $fullThumbnailPath = Storage::disk('public')->path($relativeThumbnailPath);

        if (! Storage::disk('public')->has($dir = dirname($relativeThumbnailPath))) {
            // Recursively create directory if it doesn't exist, yet.
            Storage::disk('public')->makeDirectory($dir);
        }

        // Save image.
        $image->save($fullThumbnailPath);

        unset($image);

        if (! file_exists($fullThumbnailPath)) {
            Log::warning('[Preview Cards] Something went wrong saving the thumbnail');

            return null;
        }

        // Try and apply a meaningful file extension.
        $finfo = new \finfo(FILEINFO_EXTENSION);
        $extension = explode('/', $finfo->file($fullThumbnailPath))[0];
        if (! empty($extension) && $extension !== '???') {
            // Rename file.
            Storage::disk('public')->move(
                $relativeThumbnailPath,
                $relativeThumbnailPath . ".$extension"
            );
        }

        /** @todo Check the move was successful and only then return the new URL? */

        // Return the (absolute) local avatar URL.
        return Storage::disk('public')->url($relativeThumbnailPath . ".$extension");
    }
}
