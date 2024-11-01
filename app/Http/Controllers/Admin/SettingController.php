<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        // Fetch known themes.
        $option = Option::firstOrCreate(
            ['key' => 'site_settings'],
            ['value' => []]
        );

        $settings = array_merge([
            'name' => config('app.name'),
            'tagline' => '',
        ], $option->value);

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $option = Option::where('key', 'site_settings')
            ->first();

        $validated = $request->validate([
            'name' => 'nullable|max:250',
            'tagline' => 'nullable|max:250',
        ]);

        // Save.
        $option->value = $validated;
        $option->save();

        return back()
            ->with('success', __('Changes saved!'));
    }
}
