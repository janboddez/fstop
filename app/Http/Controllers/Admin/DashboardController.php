<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Entry;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $articles = Entry::ofType('article')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->published()
            ->limit(5)
            ->get();

        $drafts = Entry::ofType('article')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->where('status', 'draft')
            ->limit(5)
            ->get();

        $comments = Comment::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->with('entry')
            ->limit(5)
            ->get();

        $pending = Comment::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->where('status', 'pending')
            ->with('entry')
            ->limit(5)
            ->get();

        $articles_count = Entry::ofType('article')
            ->published()
            ->count();

        $pages_count = Entry::ofType('page')
            ->published()
            ->count();

        $comments_count = Comment::where('status', 'pending')
            ->count();

        // Not the 10 most recently sent webmentions, but the 10 most recent entries for which sending a webmention was
        // attempted.
        $webmentions = Entry::whereHas('meta', function ($query) {
            $query->where('key', 'webmention')
                ->where(function ($query) {
                    /** @todo Now that meta got their own table, shouldn't this just be multiple rows? */
                    $query->whereRaw('json_extract(`value`, "$.*.result") = "[true]"')
                        ->orWhereRaw('json_extract(`value`, "$.*.result") is null'); // 'Cause legacy?
                });
        })
        ->orderBy('created_at', 'desc')
        ->orderBy('id', 'desc')
        ->limit(10)
        ->get();

        return view('admin.dashboard', compact(
            'articles',
            'drafts',
            'comments',
            'pending',
            'webmentions',
            'articles_count',
            'pages_count',
            'comments_count'
        ));
    }
}
