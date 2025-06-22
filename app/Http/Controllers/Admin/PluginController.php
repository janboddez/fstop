<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use TorMorten\Eventy\Facades\Events as Eventy;

class PluginController extends Controller
{
    public function index(Request $request)
    {
        // Fetch known plugins.
        $option = Option::firstOrCreate(
            ['key' => 'plugins'],
            ['value' => []]
        );

        $plugins = [];

        // Collect installed plugins.
        $dirs = glob(__DIR__ . '/../../../../plugins/*', GLOB_ONLYDIR);
        $dirs = array_map('realpath', $dirs);

        foreach ($dirs as $dir) {
            $slug = basename($dir);

            $plugins[$slug] = $this->registerPlugin($dir);

            if (isset($option->value[$slug]) && $option->value[$slug]['active'] === true) {
                // Plugin is currently active.
                $plugins[$slug]['active'] = true;
            }
        }

        // Store current status.
        $option->value = $plugins;
        $option->save();

        return view('admin.plugins', compact('plugins'));
    }

    public function update(Request $request)
    {
        $option = Option::where('key', 'plugins')
            ->first();

        $plugins = $option->value ?? [];

        $validated = $request->validate([
            'plugins' => 'array',
            'plugins.*' => 'string',
        ]);

        foreach ($plugins as $slug => $attributes) {
            $plugins[$slug]['active'] = in_array($slug, $validated['plugins'] ?? [], true);
        }

        // Save.
        $option->value = $plugins;
        $option->save();

        if (Eventy::filter('plugin:activate:optimize', true)) {
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Artisan::call('queue:restart');
        }

        return back()
            ->withSuccess(__('Changes saved!'));
    }

    /**
     * Returns plugin data.
     */
    protected function registerPlugin($dir)
    {
        $plugin = [
            'active' => false,
            'name' => basename($dir),
            'dir' => $dir,
        ];

        $json = json_decode(file_get_contents("$dir/plugin.json"), true);

        $plugin['description'] = (string) $json['description'] ?? '';

        // Plugins *must* use PSR4.
        if (isset($json['autoload']['psr-4'])) {
            $plugin['namespaces'] = (array) $json['autoload']['psr-4'];
        }

        // Laravel service providers.
        if (isset($json['extra']['laravel']['providers'])) {
            $plugin['providers'] = (array) $json['extra']['laravel']['providers'];
        }

        return $plugin;
    }
}
