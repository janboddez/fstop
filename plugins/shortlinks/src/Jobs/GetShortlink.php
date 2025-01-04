<?php

namespace Plugins\Shortlinks\Jobs;

use App\Models\Entry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GetShortlink implements ShouldQueue
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

        if (! in_array($this->entry->type, ['article', 'note'], true)) {
            return;
        }

        $token = config('shortlinks.token');
        if (empty($token)) {
            Log::debug('[Shortlinks] Missing token');

            return;
        }

        if ($this->entry->meta->firstWhere('key', 'short_url')) {
            // A short URL already exists.
            return;
        }

        // Request short URL.
        $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
            ])
            ->asForm()
            ->post('https://bddz.be/shorten', [
                'url' => $this->entry->permalink,
            ])
            ->body();

        if (! empty($response) && Str::isUrl($response, ['http', 'https'])) {
            $this->entry->meta()->updateOrCreate(
                ['key' => 'short_url'],
                ['value' => (array) filter_var($response, FILTER_SANITIZE_URL)]
            );
        }
    }
}
