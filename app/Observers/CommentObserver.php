<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\CommentPending;

class CommentObserver
{
    /**
     * Runs whenever a comment has first been created.
     */
    public function created(Comment $comment): void
    {
        if ($comment->status === 'pending') {
            /** @todo Implement user roles and whatnot. */
            User::find(1)
                ->notify(new CommentPending($comment));
        }
    }
}
