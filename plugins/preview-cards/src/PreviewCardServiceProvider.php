<?php

namespace Plugins\PreviewCards;

use App\Models\Entry;
use Illuminate\Support\ServiceProvider;
use Plugins\PreviewCards\Jobs\GetPreviewCard;

class PreviewCardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        /** @todo Use a proper observer class, rather than "action hooks." */
        add_action('entries.saved', function (Entry $entry) {
            GetPreviewCard::dispatch($entry);
        });
    }
}
