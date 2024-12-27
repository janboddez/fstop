<?php

use App\Http\Controllers\ActivityPub\InboxController;
use App\Http\Controllers\ActivityPub\FollowerController;
use App\Http\Controllers\ActivityPub\OutboxController;
use App\Http\Controllers\ActivityPub\WebFingerController;
use App\Http\Controllers\Micropub\MicropubController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('micropub')
    ->name('micropub.')
    ->group(function () {
        Route::get('/', [MicropubController::class, 'get']);
        Route::post('/', [MicropubController::class, 'post'])
            ->middleware(App\Http\Middleware\ConvertToJsonSyntax::class);

        Route::post('media', [MicropubController::class, 'media'])
            ->name('media-endpoint');
    });

Route::get('.well-known/webfinger', WebFingerController::class);

/**
 * @todo Leave out `activitypub` prefix and use username rather than ID, i.e., follow the same URL scheme as the rest of
 *       the site.
 */
Route::prefix('activitypub')
    ->name('activitypub.')
    ->group(function () {
        Route::get('users/{user}/followers', FollowerController::class)
            ->name('followers');

        // Route::get('users/{user:login}/outbox', OutboxController::class);
        Route::get('users/{user}/outbox', OutboxController::class)
            ->name('outbox');

        // Route::get('users/{user:login}/outbox', OutboxController::class);
        Route::post('users/{user}/inbox', [InboxController::class, 'inbox'])
            ->name('inbox');

        Route::post('inbox', [InboxController::class, 'inbox']);
    });
