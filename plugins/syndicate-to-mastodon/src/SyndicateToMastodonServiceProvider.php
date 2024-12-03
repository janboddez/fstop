<?php

namespace Plugins\SyndicateToMastodon;

use App\Models\Entry;
use Illuminate\Http\Request;
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
        /**
         * Add a Mastodon syndication target to (responses to) Micropub `q=config` queries.
         */
        add_filter('micropub:syndicate_to', function (array $syndicationTargets, Request $request) {
            /** @todo Make this a "proper option"? */
            $host = config('mastodon.host');
            if (empty($host)) {
                return $syndicationTargets;
            }

            $syndicationTargets[] = [
                'uid' => config('mastodon.host'), /** @todo Add username. */
                'name' => __('Mastodon'),
            ];

            return $syndicationTargets;
        }, 20, 2);

        /** @todo Use a proper observer class, rather than "action hooks." */
        add_action('entries:saved', function (Entry $entry) {
            SyndicateToMastodon::dispatch($entry);
        });
    }
}
