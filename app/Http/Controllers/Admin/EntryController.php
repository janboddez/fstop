<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Entry;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EntryController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');

        abort_unless(in_array($type, array_keys(Entry::getRegisteredTypes()), true), 404);

        $entries = Entry::ofType($type)
            ->with('tags')
            ->withCount(['comments' => function ($query) {
                $query->where('status', 'approved');
            }]);

        if ($type === 'page') {
            $entries = $entries->orderBy('slug', 'asc');
        } else {
            $entries = $entries->orderBy('created_at', 'desc');
        }

        $entries = $entries->orderBy('id', 'desc'); // Prevent pagination issues by *also* sorting by ID.

        if ($request->filled('s')) {
            // Search.
            $entries = $entries->withTrashed()
                ->where(function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->input('s')}%")
                        ->OrWhere('content', 'like', "%{$request->input('s')}%");
                })
                ->paginate()
                ->appends([
                    'type' => $type,
                    's' => $request->input('s'),
                ]);
        } elseif ($request->input('trashed')) {
            // Trash.
            $entries = $entries->onlyTrashed()
                ->paginate()
                ->appends([
                    'type' => $type,
                    'trashed' => 1,
                ]);
        } elseif ($request->input('draft')) {
            // Draft.
            $entries = $entries->where('status', 'draft')
                ->paginate()
                ->appends([
                    'type' => $type,
                    'draft' => 1,
                ]);
        } else {
            // Published.
            $entries = $entries->published()
                ->paginate()
                ->appends(['type' => $type]);
        }

        // These are the counts displayed above the table and will always lead to *one* duplicate query, but I can't be
        // bothered to optimize.
        $published = Entry::ofType($type)
            ->published()
            ->count();

        $draft = Entry::ofType($type)
            ->where('status', 'draft')
            ->count();

        $trashed = Entry::ofType($type)
            ->onlyTrashed()
            ->count();

        return view('admin.entries.index', compact('entries', 'type', 'published', 'draft', 'trashed'));
    }

    public function create(Request $request)
    {
        $type = $request->input('type');

        abort_unless(in_array($type, array_keys(Entry::getRegisteredTypes()), true), 404);

        return view('admin.entries.edit', compact('type'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|max:250',
            'slug' => 'nullable|max:250',
            'content' => 'required|string',
            'summary' => 'nullable|string',
            'created_at' => 'nullable|date_format:Y-m-d',
            'status' => 'in:draft,published',
            'visibility' => 'in:public,unlisted,private',
            'type' => 'in:' . implode(',', array_keys(Entry::getRegisteredTypes())),
            'featured' => 'nullable|url',
            'meta_keys' => 'nullable|array',
            'meta_values' => 'nullable|array',
            'meta_keys.*' => 'max:250',
            'meta_values.*' => 'nullable|string',
        ]);

        // Parse in user ID.
        $validated['user_id'] = $request->user()->id;

        // Convert `featured`, which isn't actually a fillable property, to `attachment_id`.
        if (! empty($validated['featured'])) {
            $relativePath = preg_replace(
                '~^' . rtrim(Storage::disk('public')->url(''), '/') . '/~',
                '',
                $validated['featured']
            );

            $featured = Attachment::where('path', $relativePath)
                ->first();

            $validated['attachment_id'] = $featured->id;
        }

        // Add any metadata.
        if (
            ! empty($validated['meta_keys']) &&
            ! empty($validated['meta_values']) &&
            count($validated['meta_keys']) === count($validated['meta_values'])
        ) {
            $validated['meta'] = Entry::prepareMeta($validated['meta_keys'], $validated['meta_values']);
        }

        $entry = Entry::create($validated);

        // Sync tags, if any.
        if ($request->filled('tags')) {
            $tags = explode(',', trim($request->input('tags'), ','));
            $tagIds = [];

            foreach ($tags as $name) {
                $name = trim($name);

                $tag = Tag::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name]
                );

                $tagIds[] = $tag->id;
            }

            $entry->tags()->sync($tagIds);
        }

        return redirect()->route('admin.entries.edit', compact('entry'))
            ->with('success', __('Created!'));
    }

    public function edit(Request $request, Entry $entry)
    {
        $type = $entry->type;

        $entry->load('featured');
        $entry->load('tags');

        return view('admin.entries.edit', compact('entry', 'type'));
    }

    public function update(Request $request, Entry $entry)
    {
        $validated = $request->validate([
            'name' => 'nullable|max:250',
            'slug' => 'nullable|max:250',
            'content' => 'required|string',
            'summary' => 'nullable|string',
            'created_at' => 'nullable|date_format:Y-m-d',
            'status' => 'in:draft,published',
            'visibility' => 'in:public,unlisted,private',
            'type' => 'in:' . implode(',', array_keys(Entry::getRegisteredTypes())),
            'featured' => 'nullable|url',
            'meta_keys' => 'nullable|array',
            'meta_values' => 'nullable|array',
            'meta_keys.*' => 'max:250',
            'meta_values.*' => 'nullable|string',
        ]);

        if (! empty($validated['featured'])) {
            $relativePath = preg_replace(
                '~^' . rtrim(Storage::disk('public')->url(''), '/') . '/~',
                '',
                $validated['featured']
            );

            $featured = Attachment::where('path', $relativePath)
                ->first();

            $validated['attachment_id'] = $featured->id;
        }

        if (
            ! empty($validated['meta_keys']) &&
            ! empty($validated['meta_values']) &&
            count($validated['meta_keys']) === count($validated['meta_values'])
        ) {
            $validated['meta'] = array_merge(
                $entry->meta ?? [],
                Entry::prepareMeta($validated['meta_keys'], $validated['meta_values'])
            );
        }

        $entry->update($validated);

        if ($request->filled('tags')) {
            $tags = explode(',', trim($request->input('tags'), ','));
            $tagIds = [];

            foreach ($tags as $name) {
                $name = trim($name);

                $tag = Tag::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name]
                );

                $tagIds[] = $tag->id;
            }

            $entry->tags()->sync($tagIds);
        }

        return back()
            ->withSuccess(__('Changes saved!'));
    }

    public function destroy(Entry $entry)
    {
        $type = $entry->type;

        if ($entry->trashed()) {
            $entry->forceDelete();
        } else {
            $entry->comments()->delete(); // @todo Soft-delete comments.
            $entry->tags()->detach();
            $entry->delete();
        }

        return redirect()
            ->route('admin.entries.index', ['type' => $type])
            ->withSuccess(__('Deleted!'));
    }

    public function restore(Entry $entry)
    {
        $entry->restore();

        return back()
            ->withSuccess(__('Restored! :edit_link', [
                'edit_link' => '<a href="' . route('admin.entries.edit', $entry) . '">' .
                    __('Edit :type.', ['type' => $entry->type]) . '</a>',
            ]));
    }
}
