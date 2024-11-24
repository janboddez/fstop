<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ThemeController extends Controller
{
    public function index(Request $request)
    {
        // Fetch known themes.
        $option = Option::firstOrCreate(
            ['key' => 'themes'],
            ['value' => []]
        );

        $themes = [];

        // Collect installed themes.
        $dirs = glob(__DIR__ . '/../../../../themes/*', GLOB_ONLYDIR);
        $dirs = array_map('realpath', $dirs);

        foreach ($dirs as $dir) {
            $slug = basename($dir);

            $themes[$slug] = $this->registerTheme($dir);
            $themes[$slug]['active'] = false;

            if (isset($option->value[$slug]) && $option->value[$slug]['active'] === true) {
                // Theme is currently active.
                $themes[$slug]['active'] = true;
            }
        }

        // Store current themes.
        $option->value = $themes;
        $option->save();

        return view('admin.themes', compact('themes'));
    }

    public function update(Request $request)
    {
        $option = Option::where('key', 'themes')
            ->first();

        $themes = $option->value ?? [];

        $validated = $request->validate(['theme' => 'string']);

        foreach ($themes as $slug => $theme) {
            $themes[$slug]['active'] = isset($validated['theme']) && $slug === $validated['theme'];
        }

        // Save.
        $option->value = $themes;
        $option->save();

        // Update view cache. Note that not everyone might want this ...
        Artisan::call('view:cache');

        return back()
            ->withSuccess(__('Changes saved!'));
    }

    /**
     * Returns theme data.
     */
    protected function registerTheme($dir)
    {
        $theme = [
            'active' => false,
            'name' => basename($dir),
            'dir' => $dir,
        ];

        if (file_exists("$dir/theme.json")) {
            $json = json_decode(file_get_contents("$dir/theme.json"), true);

            $theme['description'] = (string) $json['description'] ?? '';

            // Themes must use PSR4.
            if (isset($json['autoload']['psr-4'])) {
                $theme['namespaces'] = (array) $json['autoload']['psr-4'];
            }

            // Laravel service providers.
            if (isset($json['extra']['laravel']['providers'])) {
                $theme['providers'] = (array) $json['extra']['laravel']['providers'];
            }
        }

        return $theme;
    }
}
