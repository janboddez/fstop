<?php

namespace Plugins\Location\Jobs;

use App\Models\Entry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TorMorten\Eventy\Facades\Events as Eventy;

class GetLocation implements ShouldQueue
{
    use Queueable;

    public Entry $entry;

    /**
     * Create a new job instance.
     */
    public function __construct(Entry $entry)
    {
        $this->entry = $entry->withoutRelations();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->entry->status !== 'published') {
            return;
        }

        if ($this->entry->visibility === 'private') {
            return;
        }

        if (! in_array($this->entry->type, ['article', 'note'], true)) {
            return;
        }

        $meta = $this->entry->meta;

        if (! empty($meta['geo']['address'])) {
            return;
        }

        if (empty($meta['geo']['lat'])) {
            return;
        }

        if (empty($meta['geo']['lon'])) {
            return;
        }

        $meta['geo'] = array_filter(array_merge(
            $meta['geo'],
            [
                'address' => $this->getAddress(
                    (float) $meta['geo']['lon'],
                    (float) $meta['geo']['lat']
                ),
            ]
        ));

        $this->entry->meta = $meta; // Shouldn't we worry about race conditions here?

        /** @todo Look into the `WithoutOverlapping` job middleware. */

        $this->entry->saveQuietly();
    }

    /**
     * Given a latitude and longitude, returns address data (i.e., reverse geolocation).
     *
     * Uses OSM's Nominatim for geocoding.
     */
    protected function getAddress(float $lon, float $lat): ?string
    {
        // Attempt to retrieve "address" from cache.
        $value = Cache::remember('entries:location:address', 3600, function () use ($lon, $lat) {
            // Reverse geocode `[$lon, $lat]` instead.
            $response = Http::withHeaders([
                    'User-Agent' => Eventy::filter(
                        'location.user-agent',
                        'F-Stop/' . config('app.version') . '; ' . url('/'),
                        $this->entry
                    ),
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lon,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                Log::error("[Location] Failed to retrieve address data for $lat, $lon");
                return null;
            }

            $location = $response->json();

            if (! empty($location['error'])) {
                Log::error("[Location] {$location['error']} ($lat, $lon)");
                return null;
            }

            $address = $location['address']['town']
                ?? $location['address']['city']
                ?? $location['address']['municipality']
                ?? null;

            if (is_string($address) && ! empty($location['address']['country_code'])) {
                $address .= ', ' . strtoupper($location['address']['country_code']);
            }

            return $address;
        });

        return $value;
    }
}
