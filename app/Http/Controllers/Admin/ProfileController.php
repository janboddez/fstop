<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // "Default" fields.
        $settings = [
            'name' => $user->name,
            'email' => $user->email,
            'url' => $user->url ?? '',
            'bio' => '',
            'avatar' => '',
        ];

        // Previously set user meta.
        foreach ($user->meta as $meta) {
            $settings[$meta->key] = ! empty($meta->value[0]) && ! is_array($meta->value[0])
                ? $meta->value[0]
                : json_encode($meta->value, JSON_UNESCAPED_SLASHES);
        }

        return view('admin.profile', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:250',
            'email' => 'required|email|max:250',
            'url' => 'required|url|max:250', // Required because IndieAuth, etc.
            'bio' => 'nullable|string',
            'avatar' => 'nullable|url|max:250', // _Could_ convert this to an attachment ID then.
        ]);

        $user = $request->user();

        // Update `name`, `email`, and `url`.
        $user->update($validated);

        // Everything else is "user meta."
        $meta = array_diff_key($validated, array_flip($user->getFillable()));

        foreach (prepare_meta(array_keys($meta), array_values($meta), $user) as $key => $value) {
            $user->meta()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()
            ->withSuccess(__('Changes saved!'));
    }
}
