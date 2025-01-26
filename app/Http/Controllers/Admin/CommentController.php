<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $comments = Comment::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->with('entry')
            ->without('comments'); // No need to nest children.

        if ($request->filled('s')) {
            $comments = $comments->where(function ($query) use ($request) {
                $query->where('author', 'like', "%{$request->input('s')}%")
                    ->OrWhere('content', 'like', "%{$request->input('s')}%");
            })
            ->paginate()
            ->appends(['s' => $request->input('s')]);
        } elseif ($request->filled('entry')) {
            $comments = $comments->where(function ($query) use ($request) {
                $query->where('entry_id', (int) $request->input('entry'));
            })
            ->paginate()
            ->appends(['entry' => $request->input('entry')]);
        } elseif ($request->input('pending')) {
            $comments = $comments->where('status', 'pending')
            ->paginate()
            ->appends(['pending' => true]);
        } else {
            $comments = $comments->where('status', 'approved')
            ->paginate();
        }

        $approved = Comment::where('status', 'approved')
            ->count();

        $pending = Comment::where('status', 'pending')
            ->count();

        return view('admin.comments.index', compact('comments', 'approved', 'pending'));
    }

    public function edit(Comment $comment): View
    {
        return view('admin.comments.edit', compact('comment'));
    }

    public function update(Request $request, Comment $comment): Response
    {
        $validated = $request->validate([
            'author' => 'required|max:250',
            'author_email' => 'nullable|email',
            'author_url' => 'nullable|url',
            'content' => 'required',
            'status' => 'required|in:pending,approved',
            'created_at' => 'required|date_format:Y-m-d',
            'meta_keys' => 'nullable|array',
            'meta_values' => 'array',
            'meta_keys.*' => 'nullable|string|max:250',
            'meta_values.*' => 'nullable|string',
        ]);

        $comment->update($validated);

        // Add (or update) any metadata.
        if (
            ! empty($validated['meta_keys']) &&
            ! empty($validated['meta_values']) &&
            count($validated['meta_keys']) === count($validated['meta_values'])
        ) {
            $meta = prepare_meta(array_combine($validated['meta_keys'], $validated['meta_values']), $comment);

            foreach ($meta as $key => $value) {
                $comment->meta()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        return redirect()->route('admin.comments.edit', compact('comment'))
            ->withSuccess(__('Changes saved!'));
    }

    public function destroy(Comment $comment): Response
    {
        if (url()->previous() === route('admin.comments.edit', $comment)) {
            $comment->meta()->delete();
            $comment->delete();

            return redirect()
                ->route('admin.comments.index')
                ->withSuccess(__('Deleted!'));
        }

        $comment->meta()->delete();
        $comment->delete();

        return back()
            ->withSuccess(__('Deleted!'));
    }

    public function approve(Comment $comment): Response
    {
        $comment->update(['status' => 'approved']);

        return back()
            ->withSuccess(__('Approved!'));
    }

    public function unapprove(Comment $comment): Response
    {
        $comment->update(['status' => 'pending']);

        return back()
            ->withSuccess(__('Unapproved!'));
    }

    public function bulkEdit(Request $request): Response
    {
        $action = $request->input('action');

        abort_unless(in_array($action, [/*'delete', */'approve', 'unapprove'], true), 400);

        switch ($action) {
            // case 'delete':
            //     Comment::whereIn('id', (array) $request->input('items'))
            //         ->delete();

            //     break;

            case 'approve':
                Comment::whereIn('id', (array) $request->input('items'))
                    ->update(['status' => 'approved']);

                break;

            case 'unapprove':
                Comment::whereIn('id', (array) $request->input('items'))
                    ->update(['status' => 'pending']);

                break;
        }

        return response()->json(['message' => __('Changes saved!')]);
    }
}
