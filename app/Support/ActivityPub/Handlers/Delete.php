<?php

namespace App\Support\ActivityPub\Handlers;

use App\Models\Actor;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;

use function App\Support\ActivityPub\object_to_id;

class Delete
{
    public function __construct(
        protected Request $request,
        protected User $user
    ) {
    }

    public function handle(): void
    {
        if (! $id = object_to_id($this->request->input('object'))) {
            return;
        }

        if ($actor = Actor::where('url', $id)->first()) {
            // If an account we know about has been deleted.
            /** @todo Check for a 410 Gone? */
            $actor->meta()->delete();
            $actor->delete();
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
