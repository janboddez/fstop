<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $tags = Tag::orderBy('name', 'asc')
            ->orderBy('id', 'asc')
            ->withCount('entries');

        if ($request->filled('s')) {
            // Search.
            $tags = $tags->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->input('s')}%")
                ->OrWhere('slug', 'like', "%{$request->input('s')}%");
            })
                ->paginate()
                ->appends(['s' => $request->input('s')]);
        } else {
            $tags = $tags->paginate();
        }

        return view('admin.tags.index', compact('tags'));
    }

    public function edit(Request $request, Tag $tag)
    {
        $tag->loadCount('entries');

        return view('admin.tags.edit', compact('tag'));
    }

    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'name' => 'required|max:250',
            'slug' => 'required|max:250',
        ]);

        $tag->update($validated);

        return back()
            ->withSuccess('Changes saved!');
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return back()
            ->withSuccess('Deleted!');
    }
}
