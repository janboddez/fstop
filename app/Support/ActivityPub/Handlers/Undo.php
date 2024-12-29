<?php

namespace App\Support\ActivityPub\Handlers;

use App\Models\Actor;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;

class Undo
{
    public function __construct(
        public Request $request,
        public User $user
    ) {}

    public function handle(): void
    {
        $object = $this->request->input('object');

        if (empty($object['type'])) {
            return;
        }

        if ($object['type'] === 'Like') {
            if (! $id = activitypub_object_to_id($object)) {
                return;
            }

            // `$id` now equals the activity ID we (hopefully) stored before.
            $comment = Comment::whereHas('meta', function ($query) use ($id) {
                $query->where('key', 'source')
                    ->where('value', json_encode((array) $id));
            })
            ->without('comments')
            ->first();

            if ($comment) {
                $comment->meta()->delete();
                $comment->delete();
            }
        }

        if ($object['type'] === 'Follow') {
            if (empty($object['actor']) || ! filter_var($object['actor'], FILTER_VALIDATE_URL)) {
                // No or invalid actor.
                return;
            }

            /** @todo How do we verify our URLs are up to date? */
            $actorId = Actor::where('url', filter_var($object['actor'], FILTER_SANITIZE_URL))
                ->value('id');

            $this->user->followers()->detach($actorId);
        }
    }
}
