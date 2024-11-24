<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FeedController extends Controller
{
    public function __invoke(Request $request): Response
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
            ->with('user')
            ->limit(15);

        /** @todo Use `when()`? */
        if ($type) {
            $entries->ofType($type);
        } else {
            $entries->whereIn('type', get_registered_entry_types('slug', 'page'));
        }

        $entries = $entries->get();

        return response()->view('theme::entries.feed', compact('entries', 'type'))
            ->header('Content-Type', 'application/rss+xml; charset=' . config('app.charset', 'utf-8'));
    }
}
