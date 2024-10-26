<?php

use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EntryController as EntryAdminController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\TagController as TagAdminController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Models\Entry;
use Illuminate\Support\Facades\Route;

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

Route::get('login', [LoginController::class, 'login'])->name('login');
Route::post('login', [LoginController::class, 'authenticate']);

Route::get('logout', [LoginController::class, 'logout']);

Route::group(['middleware' => 'auth', 'prefix' => 'admin', 'as' => 'admin.'], function () {
    // Admin routes.
    Route::get('/', DashboardController::class);

    Route::resource('entries', EntryAdminController::class)
        ->except(['show', 'destroy']);

    Route::delete('entries/{entry}', [EntryAdminController::class, 'destroy'])
        ->withTrashed()
        ->name('entries.destroy');

    Route::post('entries/{entry}/restore', [EntryAdminController::class, 'restore'])
        ->withTrashed()
        ->name('entries.restore');

    Route::resource('tags', TagAdminController::class)
        ->except(['create', 'show']);

    Route::resource('attachments', AttachmentController::class)
        ->except('show');

    Route::resource('comments', CommentController::class)
        ->except(['create', 'show']);

    Route::post('comments/{comment}/approve', [CommentController::class, 'approve'])
        ->name('comments.approve');

    Route::post('comments/{comment}/unapprove', [CommentController::class, 'unapprove'])
        ->name('comments.unapprove');

    Route::get('plugins', [PluginController::class, 'index'])
        ->name('plugins.index');

    Route::post('plugins', [PluginController::class, 'update'])
        ->name('plugins.update');

    Route::get('themes', [ThemeController::class, 'index'])
        ->name('themes.index');

    Route::post('themes', [ThemeController::class, 'update'])
        ->name('themes.update');
});

// Everything below is front-end routes.
Route::get('/', [EntryController::class, 'index'])
    ->name('articles.index');

Route::get('articles', [EntryController::class, 'articleArchive'])
    ->name('articles.archive');

foreach (array_keys(Entry::TYPES) as $type) {
    Route::get(Str::plural($type) . '/feed', FeedController::class)
        ->name(Str::plural($type) . '.feed');

    if ($type !== 'article') {
        Route::get(Str::plural($type), [EntryController::class, 'index'])
            ->name(Str::plural($type) . '.index');
    }

    if ($type !== 'page') {
        Route::get(Str::plural($type) . '/{slug}', [EntryController::class, 'show'])
            ->name(Str::plural($type) . '.show');
    }
}

Route::get('tags/{tag:slug}', [TagController::class, 'show'])
    ->name('tags.show');

// "Stream."
Route::get('stream', [EntryController::class, 'stream']);

// Generic feed; contains all content types.
Route::get('feed', FeedController::class);

Route::get('search', SearchController::class);

// Catch-all route for pages.
Route::get('{slug}', [EntryController::class, 'show'])
    ->where('slug', '.*')
    ->name('pages.show');
