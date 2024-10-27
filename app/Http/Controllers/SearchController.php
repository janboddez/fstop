<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! $request->filled('s')) {
            // No need to look for anything if we're not looking for anything.
            return view('theme::entries.search', ['entries' => new Paginator([], 15)]);
        }

        $entries = Entry::whereIn('type', array_keys(Entry::getRegisteredTypes()))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->input('s')}%")
                    ->orWhere('content', 'like', "%{$request->input('s')}%")
                    ->orWhere('summary', 'like', "%{$request->input('s')}%")
                    ->orWhere('slug', 'like', "%{$request->input('s')}%");
            })
            ->simplePaginate()
            ->appends(['s' => $request->input('s')]);

        return view('theme::entries.search', compact('entries'));
    }
}
