<?php

namespace App\Support\ActivityPub;

use App\Models\Comment;
use App\Models\Follower;
use App\Models\User;
use Illuminate\Http\Request;

class DeleteHandler
{
    public function __construct(
        protected Request $request,
        protected User $user
    ) {}

    public function handle(): void
    {
        if (! $id = activitypub_object_to_id($this->request->input('object'))) {
            return;
        }

        if ($follower = Follower::where('url', $id)->first()) {
            // If an account we know about has been deleted.
            /** @todo Check for a 410 Gone? */
            $follower->meta()->delete();
            $follower->delete();
        } else {
            // Could be for a reply instead.
            $comment = Comment::whereHas('meta', function ($query) use ($id) {
                $query->where('key', 'source')
                    ->where('value', json_encode((array) $id));
            })
            ->without('comments') // Prevent autoloading children.
            ->first();

            if ($comment) {
                $comment->meta()->delete();
                $comment->delete();
            }
        }
    }
}
