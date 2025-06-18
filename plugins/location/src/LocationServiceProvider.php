<?php

namespace Plugins\Location;

use App\Models\Entry;
use Illuminate\Support\ServiceProvider;
use Plugins\Location\Jobs\GetLocation;
use Plugins\Location\Jobs\GetWeather;

class LocationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/location.php' => config_path('location.php'),
        ]);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'location');

        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        /**
         * Display "Location" "meta box."
         */
        add_action('admin:entries:edit', function (?Entry $entry = null, ?string $type = null) {
            if (! in_array($type, ['article', 'note'], true)) {
                return;
            }

            if ($entry) {
                $meta = $entry->meta->firstWhere('key', 'geo');
            }

            $geo = [
                'lat' => $meta->value['lat'] ?? '',
                'lon' => $meta->value['lon'] ?? '',
                'address' => $meta->value['address'] ?? '',
            ];

            // Render a "meta box."
            echo view('location::entries.edit', compact('entry', 'geo'))->render();
        }, 20, 2);

        /**
         * Store location data, from input form fields, to entry meta.
         */
        add_action('entries:saved', function (Entry $entry) {
            if (! request()->is('admin*')) {
                return;
            }

            $geo = ($meta = $entry->meta->firstWhere('key', 'geo'))
                ? $meta->value
                : [];

            $lat = request()->input('geo_lat');
            $lon = request()->input('geo_lon');
            $address = request()->input('geo_address');

            if (is_numeric($lat)) {
                $geo['lat'] = round((float) $lat, 8);
            } else {
                unset($geo['lat']);
            }

            if (is_numeric($lon)) {
                $geo['lon'] = round((float) $lon, 8);
            } else {
                unset($geo['lon']);
            }

            if (! empty($address)) {
                $geo['address'] = strip_tags((string) $address);
            } else {
                unset($geo['address']);
            }

            if (empty($geo)) {
                $entry->meta()->where('key', 'geo')->delete();

                return;
            }

            $entry->meta()->updateOrCreate(
                ['key' => 'geo'],
                ['value' => $geo]
            );
        });

        /**
         * When needed, queue a reverse geocoding job.
         *
         * Runs after an entry, its tags, and possible metadata are saved.
         */
        add_action('entries:saved', function (Entry $entry) {
            // Get an "address," i.e., city or municipality.
            GetLocation::dispatch($entry);
            GetWeather::dispatch($entry);
        });
    }
}
