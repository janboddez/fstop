<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

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
        } elseif ($request->input('pending')) {
            $comments = $comments->where('status', 'pending')
            ->paginate();
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
        ]);

        $comment->update($validated);

        return redirect()->route('admin.comments.edit', compact('comment'))
            ->with('success', __('Changes saved!'));
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();

        return back()
            ->with('success', __('Deleted!'));
    }

    public function approve(Comment $comment)
    {
        $comment->update(['status' => 'approved']);

        return back()
            ->with('success', __('Approved!'));
    }

    public function unapprove(Comment $comment)
    {
        $comment->update(['status' => 'pending']);

        return back()
            ->with('success', __('Unapproved!'));
    }
}
