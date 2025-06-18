<?php

use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EntryController as EntryAdminController;
use App\Http\Controllers\Admin\TagController as TagAdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/**
 * Authentication and the Like
 */
Route::get('login', [LoginController::class, 'login'])->name('login');
Route::post('login', [LoginController::class, 'authenticate']);

Route::get('logout', [LoginController::class, 'logout']);

/**
 * Admin Interface
 */
Route::group(['middleware' => 'auth', 'prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::get('/', DashboardController::class);

    /**
     * Resource Controllers
     */
    Route::resource('entries', EntryAdminController::class)
        ->except(['show', 'destroy']);

    Route::delete('entries/{entry}', [EntryAdminController::class, 'destroy'])
        ->withTrashed()
        ->name('entries.destroy');

    Route::resource('tags', TagAdminController::class)
        ->except(['create', 'show']);

    Route::resource('attachments', AttachmentController::class)
        ->except('show');

    Route::resource('comments', CommentController::class)
        ->except(['create', 'show']);

    /**
     * Everything Else
     */
    Route::group(['prefix' => 'entries', 'as' => 'entries.'], function () {
        Route::post('{entry}/restore', [EntryAdminController::class, 'restore'])
            ->withTrashed()
            ->name('restore');

        Route::post('empty-trash', [EntryAdminController::class, 'emptyTrash'])
            ->name('empty-trash');

        Route::post('edit', [EntryAdminController::class, 'bulkEdit'])
            ->name('bulk-edit');
    });

    Route::group(['prefix' => 'comments', 'as' => 'comments.'], function () {
        Route::post('{comment}/approve', [CommentController::class, 'approve'])
            ->name('approve');

        Route::post('{comment}/unapprove', [CommentController::class, 'unapprove'])
            ->name('unapprove');

        Route::post('edit', [CommentController::class, 'bulkEdit'])
            ->name('bulk-edit');
    });

    /** @todo Rename `profile` to `users`, etc. */
    foreach (['profile', 'themes', 'plugins', 'settings', 'followers'] as $name) {
        $controller = '\\App\\Http\\Controllers\\Admin\\' . ucfirst(Str::singular($name)) . 'Controller';

        Route::group(['prefix' => $name, 'as' => "$name."], function () use ($controller, $name) {
            Route::get('/', [$controller, 'index'])
                ->name('index');

            if ($name !== 'followers') {
                Route::put('/', [$controller, 'update'])
                    ->name('update');
            }
        });
    }
});

/**
 * Front-End Routes
 */
Route::prefix('articles')
    ->name('articles.')
    ->group(function () {
        Route::get('/', [EntryController::class, 'index'])
            ->name('index');

        Route::get('feed', FeedController::class)
            ->name('feed');

        Route::get('{slug}', [EntryController::class, 'show'])
            ->name('show');
    });

Route::get('/', [EntryController::class, 'index']);

/** @todo Add tag, user feeds. */
Route::prefix('tags')
    ->name('tags.')
    ->group(function () {
        Route::get('{tag:slug}', [TagController::class, 'show'])
            ->name('show');
    });

// User profiles.
Route::prefix('users')
    ->name('users.')
    ->group(function () {
        Route::get('{user:login}', UserController::class)
            ->name('show');
    });

// Alternative user profiles.
Route::get('@{user:login}', UserController::class);

// Generic feed; contains all content types.
Route::get('feed', FeedController::class);

// "Stream."
Route::get('stream', [EntryController::class, 'stream']);

Route::get('search', SearchController::class);

// Catch-all route for pages.
Route::get('{slug}', [EntryController::class, 'show'])
    ->where('slug', '.*')
    ->name('pages.show');
