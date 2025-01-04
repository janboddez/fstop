<?php

namespace App\Jobs;

use App\Models\Entry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use janboddez\Webmention\WebmentionSender;

class SendWebmention implements ShouldQueue
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
        // Prevent sending mentions for "old" posts.
        /** @todo Make smarter. */
        if ($this->entry->created_at->lt(now()->subHours(2))) {
            Log::debug("[Webmention] Skipping sending mentions for entry {$this->entry->id}: too old");

            return;
        }

        if ($this->entry->trashed()) {
            return;
        }

        if ($this->entry->status !== 'published') {
            return;
        }

        if ($this->entry->visibility === 'private') {
            return;
        }

        $content = $this->entry->content ?? '';
        if (empty($content)) {
            return;
        }

        // Previously sent webmentions, if any.
        $previousMentions = $this->entry->meta['webmention'] ?? [];

        $results = [];

        foreach (WebmentionSender::findLinks($content) as $target) {
            if (in_array($target, array_column($previousMentions, 'target'), true)) {
                // Skip (until we support updates, at least).
                Log::debug("[Webmention] Previously pinged the page at $target, skipping");

                continue;
            }

            $result = WebmentionSender::send($this->entry->permalink, $target);
            if ($result) {
                $results[md5($target)] = $result;
            }
        }

        if (! empty($results)) {
            $this->entry->meta()->updateOrCreate(
                ['key' => 'webmention'],
                ['value' => array_merge($previousMentions, $results)]
            );
        }
    }
}
