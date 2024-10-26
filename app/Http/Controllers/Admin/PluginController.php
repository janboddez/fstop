<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\Request;

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
        $rules = [];

        foreach ($plugins as $slug => $plugin) {
            $rules['plugin_' . $slug] = 'boolean';
        }

        $validated = $request->validate($rules);

        foreach ($plugins as $slug => $plugin) {
            $plugins[$slug]['active'] = ! empty($validated['plugin_' . $slug]);
        }

        // Save.
        $option->value = $plugins;
        $option->save();

        return back()
            ->with('success', __('Changes saved!'));
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
