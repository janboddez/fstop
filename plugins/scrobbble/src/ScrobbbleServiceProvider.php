<?php

namespace Plugins\Scrobbble;

use App\Models\Entry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ScrobbbleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Need to call this in the `register()` (rather than `boot()`) method in order to register our routes before
        // "core's" catch-all page route.
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->registerHooks(); // For these, it doesn't really matter.
    }

    protected function registerHooks(): void
    {
        /**
         * Adds short-form entry types.
         *
         * @param  array  $types Registered types.
         * @return array  $types Filtered array of types.
         */
        add_filter('entries:registered_types', function ($types) {
            $types['listen'] = ['icon' => 'mdi mdi-playlist-music'];

            return $types;
        });

        /**
         * Sets autogenerated names, for these "short-form" entry types.
         *
         * @param  string  $name  Original entry name.
         * @param  Entry   $entry Entry being saved.
         * @return string         Filtered name.
         */
        add_filter('entries:set_name', function ($name, $entry) {
            if ('listen' !== $entry->type) {
                // Do nothing.
                return $name;
            }

            // Generate a title off the (current) content.
            $name = strip_tags($entry->content); // Strip tags.
            $name = Str::words($name, 10, ' …'); // Shorten.
            $name = html_entity_decode($name); // Decode quotes, etc. (We escape on output.)
            $name = Str::replaceEnd('… …', '…', $name);
            $name = preg_replace('~\s+~', ' ', $name); // Get rid of excess whitespace.
            $name = Str::limit($name, 250, '…'); // Shorten (again).

            return $name;
        }, 20, 2);

        /**
         * Bypasses the default slug generation process.
         *
         * @param  string      $slug  Whether to bypass "core" behavior. Default `null`.
         * @param  Entry       $entry Entry being saved.
         * @return string|null        Autogenerated slug, or `null`.
         */
        add_filter('entries:set_slug', function ($slug, $entry) {
            if ('listen' !== $entry->type) {
                return $slug;
            }

            if (! empty($entry->slug)) {
                // A slug was set previously; let "core" do its thing.
                return $slug;
            }

            // Ensure listens get a random slug rather than a title-based one.
            return random_slug();
        }, 20, 2);
    }
}
