<?php

namespace Plugins\Location\Jobs;

use App\Models\Entry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetWeather implements ShouldQueue
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
        // Prevent sending mentions for "old" posts.
        /** @todo Make smarter. Like look at `published`! */
        if ($this->entry->created_at->lt(now()->subHour())) {
            Log::debug("[Location] Skipping fetching weather info for entry {$this->entry->id}: too old");

            return;
        }

        if ($this->entry->status !== 'published') {
            return;
        }

        if ($this->entry->visibility === 'private') {
            return;
        }

        if (! in_array($this->entry->type, ['article', 'note'], true)) {
            return;
        }

        if ($this->entry->meta->firstWhere('key', 'weather')) {
            // We've previously stored weather details for this entry.
            return;
        }

        $meta = $this->entry->meta->firstWhere('key', 'geo');

        if (empty($meta->value['lat'])) {
            return;
        }

        if (empty($meta->value['lon'])) {
            return;
        }

        $weather = $this->getWeather(
            (float) $meta->value['lat'],
            (float) $meta->value['lon']
        );

        if (! empty($weather)) {
            // Add (or update, but we don't normally update) the `weather` custom field.
            $this->entry->meta()->updateOrCreate(
                ['key' => 'weather'],
                ['value' => $weather]
            );
        }
    }

    /**
     * Given a latitude and longitude, returns a location name ("address").
     *
     * Uses OSM's Nominatim for geocoding.
     */
    protected function getWeather(float $lat, float $lon): array
    {
        $weather = [];

        // Try the cache first.
        $data = Cache::remember("location:weather:$lat:$lon", 60 * 15, function () use ($lat, $lon) {
            $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => config('location.weather.api_key'),
                ]);

            if (! $response->successful()) {
                Log::error("[Location] Failed to retrieve weather data for $lat, $lon");

                return null;
            }

            return $response->json();
        });

        $weather['temperature'] = isset($data['main']['temp']) && is_numeric($data['main']['temp'])
            ? (float) round($data['main']['temp'], 2)
            : null;

        $weather['humidity'] = isset($data['main']['temp']) && is_numeric($data['main']['temp'])
            ? (float) round($data['main']['temp'], 2)
            : null;

        $temp = ! empty($data['weather'])
            ? ((array) $data['weather'])[0]
            : null;

        $weather['id'] = isset($temp['id'])
            ? static::iconMap((int) $temp['id'])
            : null;

        $weather['description'] = isset($temp['description'])
            ? ucfirst(strip_tags($temp['description']))
            : null;

        return array_filter($weather);
    }

    /**
     * Maps OpenWeather's IDs to SVG icons. Kindly borrowed from David Shanske's Simple Location plugin for WordPress.
     *
     * @link https://github.com/dshanske/simple-location
     * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 2 or later
     *
     * @todo Actually use this code, haha!
     */
    protected static function iconMap(int $id): string
    {
        if (in_array($id, [200, 201, 202, 230, 231, 232], true)) {
            return 'wi-thunderstorm';
        }

        if (in_array($id, [210, 211, 212, 221], true)) {
            return 'wi-lightning';
        }

        if (in_array($id, [300, 301, 321, 500], true)) {
            return 'wi-sprinkle';
        }

        if (in_array($id, [302, 311, 312, 314, 501, 502, 503, 504], true)) {
            return 'wi-rain';
        }

        if (in_array($id, [310, 511, 611, 612, 615, 616, 620], true)) {
            return 'wi-rain-mix';
        }

        if (in_array($id, [313, 520, 521, 522, 701], true)) {
            return 'wi-showers';
        }

        if (in_array($id, [531, 901], true)) {
            return 'wi-storm-showers';
        }

        if (in_array($id, [600, 601, 621, 622], true)) {
            return 'wi-snow';
        }

        if (in_array($id, [602], true)) {
            return 'wi-sleet';
        }

        if (in_array($id, [711], true)) {
            return 'wi-smoke';
        }

        if (in_array($id, [721], true)) {
            return 'wi-day-haze';
        }

        if (in_array($id, [731, 761], true)) {
            return 'wi-dust';
        }

        if (in_array($id, [741], true)) {
            return 'wi-fog';
        }

        if (in_array($id, [771, 801, 802, 803], true)) {
            return 'wi-cloudy-gusts';
        }

        if (in_array($id, [781, 900], true)) {
            return 'wi-tornado';
        }

        if (in_array($id, [800], true)) {
            return 'wi-day-sunny';
        }

        if (in_array($id, [804], true)) {
            return 'wi-cloudy';
        }

        if (in_array($id, [902, 962], true)) {
            return 'wi-hurricane';
        }

        if (in_array($id, [903], true)) {
            return 'wi-snowflake-cold';
        }

        if (in_array($id, [904], true)) {
            return 'wi-hot';
        }

        if (in_array($id, [905], true)) {
            return 'wi-windy';
        }

        if (in_array($id, [906], true)) {
            return 'wi-day-hail';
        }

        if (in_array($id, [957], true)) {
            return 'wi-strong-wind';
        }

        if (in_array($id, [762], true)) {
            return 'wi-volcano';
        }

        if (in_array($id, [751], true)) {
            return 'wi-sandstorm';
        }

        return '';
    }
}
