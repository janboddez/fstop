<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $comments = Comment::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

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
            ->appends(['s' => $request->input('entry')]);
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

    public function edit(Comment $comment)
    {
        return view('admin.comments.edit', compact('comment'));
    }

    public function update(Request $request, Comment $comment)
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

        // Add any metadata.
        if (
            ! empty($validated['meta_keys']) &&
            ! empty($validated['meta_values']) &&
            count($validated['meta_keys']) === count($validated['meta_values'])
        ) {
            foreach (prepare_meta($validated['meta_keys'], $validated['meta_values'], $comment) as $key => $value) {
                $comment->meta()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        return redirect()->route('admin.comments.edit', compact('comment'))
            ->withSuccess(__('Changes saved!'));
    }

    public function destroy(Comment $comment)
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

    public function approve(Comment $comment)
    {
        $comment->update(['status' => 'approved']);

        return back()
            ->withSuccess(__('Approved!'));
    }

    public function unapprove(Comment $comment)
    {
        $comment->update(['status' => 'pending']);

        return back()
            ->withSuccess(__('Unapproved!'));
    }
}
