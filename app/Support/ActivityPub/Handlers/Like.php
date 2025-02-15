<?php

namespace App\Support\ActivityPub\Handlers;

use App\Models\Actor;
use App\Models\User;
use Illuminate\Http\Request;

use function App\Support\ActivityPub\fetch_profile;
use function App\Support\ActivityPub\object_to_id;

class Like
{
    public function __construct(
        public Request $request,
        public User $user
    ) {
    }

    public function handle(): void
    {
        $object = $this->request->input('object');

        if (! $entry = url_to_entry((string) object_to_id($object))) {
            return;
        }

        if (! $actorUrl = filter_var($this->request->input('actor'), FILTER_VALIDATE_URL)) {
            return;
        }

        if (! $id = filter_var($this->request->input('id'), FILTER_VALIDATE_URL)) {
            return;
        }

        /**
         * @todo Store profiles for everyone we ever interact with, and user a pivot table for actual followers. And
         *       generate a "handle," should we not know their name.
         */

        // See if we know this person, and store them if we don't.
        $actor = Actor::firstOrCreate([
            'url' => filter_var($actorUrl, FILTER_SANITIZE_URL),
        ]);

        if (
            $actor->wasRecentlyCreated &&
            $meta = fetch_profile(filter_var($actor->url, FILTER_SANITIZE_URL), $this->user, true)
        ) {
            add_meta($meta, $actor);
        }

        $data = [
            'author' => strip_tags($actor->name ?? filter_var($actorUrl, FILTER_SANITIZE_URL)),
            'author_url' => $actor->profile ?? filter_var($actorUrl, FILTER_SANITIZE_URL),
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

        /**
         * @todo Look into alternatives, like, if we were to make this a pure "Fediverse app," we could simply add an
         *       `actor_id` to replies (and use that to fetch names and avatars of commenters).
         */
        if (! empty($actor->avatar)) {
            $comment->meta()->create([
                'key' => 'avatar',
                'value' => (array) filter_var($actor->avatar, FILTER_SANITIZE_URL),
            ]);
        }
    }
}
