<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EntryController extends Controller
{
    public function index(Request $request): View
    {
        // Determine the entry type from the URL.
        $type = ! empty($request->segment(1))
            ? Str::singular($request->segment(1))
            : 'article'; // Default.

        // If the entry type is not currently registered, return a 404.
        abort_unless(in_array($type, get_registered_entry_types('slug', 'page'), true), 404);

        $entries = Entry::ofType($type)
            ->orderBy('published', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->with('user')
            ->simplePaginate();

        return view('theme::entries.index', compact('entries', 'type'));
    }

    public function show(Request $request, string $slug): Response|View
    {
        // The `pages.show` route allows for forward slashes, so we do this bit manually (for all entry types, for now)
        // rather than rely on implicit model binding.
        $entry = Entry::whereIn('type', get_registered_entry_types()) // Include only registered entry types.
            ->with('featured')
            ->with('tags')
            ->with('user')
            ->with(['comments' => function ($query) {
                $query->whereNotIn('type', ['like', 'repost', 'bookmark'])
                    ->whereNull('parent_id')
                    ->approved();
            }])
            ->with(['likes' => function ($query) {
                $query->approved();
            }])
            ->with(['reposts' => function ($query) {
                $query->approved();
            }])
            ->with(['bookmarks' => function ($query) {
                $query->approved();
            }]);

        $entry = $entry->where('slug', $slug)
            ->firstOrFail();

        if (($entry->status !== 'published' || $entry->visibility === 'private') && ! Auth::check()) {
            // "Hide" draft and "private" entries. ("Unlisted" entries can still be accessed directly.)
            abort(404);
        }

        if (
            in_array($entry->type, ['article', 'note', 'like'], true) &&
            request()->expectsJson() &&
            null === $entry->meta->firstWhere('key', '_like_of') &&
            null === $entry->meta->firstWhere('key', '_repost_of')
        ) {
            // "Content negotiation."
            return response()->json(
                $entry->serialize(),
                200,
                ['Content-Type' => 'application/activity+json']
            );
        }

        return view('theme::entries.show', compact('entry'));
    }

    public function stream(): View
    {
        // Include only currently registered entry types, and exclude pages.
        $types = get_registered_entry_types('slug', 'page');

        $entries = Entry::whereIn('type', $types)
            ->orderBy('published', 'desc')
            ->orderBy('id', 'desc')
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->with('user')
            ->simplePaginate();

        return view('theme::entries.index', compact('entries'));
    }

    /**
     * Grab all published articles, ever. Will be shown per month.
     */
    public function articleArchive(): View
    {
        $entries = Entry::ofType('article')
            ->orderBy('published', 'desc')
            ->orderBy('id', 'desc')
            ->published()
            ->public()
            ->get();

        return view('theme::entries.archive', compact('entries'));
    }
}
