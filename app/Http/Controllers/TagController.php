<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Contracts\View\View;

class TagController extends Controller
{
    public function show(Tag $tag): View
    {
        $entries = $tag->entries()
            ->whereIn('type', get_registered_entry_types('slug', 'page'))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->published()
            ->public()
            ->with('featured')
            ->with('tags')
            ->with('user')
            ->simplePaginate();

        return view('theme::tags.show', compact('tag', 'entries'));
    }
}
