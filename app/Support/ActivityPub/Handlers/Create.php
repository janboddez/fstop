<?php

namespace App\Support\ActivityPub\Handlers;

use App\Models\Actor;
use App\Models\Comment;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use function App\Support\ActivityPub\fetch_profile;
use function App\Support\ActivityPub\object_to_id;

class Create
{
    public function __construct(
        protected Request $request,
        protected User $user
    ) {
    }

    /**
     * @todo Use `abort()` with "proper" error messages and status codes? (Something Mastodon *does not* do.)
     */
    public function handle(): void
    {
        $object = $this->request->input('object');

        if (empty($object['inReplyTo']) || ! Str::isUrl($object['inReplyTo'], ['http', 'https'])) {
            return;
        }

        if (empty($object['attributedTo']) || ! Str::isUrl($object['attributedTo'], ['http', 'https'])) {
            return;
        }

        if (! $id = object_to_id($object)) {
            return;
        }

        if (! empty($object['content'])) {
            /** @todo Properly "HTMLPurifier" this. */
            $content = strip_tags(
                $object['content'],
                '<a><b><blockquote><cite><i><em><li><ol><p><pre><strong><ul>'
            );
        }

        if (empty($content)) {
            return;
        }

        // Okay. Let's try to find the entry being replied to.
        if (! $parent = url_to_entry(filter_var($object['inReplyTo'], FILTER_SANITIZE_URL))) {
            // Could be a reply to a reply, still.
            $parent = Comment::whereHas('meta', function ($query) use ($object) {
                $query->where('key', 'source')
                    ->where('value', json_encode((array) filter_var($object['inReplyTo'], FILTER_SANITIZE_URL)));
            })
            ->without('comments')
            ->first();

            if (! $parent) {
                // Still no dice. Bail.
                return;
            }
        }

        // See if we know this person, and store them if we don't.
        $actor = Actor::firstOrCreate([
            'url' => filter_var($object['attributedTo'], FILTER_SANITIZE_URL),
        ]);

        if (
            $actor->wasRecentlyCreated &&
            $meta = fetch_profile(filter_var($actor->url, FILTER_SANITIZE_URL), $this->user, true)
        ) {
            add_meta($meta, $actor);
        }

        $actor->load('meta');

        $data = array_filter([
            'author' => strip_tags($actor->name ?? filter_var($object['attributedTo'], FILTER_SANITIZE_URL)),
            'author_url' => $actor->profile ?? filter_var($object['attributedTo'], FILTER_SANITIZE_URL),
            'content' => $content,
            'status' => 'pending',
            'type' => 'reply',
            'created_at' => now(), /** @todo Replace with parsed `$object['published']`, I guess. */
            'entry_id' => $parent instanceof Entry ? $parent->id : $parent->entry_id, // Always set `entry_id`.
            'parent_id' => $parent instanceof Comment ? $parent->id : null,
        ]);

        $exists = $parent->comments()
            ->whereHas('meta', function ($query) use ($id) {
                $query->where('key', 'source')
                    ->where('value', json_encode((array) $id));
            })
            ->exists();

        if ($exists) {
            return;
        }

        $comment = $parent->comments()->create($data);

        // Store object ID to be able to process updates and deletes.
        $comment->meta()->create([
            'key' => 'source', // We use `source` for webmentions, too. Thought it made sense to reuse that name.
            'value' => (array) $id,
        ]);

        if (! empty($object['url']) && Str::isUrl($object['url'], ['http', 'https'])) {
            $comment->meta()->create([
                'key' => 'activitypub_url',
                'value' => (array) filter_var($object['url'], FILTER_SANITIZE_URL),
            ]);
        }

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
