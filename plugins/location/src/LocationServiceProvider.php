<?php

namespace Plugins\Location;

use App\Models\Entry;
use Illuminate\Support\ServiceProvider;
use Plugins\Location\Jobs\GetLocation;

class LocationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        /** @todo Use a proper observer class, rather than "action hooks." */
        add_action('entries.saved', function (Entry $entry) {
            // Store location data, if any.
            $lat = request()->input('geo_lat');
            $lon = request()->input('geo_lon');

            if ($lat && is_numeric($lat) && $lon && is_numeric($lon)) {
                $meta = $entry->meta;

                if (isset($meta['geo'])) {
                    $meta['geo'] = array_merge(
                        $meta['geo'],
                        [
                            'lat' => round((float) $lat, 8),
                            'lon' => round((float) $lon, 8),
                        ]
                    );
                }

                $entry->meta = $meta; // Shouldn't we worry about race conditions here?
                $entry->saveQuietly();
            }

            // Get an "address," i.e., city or municipality.
            GetLocation::dispatch($entry);
        });
    }
}
