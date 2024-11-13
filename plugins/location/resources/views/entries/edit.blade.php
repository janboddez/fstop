<div class="card mt-5">
    <header class="card-header">
        <h2 class="card-header-title">{{ __('Location') }}</h2>
    </header>

    <div class="card-content">
        <div class="field is-grouped is-grouped-multiline">
            <div class="control" style="width: 37.5%;">
                <label for="geo_lat">{{ __('Latitude') }}</label>
                <input class="input" type="text" id="geo_lat" name="geo_lat" value="{{ old('geo_lat', $geo['lat']) }}">
            </div>

            <div class="control" style="width: 37.5%;">
                <label for="geo_lon">{{ __('Longitude') }}</label>
                <input class="input" type="text" id="geo_lon" name="geo_lon" value="{{ old('geo_lon', $geo['lon']) }}">
            </div>
        </div>

        <div class="field">
            <label class="is-sr-only" for="geo_address">{{ __('Location Name') }}</label>
            <div class="control">
                <input class="input" type="text" id="geo_address" name="geo_address" value="{{ old('geo_address', $geo['address']) }}">
            </div>
        </div>
    </div>
</div>

<script>
// Have the browser fill out empty latitude and longitude fields.
const geoLat = document.getElementById('geo_lat');
const geoLon = document.getElementById('geo_lon');

navigator.geolocation.getCurrentPosition(
    (position) => {
        if (geoLat && ! geoLat.value) {
            geoLat.value = position.coords.latitude.toString();
        }

        if (geoLon && ! geoLon.value) {
            geoLon.value = position.coords.longitude.toString();
        }
    }, (error) => {
        console.log(error);
    }
);
</script>
