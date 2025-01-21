<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

use function App\Support\ActivityPub\fetch_profile;

class FollowerController extends Controller
{
    public function index(Request $request): View
    {
        $followers = auth()->user()->followers()
            ->withPivot('created_at')
            ->orderByPivot('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate();

        // foreach ($followers as $follower) {
        //     // Hopefully (?) trigger an avatar download ...
        //     $meta = fetch_profile(filter_var($follower->url, FILTER_SANITIZE_URL), auth()->user(), true);
        //     foreach (prepare_meta($meta, $follower) as $key => $value) {
        //         $follower->meta()->updateOrCreate(
        //             ['key' => $key],
        //             ['value' => $value]
        //         );
        //     }
        // }

        return view('admin.followers', compact('followers'));
    }
}
