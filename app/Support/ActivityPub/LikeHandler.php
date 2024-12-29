<?php

namespace App\Support\ActivityPub;

use App\Models\Actor;
use App\Models\User;
use Illuminate\Http\Request;

class LikeHandler
{
    public function __construct(
        public Request $request,
        public User $user
    ) {}

    public function handle(): void
    {
        $object = $this->request->input('object');

        if (! $entry = url_to_entry((string) activitypub_object_to_id($object))) {
            return;
        }

        if (! $actor = filter_var($this->request->input('actor'), FILTER_VALIDATE_URL)) {
            return;
        }

        if (! $id = filter_var($this->request->input('id'), FILTER_VALIDATE_URL)) {
            return;
        }

        /**
         * @todo Store profiles for everyone we ever interact with, and user a pivot table for actual followers. And
         *       generate a "handle," should we not know their name.
         */

        // See if maybe we know this person.
        $actor = Actor::where('url', filter_var($actor, FILTER_SANITIZE_URL))
            ->first();

        $data = [
            'author' => strip_tags($actor->name ?? filter_var($actor, FILTER_SANITIZE_URL)),
            'author_url' => filter_var($actor, FILTER_SANITIZE_URL),
            'content' => __('… liked this!'),
            'status' => 'pending',
            'type' => 'like',
            'created_at' => now(), /** @todo Replace with parsed `$request->input('published')`, I guess. */
        ];

        // Look for an existing comment.
        $exists = $entry->comments()
            ->whereHas('meta', function ($query) use ($id) {
                $query->where('key', 'source')
                    ->where('value', json_encode((array) filter_var($id, FILTER_SANITIZE_URL)));
            })
            ->exists();

        if ($exists) {
            return;
        }

        $comment = $entry->comments()->create($data);

        // Saving the activity ID as our source, for duplicate detection and to be able to process "Undo" activities.
        $comment->meta()->create([
            'key' => 'source',
            'value' => (array) filter_var($id, FILTER_SANITIZE_URL),
        ]);
    }
}
