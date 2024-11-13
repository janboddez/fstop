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

        /** @todo Bail if a post isn't recent! We don't want to add possibly long locations way after the fact. */

        if (! empty($this->entry->meta['geo']['address'])) {
            return;
        }

        if (empty($this->entry->meta['geo']['lat'])) {
            return;
        }

        if (empty($this->entry->meta['geo']['lon'])) {
            return;
        }

        $address = $this->getAddress(
            (float) $this->entry->meta['geo']['lat'],
            (float) $this->entry->meta['geo']['lon']
        );

        $this->entry->forceFill([
            'meta->geo->address' => $address,
        ]);

        $this->entry->saveQuietly();
    }

    /**
     * Given a latitude and longitude, returns a location name ("address").
     *
     * Uses OSM's Nominatim for geocoding.
     */
    protected function getAddress(float $lat, float $lon): ?string
    {
        // Attempt to retrieve from cache.
        $data = Cache::remember("location:$lat:$lon", 60 * 60 * 24 * 7, function () use ($lat, $lon) {
            // Reverse geocode `[$lat, $lon]` instead.
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

            return $response->json();
        });

        if (! empty($data['error'])) {
            Log::error("[Location] {$data['error']} ($lat, $lon)");
            return null;
        }

        $address = $data['address']['town']
            ?? $data['address']['city']
            ?? $data['address']['municipality']
            ?? null;

        if (is_string($address) && ! empty($data['address']['country_code'])) {
            $address .= ', ' . strtoupper($data['address']['country_code']);
        }

        return $address;
    }
}
