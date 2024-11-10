<?php

namespace Plugins\PreviewCards\Jobs;

use App\Models\Entry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        $urls = $crawler->filterXPath('//a[@href]')?->extract(['href']);

        if (empty($urls)) {
            return;
        }

        $meta = $this->entry->meta;
        if (! empty($meta['preview_card'])) {
            return;
        }

        // Fetch the remote page.
        $response = Http::withHeaders([
                'User-Agent' => Eventy::filter(
                    'preview-cards.user-agent',
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0',
                    $this->entry
                ),
            ])
            ->get($urls[0]);

        if (! $response->successful()) {
            Log::error('[Preview Cards] Failed to fetch the page at ' . $urls[0]);
            return;
        }

        $body = $response->body();

        if (empty($body)) {
            Log::error('[Preview Cards] Missing page body');
            return;
        }

        // Parse for a title, possible thumnail.
        $crawler = new Crawler($body);
        $name = $crawler->filterXPath('//title')?->text(null);

        $thumbnailUrl = $crawler->filterXPath('//meta[@property="og:image"]')?->attr('content', null)
            ?? $crawler->filterXPath('//meta[@property="twitter:image"]')?->attr('content', null);

        // If a thumbnail was found, save it locally.
        $localThumbnailUrl = $thumbnailUrl
            ? $this->cacheThumbnail($thumbnailUrl)
            : null;

        /** @todo Not overwrite the image if it exists and is sufficiently recent. */

        $meta['preview_card'] = array_filter([
            'url' => $urls[0],
            'title' => $name,
            'thumbnail' => $localThumbnailUrl,
        ]);

        $this->entry->meta = $meta;
        $this->entry->saveQuietly();
    }

    /**
     * Attempts to download and resize an image file, then return its local URL.
     */
    protected function cacheThumbnail(string $thumbnailUrl, int $size = 150): ?string
    {
        // Download image.
        $response = Http::withHeaders([
                'User-Agent' => Eventy::filter(
                    'preview-cards.user-agent',
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0',
                    $this->entry
                ),
            ])
            ->get($thumbnailUrl);

        if (! $response->successful()) {
            Log::error('[Preview Cards] Something went wrong fetching the image at ' . $thumbnailUrl);
            return null;
        }

        $blob = $response->body();

        if (empty($blob)) {
            Log::error('[Preview Cards] Missing image data');
            return null;
        }

        try {
            // Resize and crop.
            $imagick = new \Imagick();
            $imagick->readImageBlob($blob);
            $imagick->cropThumbnailImage($size, $size);
            $imagick->setImagePage(0, 0, 0, 0);

            // Generate filename.
            $hash = md5($thumbnailUrl);
            $relativeThumbnailPath = 'preview-cards/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;

            // Save image.
            Storage::disk('public')->put(
                $relativeThumbnailPath,
                $imagick->getImageBlob()
            );

            $imagick->destroy();

            $fullThumbnailPath = Storage::disk('public')->path($relativeThumbnailPath);
            if (! file_exists($fullThumbnailPath)) {
                Log::error('[Preview Cards] Something went wrong saving the thumbnail');
                return null;
            }

            // Try and grab a meaningful file extension.
            $finfo = new \finfo(FILEINFO_EXTENSION);
            $extension = explode('/', $finfo->file($fullThumbnailPath))[0];
            if (! empty($extension) && $extension != '???') {
                Storage::disk('public')->move(
                    $relativeThumbnailPath,
                    $relativeThumbnailPath . ".$extension"
                );
            }

            // Return the (absolute) local thumbnail URL.
            return Storage::disk('public')->url($relativeThumbnailPath . ".$extension");
        } catch (\Exception $exception) {
            Log::error('[Preview Cards] Something went wrong: ' . $exception->getMessage());
        }

        return null;
    }
}
