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
        /** @todo Use a proper observer class, rather than "action hooks." */
        add_action('entries.saved', function (Entry $entry) {
            SyndicateToMastodon::dispatch($entry);
        });
    }
}
