<?php

return array_filter([
    App\Providers\AppServiceProvider::class,
    App\Providers\PluginServiceProvider::class,
    App\Providers\ThemeServiceProvider::class,
    config('app.activitypub', env('ACTIVITYPUB_ENABLED', false))
        ? App\Providers\ActivityPubServiceProvider::class
        : null,
]);
