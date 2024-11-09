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
        /** @todo Make smarter. */
        if ($this->entry->created_at->lt(Carbon::now()->subHours(2))) {
            // Prevent syndicating "old" posts.
            Log::debug("[SyndicateToMastodon] Skipping entry {$this->entry->id}: too old");
            return;
        }

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

        $meta = $this->entry->meta;

        if (empty($meta['syndicate_to_mastodon'])) {
            return;
        }

        if (! empty($meta['syndication'])) {
            foreach ($meta['syndication'] as $url) {
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
            $meta['syndicate_to_mastodon_error'] = [$response['error']];
        }

        if (! empty($response['url']) && filter_var($response['url'], FILTER_VALIDATE_URL)) {
            // Delete previous errors, if any.
            unset($meta['syndicate_to_mastodon_error']);

            $meta['syndication'] = array_merge(
                $meta['syndication'] ?? [],
                [filter_var($response['url'], FILTER_SANITIZE_URL)]
            );
        }

        $this->entry->meta = $meta;
        $this->entry->saveQuietly();
    }
}
