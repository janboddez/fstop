<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Entry;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Events as Eventy;

class EntryController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');

        abort_unless(in_array($type, get_registered_entry_types(), true), 404);

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

        abort_unless(in_array($type, get_registered_entry_types(), true), 404);

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
            'type' => 'in:' . implode(',', get_registered_entry_types()),
            'featured' => 'nullable|url',
            'meta_keys' => 'nullable|array',
            'meta_values' => 'array',
            'meta_keys.*' => 'nullable|string|max:250',
            'meta_values.*' => 'nullable|string',
            'tags' => 'nullable|string',
        ]);

        // Parse in user ID.
        $validated['user_id'] = $request->user()->id;

        // Convert `featured`, which isn't actually a fillable property, to `attachment_id`.
        if (! empty($validated['featured'])) {
            $relativePath = Str::replaceStart(Storage::disk('public')->url(''), '', $validated['featured']);

            $featured = Attachment::where('path', $relativePath)
                ->first();

            $validated['attachment_id'] = $featured->id;
        }

        $entry = Entry::create($validated);

        // Sync tags, if any.
        if (! empty($validated['tags'])) {
            $tags = explode(',', trim($validated['tags'], ','));
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

        // Add any metadata.
        if (
            ! empty($validated['meta_keys']) &&
            ! empty($validated['meta_values']) &&
            count($validated['meta_keys']) === count($validated['meta_values'])
        ) {
            foreach (prepare_meta($validated['meta_keys'], $validated['meta_values'], $entry) as $key => $value) {
                $entry->meta()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        /** @todo Use an actual Laravel event. */
        Eventy::action('entries:saved', $entry);

        return redirect()->route('admin.entries.edit', compact('entry'))
            ->withSuccess(__('Created!'));
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
            'type' => 'in:' . implode(',', get_registered_entry_types()),
            'featured' => 'nullable|url',
            'meta_keys' => 'nullable|array',
            'meta_values' => 'array',
            'meta_keys.*' => 'nullable|string|max:250',
            'meta_values.*' => 'nullable|string',
            'tags' => 'nullable|string',
        ]);

        if (! empty($validated['featured'])) {
            $relativePath = Str::replaceStart(Storage::disk('public')->url(''), '', $validated['featured']);

            $featured = Attachment::where('path', $relativePath)
                ->first();

            $validated['attachment_id'] = $featured->id;
        }

        $entry->update($validated);

        if (! empty($validated['tags'])) {
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

        if (
            ! empty($validated['meta_keys']) &&
            ! empty($validated['meta_values']) &&
            count($validated['meta_keys']) === count($validated['meta_values'])
        ) {
            foreach (prepare_meta($validated['meta_keys'], $validated['meta_values'], $entry) as $key => $value) {
                $entry->meta()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        Eventy::action('entries:saved', $entry);

        return back()
            ->withSuccess(__('Changes saved!'));
    }

    public function destroy(Entry $entry)
    {
        $type = $entry->type;

        if (session()->previousUrl() === route('admin.entries.edit', $entry)) {
            if ($entry->trashed()) {
                $entry->comments()->delete(); /** @todo Cascade on delete? */
                $entry->meta()->delete();
                $entry->forceDelete();
                $entry->tags()->detach();
            } else {
                $entry->delete();
            }

            return redirect()
                ->route('admin.entries.index', ['type' => $type])
                ->withSuccess(__('Deleted!'));
        }

        if ($entry->trashed()) {
            $entry->comments()->delete(); /** @todo Cascade on delete? */
            $entry->meta()->delete();
            $entry->forceDelete();
            $entry->tags()->detach();
        } else {
            $entry->delete();
        }

        return back()
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
