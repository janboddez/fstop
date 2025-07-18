<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntryRequest;
use App\Models\Attachment;
use App\Models\Entry;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use TorMorten\Eventy\Facades\Events as Eventy;

class EntryController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->input('type');

        abort_unless(in_array($type, get_registered_entry_types(), true), 400);

        $entries = Entry::ofType($type)
            ->with('tags')
            ->withCount('comments');

        if ($type === 'page') {
            $entries = $entries->orderBy('slug', 'asc');
        } else {
            // This puts notes that were never published at the top (in trash), rather than last. We could also sort on
            // status. (Maybe we should?)
            $entries = $entries
                ->orderBy('status', 'asc')
                ->orderByRaw('CASE WHEN published IS NULL THEN 0 ELSE 1 END ASC')
                ->orderBy('published', 'desc');
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

    public function create(Request $request): View
    {
        $type = $request->input('type');

        abort_unless(in_array($type, get_registered_entry_types(), true), 404);

        return view('admin.entries.edit', compact('type'));
    }

    public function store(StoreEntryRequest $request): Response
    {
        $validated = $request->validated();

        // Parse in user ID.
        $validated['user_id'] = $request->user()->id;

        // Convert `featured`, which isn't actually a fillable property, to `attachment_id`.
        if (! empty($validated['featured'])) {
            $relativePath = Str::replaceStart(Storage::disk('public')->url(''), '', $validated['featured']);

            $featured = Attachment::where('path', $relativePath)
                ->first();

            if ($featured) {
                $validated['attachment_id'] = $featured->id;
            }
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
            add_meta(array_combine($validated['meta_keys'], $validated['meta_values']), $entry);
        }

        Eventy::action('entries:saved', $entry, null);

        return redirect()->route('admin.entries.edit', compact('entry'))
            ->withSuccess(__('Created!'));
    }

    public function edit(Request $request, Entry $entry): View
    {
        $type = $entry->type;

        $entry->load('featured');
        $entry->load('tags');
        $entry->loadCount('comments');

        return view('admin.entries.edit', compact('entry', 'type'));
    }

    public function update(StoreEntryRequest $request, Entry $entry): Response
    {
        $validated = $request->validated();
        $previousStatus = $entry->getOriginal('status');

        if (! empty($validated['featured'])) {
            $relativePath = Str::replaceStart(Storage::disk('public')->url(''), '', $validated['featured']);

            $featured = Attachment::where('path', $relativePath)
                ->first();

            if ($featured) {
                $validated['attachment_id'] = $featured->id;
            }
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
            $meta = prepare_meta(array_combine($validated['meta_keys'], $validated['meta_values']), $entry);

            foreach ($meta as $key => $value) {
                $entry->meta()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        Eventy::action('entries:saved', $entry, $previousStatus);

        return back()
            ->withSuccess(__('Changes saved!'));
    }

    public function destroy(Entry $entry): Response
    {
        $type = $entry->type;

        if (app('redirect')->getUrlGenerator()->previous() === route('admin.entries.edit', $entry)) {
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
            $entry->tags()->detach();
            $entry->forceDelete();
        } else {
            $entry->delete();
        }

        return back()
            ->withSuccess(__('Deleted!'));
    }

    public function bulkEdit(Request $request): Response
    {
        $action = $request->input('action');

        abort_unless(in_array($action, ['delete', 'publish', 'unpublish'], true), 400);

        switch ($action) {
            case 'delete':
                // Entry::whereIn('id', (array) $request->input('items'))
                //     ->whereNull('deleted_at')
                //     ->delete();

                // Entry::whereIn('id', (array) $request->input('items'))
                //     ->onlyTrashed()
                //     ->forceDelete();

                $entries = Entry::whereIn('id', (array) $request->input('items'))
                    ->withTrashed()
                    ->get();

                foreach ($entries as $entry) {
                    if ($entry->trashed()) {
                        $entry->comments()->delete(); /** @todo Cascade on delete? */
                        $entry->meta()->delete();
                        $entry->tags()->detach();
                        $entry->forceDelete();
                    } else {
                        $entry->delete();
                    }
                }

                break;

            case 'publish':
                Entry::whereIn('id', (array) $request->input('items'))
                    ->update(['status' => 'published']);

                break;

            case 'unpublish':
                Entry::whereIn('id', (array) $request->input('items'))
                    ->update(['status' => 'draft']);

                break;
        }

        return response()->json(['message' => __('Changes saved!')]);
    }

    public function emptyTrash(Request $request): Response
    {
        $type = $request->input('type');

        abort_unless(in_array($type, get_registered_entry_types(), true), 400);

        $trashed = Entry::ofType($type)
            ->onlyTrashed()
            ->get();

        /** @todo Should probably look at cascade on delete as this isn't very efficient ... */
        foreach ($trashed as $entry) {
            $entry->comments()->delete();
            $entry->meta()->delete();
            $entry->forceDelete();
        }

        return back()
            ->withSuccess(__('Trash emptied!'));
    }

    public function restore(Entry $entry): Response
    {
        $entry->restore();

        return back()
            ->withSuccess(__('Restored! :edit_link', [
                'edit_link' => '<a href="' . route('admin.entries.edit', $entry) . '">' .
                    __('Edit :type.', ['type' => $entry->type]) . '</a>',
            ]));
    }
}
