<?php

use App\Models\Entry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

function is_archive($type = null): bool
{
    if (request()->is('admin/*')) {
        return false;
    }

    if ($type) {
        if (Route::currentRouteName() === Str::plural($type) . '.index') {
            return true;
        }

        return false;
    }

    if (Route::currentRouteName() === 'tags.show') {
        // Tag archive.
        return true;
    }

    if (request()->is('search')) {
        // Search results.
        return true;
    }

    foreach (array_keys(Entry::getRegisteredTypes()) as $type) {
        if (Route::currentRouteName() === Str::plural($type) . '.index') {
            return true;
        }
    }

    return false;
}

function is_page(): bool
{
    if (request()->is('admin/*')) {
        return false;
    }

    if (is_singular('page')) {
        return true;
    }

    return false;
}

function is_singular($type = null): bool
{
    if (request()->is('admin/*')) {
        return false;
    }

    if ($type) {
        if (Route::currentRouteName() === Str::plural($type) . '.show') {
            return true;
        }

        return false;
    }

    foreach (array_keys(Entry::getRegisteredTypes()) as $type) {
        if (Route::currentRouteName() === Str::plural($type) . '.show') {
            return true;
        }
    }

    return false;
}

function body_class(): string
{
    $classes = [];

    if (request()->is('/')) {
        $classes[] = 'home';
    }

    if (is_archive()) {
        $classes[] = 'archive';
    }

    if (is_singular()) {
        $classes[] = 'single';
    }

    if (is_page()) {
        $classes[] = 'page';
    }

    return implode(' ', $classes);
}

function random_slug(): string
{
    do {
        $slug = bin2hex(openssl_random_pseudo_bytes(5));
    } while (Entry::where('slug', $slug)->withTrashed()->exists());

    return $slug;
}

function url_to_entry(string $url): Entry
{
    if (! filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    if (! $slug = basename($url)) {
        return null;
    }

    $entry = Entry::where('slug', $slug)
        ->first();

    return Eventy::filter('entries.url_to_entry', $entry, $url);
}

/**
 * Registers a plugin or theme's namespace(s), for autoloading.
 */
function autoload_register(array $namespaces, string $dir): ?string
{
    foreach ($namespaces as $namespace => $paths) {
        $paths = (array) $paths;

        spl_autoload_register(function ($class) use ($namespace, $paths, $dir) {
            // Check if the namespace matches the class we are looking for.
            if (! preg_match('~^' . preg_quote($namespace) . '~', $class)) {
                return null;
            }

            // Remove the namespace from the file path. (Plugins *must* use PSR4.)
            $class = str_replace($namespace, '', $class);
            $filename = preg_replace('~\\\\~', '/', $class) . '.php';

            foreach ($paths as $path) {
                $filename = "$dir/$path/$filename";

                if (! file_exists($filename)) {
                    return null;
                }

                // Load class file.
                include_once $filename;

                return $filename;
            }
        });
    }

    return null;
}
