<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EntryController extends Controller
{
    public function index(Request $request)
    {
        // Determine the post type from the URL.
        $type = ! empty($request->segment(1))
            ? Str::singular($request->segment(1))
            : 'article';

        abort_unless(in_array($type, array_diff(array_keys(Entry::getRegisteredTypes()), ['page']), true), 404);

        $entries = Entry::ofType($type)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->simplePaginate();

        return view('theme::entries.index', compact('entries', 'type'));
    }

    public function show(Request $request, string $slug)
    {
        // The `pages.show` route allows for forward slashes, so we do this bit manually (for all entry types, for now)
        // rather than rely on implicit model binding.
        $entry = Entry::where('slug', $slug) // Slugs are unique across all types; no need to take type along.
            ->whereIn('type', array_keys(Entry::getRegisteredTypes()))
            ->with('featured')
            ->with('tags')
            ->with(['comments' => function ($query) {
                $query->where('status', 'approved');
            }])
            ->firstOrFail();

        if ($entry->status !== 'published' || ($entry->visibility === 'private' && ! Auth::check())) {
            // "Hide" draft and "private" entries. ("Unlisted" entries can still be accessed directly.)
            abort(404);
        }

        return view('theme::entries.show', compact('entry'));
    }

    /**
     * Grab all published articles, ever. Will be shown per month.
     */
    public function articleArchive()
    {
        $entries = Entry::where('type', 'article')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->published()
            ->public()
            ->with('tags')
            ->get();

        return view('theme::entries.archive', compact('entries'));
    }

    public function stream()
    {
        $entries = Entry::whereIn('type', array_diff(array_keys(Entry::getRegisteredTypes()), ['page']))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->simplePaginate();

        return view('theme::entries.index', compact('entries'));
    }
}
