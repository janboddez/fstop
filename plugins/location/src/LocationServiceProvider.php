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
        add_action('admin.entries.edit', function (Entry $entry = null, string $type = null) {
            if (! in_array($type, ['article', 'note'], true)) {
                return;
            }

            $geo = [
                'lat' => $entry->meta['geo']['lat'] ?? '',
                'lon' => $entry->meta['geo']['lon'] ?? '',
                'address' => $entry->meta['geo']['address'] ?? '',
            ];

            // Render a "meta box."
            echo view('location::entries.edit', compact('geo'))->render();
        }, 20, 2);

        /**
         * Store location data, from input form fields, to entry meta.
         *
         * Runs before an entry is saved.
         */
        add_action('entries.saving', function (Entry $entry) {
            $lat = request()->input('geo_lat');
            $lon = request()->input('geo_lon');
            $address = request()->input('geo_address');

            $meta = $entry->meta;
            $meta['geo'] = $meta['geo'] ?? [];

            if (is_numeric($lat)) {
                $meta['geo']['lat'] = round((float) $lat, 8);
            } else {
                unset($meta['geo']['lat']);
            }

            if (is_numeric($lon)) {
                $meta['geo']['lon'] = round((float) $lon, 8);
            } else {
                unset($meta['geo']['lon']);
            }

            if (! empty($address)) {
                $meta['geo']['address'] = strip_tags((string) $address);
            } else {
                unset($meta['geo']['address']);
            }

            $entry->meta = array_filter($meta);
        });

        /**
         * When needed, queue a reverse geocoding job.
         *
         * Runs after an entry is saved.
         *
         * @todo Prevent race conditions, as multiple jobs may alter the same entry at once.
         *       Either use a separate meta table or lock jobs.
         */
        add_action('entries.saved', function (Entry $entry) {
            // Get an "address," i.e., city or municipality.
            GetLocation::dispatch($entry);
        }, 99);
    }
}
