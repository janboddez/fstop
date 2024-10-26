<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Tag;

class TagController extends Controller
{
    public function show(Tag $tag)
    {
        $entries = $tag->entries()
            ->whereIn('type', array_diff(array_keys(Entry::getRegisteredTypes()), ['page']))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->simplePaginate();

        return view('theme::tags.show', compact('tag', 'entries'));
    }
}
