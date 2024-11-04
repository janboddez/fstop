<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

foreach (['note', 'like'] as $type) {
    Route::middleware(['web'])
        ->prefix(Str::plural($type))
        ->name(Str::plural($type) . '.')
        ->group(function () {
            Route::get('/', [EntryController::class, 'index'])
                ->name('index');

            Route::get('feed', FeedController::class)
                ->name('feed');

            Route::get('{slug}', [EntryController::class, 'show'])
                ->name('show');
        });
}
