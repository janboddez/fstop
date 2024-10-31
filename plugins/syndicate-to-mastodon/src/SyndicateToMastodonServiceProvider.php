<?php

namespace Plugins\SyndicateToMastodon;

use App\Models\Entry;
use Illuminate\Support\ServiceProvider;
use Plugins\SyndicateToMastodon\Jobs\SyndicateToMastodon;

class SyndicateToMastodonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/mastodon.php' => config_path('mastodon.php'),
        ]);

        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        /** todo Use a proper observer class, rather than "action hooks."  */
        add_action('entries.saving', function (Entry $entry) {
            if (request()->has('syndicate_to_mastodon')) {
                $meta = $entry->meta;
                $meta['syndicate_to_mastodon'] = true;
                $entry->meta = $meta;
            }
        });

        add_action('entries.saved', function (Entry $entry) {
            SyndicateToMastodon::dispatch($entry);
        });
    }
}
