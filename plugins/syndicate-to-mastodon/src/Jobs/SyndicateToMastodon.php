<?php

namespace Plugins\SyndicateToMastodon\Jobs;

use App\Models\Entry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyndicateToMastodon implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * If we set this to, e.g., `3`, and the job failed because of some bug that we later address, there's a good chance
     * our Mastodon followers will then see the same status posted thrice. So we keep this at `1`.
     */
    public int $tries = 1;

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

        /** @todo Store username also, and eventually create a settings page. */
        $host = config('mastodon.host');
        if (empty($host)) {
            Log::warning('[SyndicateToMastodon] Missing host');
            return;
        }

        $host = rtrim($host, '/');

        $token = config('mastodon.token');
        if (empty($token)) {
            Log::warning('[SyndicateToMastodon] Missing token');
            return;
        }

        $micropub = false;

        if (
            ($syndicationTargets = $this->entry->meta->firstWhere('key', 'mp-syndicate-to')) &&
            in_array(config('mastodon.host'), $syndicationTargets->value, true) /** @todo Pick a better "uid." */
        ) {
            Log::debug('[SyndicateToMastodon] Micropub syndication triggered.');
            // Rather than reload `$entry->meta` after setting the `syndicate_to_mastodon` field, we use this variable.
            $micropub = true;

            $this->entry->meta()->updateOrCreate(
                ['key' => 'syndicate_to_mastodon'],
                ['value' => [1]]
            );
        }

        if (
            ! $micropub &&
            (
                ! ($syndicate = $this->entry->meta->firstWhere('key', 'syndicate_to_mastodon')) ||
                empty($syndicate->value)
            )
        ) {
            // Syndication not enabled.
            return;
        }

        /** @todo Somehow force reload this data, as it may be stale. */
        if ($syndicationUrls = $this->entry->meta->firstWhere('key', 'syndication') ?: []) {
            foreach ($syndicationUrls->value as $url) {
                if (strpos($url, $host) !== false) {
                    Log::debug("[SyndicateToMastodon] Entry got syndicated to $url before");
                    return;
                }
            }
        }

        $permalink = ($shortUrl = $this->entry->meta->firstWhere('key', 'short_url'))
            ? $shortUrl->value[0]
            : $this->entry->permalink;

        if ($this->entry->type === 'note') {
            $content = $this->entry->content ?? '';
            $content = html_entity_decode(strip_tags($content)); /** @todo (Partial) inverse Markdown conversion. */
            $content .= "\n\n$permalink";
        } else {
            $content = "{$this->entry->name} $permalink";
        }

        if (empty($content)) {
            Log::debug('[SyndicateToMastodon] Empty status');
            return;
        }

        if (! blank($this->entry->tags)) {
            $hashtags = '';

            foreach ($this->entry->tags as $tag) {
                $hashtags .= '#' . Str::camel($tag->name);
            }

            $content .= "\n\n$hashtags";
        }

        $args = [
            'status' => $content,
            'visibility' => $this->entry->visibility ?? 'public',
        ];

        // Send the thing.
        $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer $token",
            ])
            ->asForm()
            ->post("$host/api/v1/statuses", $args)
            ->json();

        if (! empty($response['error'])) {
            Log::debug("[SyndicateToMastodon] Something went wrong: {$response['error']}");
            $this->entry->forceFill([
                'meta->syndicate_to_mastodon_error' => (array) $response['error'],
            ]);
        }

        if (! empty($response['url']) && filter_var($response['url'], FILTER_VALIDATE_URL)) {
            $syndicationUrls[] = filter_var($response['url'], FILTER_SANITIZE_URL);

            $this->entry->meta()->updateOrCreate(
                ['key' => 'syndication'],
                ['value' => $syndicationUrls]
            );
        }
    }
}
