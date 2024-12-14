<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;
use Plugins\Scrobbble\Http\Controllers\ScrobbleController;
use Plugins\Scrobbble\Http\Controllers\V2\ScrobbleController as V2Controller;

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
        // The v1.2 protocol.
        Route::get('scrobbble', [ScrobbleController::class, 'handshake']);
        Route::post('submissions', [ScrobbleController::class, 'scrobble']);
        Route::match(['get', 'post'], 'nowplaying', [ScrobbleController::class, 'now']);

        // The v2.0 protocol (or select bits of it).
        Route::match(['get', 'post'], 'scrobbble/2.0', [V2Controller::class, 'handle']);
    });
