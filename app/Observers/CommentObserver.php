<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\CommentPending;
use Carbon\Carbon;

class CommentObserver
{
    public function saving(Comment $comment): void
    {
        // Ensure `created_at` is always set. There's no need to do this for, or otherwise modify `updated_at`, as
        // Laravel should take care of it automatically.
        if (
            preg_match('~\d{4}-\d{2}-\d{2}~', request()->input('created_at')) &&
            preg_match('~\d{2}:\d{2}:\d{2}~', request()->input('time'))
        ) {
            // If we were given a date and a time, use those.
            $createdAt = Carbon::parse(request()->input('created_at') . ' ' . request()->input('time'));
        } else {
            // Keep unchanged, or fall back to "now."
            $createdAt = $comment->created_at ?? now();
        }

        $comment->created_at = $createdAt;
    }

    /**
     * Runs whenever a comment was first created.
     */
    public function created(Comment $comment): void
    {
        /** @todo Implement user roles and whatnot rather use than this hardcoded ID. */
        User::find(1)
            ->notify(new CommentPending($comment));
    }
}
