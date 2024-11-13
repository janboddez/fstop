<?php

namespace Plugins\SyndicateToMastodon\Jobs;

use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyndicateToMastodon implements ShouldQueue
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

        $host = config('mastodon.host');
        if (empty($host)) {
            Log::debug('[SyndicateToMastodon] Missing host');
            return;
        }

        $host = rtrim($host, '/');

        $token = config('mastodon.token');
        if (empty($token)) {
            Log::debug('[SyndicateToMastodon] Missing token');
            return;
        }

        if (empty($this->entry->meta['syndicate_to_mastodon'])) {
            return;
        }

        /** @todo Somehow force reload this data, as it may be stale. */
        if (! empty($this->entry->meta['syndication'])) {
            foreach ($this->entry->meta['syndication'] as $url) {
                if (strpos($url, $host) !== false) {
                    Log::debug('[SyndicateToMastodon] Entry got syndicated before');
                    return;
                }
            }
        }

        $url = $this->entry->shortlink ?? $this->entry->permalink;

        if ($this->entry->type === 'note') {
            $content = $this->entry->content ?? '';
            $content = html_entity_decode(strip_tags($content));
            $content .= "\n\n{$url}";
        } else {
            $content = "{$this->entry->name} {$url}";
        }

        if (empty($content)) {
            Log::debug('[SyndicateToMastodon] Empty status');
            return;
        }

        // Send the thing.
        $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer $token",
            ])
            ->asForm()
            ->post("$host/api/v1/statuses", [
                'status' => $content,
            ])
            ->json();

        if (! empty($response['error'])) {
            Log::debug("[SyndicateToMastodon] Something went wrong: {$response['error']}");
            $this->entry->forceFill([
                'meta->syndicate_to_mastodon_error' => (array) $response['error'],
            ]);
        }

        if (! empty($response['url']) && filter_var($response['url'], FILTER_VALIDATE_URL)) {
            $syndication = array_merge(
                $this->entry->meta['syndication'] ?? [],
                [filter_var($response['url'], FILTER_SANITIZE_URL)]
            );
        }

        $this->entry->forceFill([
            'meta->syndicate_to_mastodon_error' => [], /** @todo Properly delete this key. */
            'meta->syndication' => $syndication,
        ]);

        $this->entry->saveQuietly();
    }
}
