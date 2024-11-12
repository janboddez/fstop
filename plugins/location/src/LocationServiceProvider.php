<?php

namespace Plugins\Location;

use App\Models\Entry;
use Illuminate\Support\ServiceProvider;
use Plugins\Location\Jobs\GetLocation;

class LocationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'location');
        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        /**
         * Display "Location" "meta box."
         */
        add_action('admin.entries.edit', function (Entry $entry, string $type = null) {
            if (! in_array($type, ['article', 'note'], true)) {
                return;
            }

            $geo = [
                'lon' => $entry->meta['geo']['lon'] ?? '',
                'lat' => $entry->meta['geo']['lat'] ?? '',
                'address' => $entry->meta['geo']['address'] ?? '',
            ];

            // Render a "meta box."
            echo view('location::entries.edit', compact('geo'))->render();
        }, 20, 2);

        /**
         * Stores location data. Tied to `entries.saved` rather than `entries.saving` because meta is (currently)
         * processed after entries are saved and would otherwise get overwritten. The obvious downside is it doesn't
         * actually show until you explicitly refresh the page once more after clicking "Create" or "Update."
         *
         * @todo Make "core" use the `saving` event instead.
         */
        add_action('entries.saved', function (Entry $entry) {
            // Store location data, if any.
            $lon = request()->input('geo_lon');
            $lat = request()->input('geo_lat');
            $address = request()->input('geo_address');

            $meta = $entry->meta;
            $meta['geo'] = $meta['geo'] ?? [];

            if (is_numeric($lon)) {
                $meta['geo']['lon'] = round((float) $lon, 8);
            } else {
                $meta['geo']['lon'] = null;
            }

            if (is_numeric($lat)) {
                $meta['geo']['lat'] = round((float) $lon, 8);
            } else {
                $meta['geo']['lat'] = null;
            }

            if (! empty($address)) {
                $meta['geo']['address'] = strip_tags((string) $address);
            } else {
                $meta['geo']['address'] = null;
            }

            $meta['geo'] = array_filter($meta['geo']);

            if (empty($meta['geo'])) {
                unset($meta['geo']);
            }

            $entry->meta = $meta;
            $entry->saveQuietly();
        }, 40);

        /**
         * When needed, queue a reverse geocoding job.
         */
        add_action('entries.saved', function (Entry $entry) {
            // Get an "address," i.e., city or municipality.
            GetLocation::dispatch($entry);
        }, 50);
    }
}
