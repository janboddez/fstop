<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    public function __invoke(Request $request)
    {
        // Determine the post type from the URL.
        $type = $request->segment(1) !== 'feed'
            ? Str::singular($request->segment(1))
            : null;

        $entries = Entry::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->limit(15);

        if ($type) {
            $entries->ofType($type);
        } else {
            $entries->whereIn('type', array_diff(array_keys(Entry::getRegisteredTypes()), ['page']));
        }

        $entries = $entries->get();

        return response()->view('theme::entries.feed', compact('entries', 'type'))
            ->header('Content-Type', 'application/rss+xml; charset=' . config('app.charset', 'utf-8'));
    }
}
