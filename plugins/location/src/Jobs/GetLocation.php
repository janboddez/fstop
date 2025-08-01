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

        $meta = $this->entry->meta->firstWhere('key', 'geo');

        if (! empty($meta->value['address'])) {
            return;
        }

        if (empty($meta->value['lat'])) {
            return;
        }

        if (empty($meta->value['lon'])) {
            return;
        }

        $address = $this->getAddress(
            (float) $meta->value['lat'],
            (float) $meta->value['lon']
        );

        $meta = array_filter([
            'lat' => $meta->value['lat'],
            'lon' => $meta->value['lon'],
            'address' => $address,
        ]);

        $this->entry->meta()->updateOrCreate(
            ['key' => 'geo'],
            ['value' => $meta]
        );
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
                        'location:user_agent',
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
                ])
                ->json();

            return isset($response) && is_array($response)
                ? $response
                : [];
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
