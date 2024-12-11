<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;
use Plugins\Scrobbble\Http\Controllers\ScrobbleController;

Route::middleware('web')
    ->prefix('listens')
    ->name('listens.')
    ->group(function () {
        Route::get('/', [EntryController::class, 'index'])
            ->name('index');

        Route::get('feed', FeedController::class)
            ->name('feed');

        Route::get('{slug}', [EntryController::class, 'show'])
            ->name('show');
    });

Route::middleware('api')
    ->prefix('scrobbble/v1')
    ->group(function () {
        Route::get('scrobbble', [ScrobbleController::class, 'handshake']);
        Route::get('nowplaying', [ScrobbleController::class, 'now']);

        Route::post('nowplaying', [ScrobbleController::class, 'now']);
        Route::post('submissions', [ScrobbleController::class, 'scrobble']);
    });
