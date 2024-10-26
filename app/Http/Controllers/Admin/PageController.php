<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $pages = Page::orderBy('slug', 'asc')
            ->orderBy('id', 'desc');

        if ($request->filled('s')) {
            $pages = $pages->withTrashed()
                ->where(function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->input('s')}%")
                        ->OrWhere('content', 'like', "%{$request->input('s')}%");
                })
                ->paginate()
                ->appends(['s' => $request->input('s')]);
        } elseif ($request->input('trashed')) {
            $pages = $pages->onlyTrashed()
                ->paginate()
                ->appends(['trashed' => true]);
        } elseif ($request->input('draft')) {
            $pages = $pages->draft()
                ->paginate()
                ->appends(['draft' => true]);
        } else {
            $pages = $pages->published()
                ->paginate();
        }

        $published = Page::published()
        ->count();

        $draft = Page::where('status', 'draft')
            ->count();

        $trashed = Page::onlyTrashed()
            ->count();

        return view('admin.pages.index', compact('pages', 'published', 'draft', 'trashed'));
    }

    public function create()
    {
        return view('admin.pages.edit');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:250',
            'content' => 'required',
            'status' => 'in:draft,published',
            'visibility' => 'in:public,unlisted,private',
            'created_at' => 'nullable|date_format:Y-m-d',
        ]);

        $validated = array_merge($validated, [
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
            'user_id' => $request->user()->id,
        ]);

        $page = Page::create($validated);

        return redirect()->route('admin.pages.edit', compact('page'));
    }

    public function edit(Request $request, Page $page)
    {
        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'name' => 'required|max:250',
            'content' => 'required',
            'status' => 'in:draft,published',
            'visibility' => 'in:public,unlisted,private',
            'created_at' => 'nullable|date_format:Y-m-d',
        ]);

        $page->update($validated);

        return back()
            ->with('success', __('Changes saved!'));
    }

    public function destroy(Page $page)
    {
        if ($page->trashed()) {
            $page->forceDelete();
        } else {
            $page->delete();
        }

        return back()
            ->with('success', __('Deleted!'));
    }

    public function restore(Page $page)
    {
        $page->restore();

        return back()
            ->with('success', __('Restored!'));
    }
}
