<?php

namespace App\Support\ActivityPub\Handlers;

use App\Models\Comment;
use App\Models\Actor;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Http\Request;

class Create
{
    public function __construct(
        protected Request $request,
        protected User $user
    ) {}

    /**
     * @todo Use `abort()` with "proper" error messages and status codes? (Something Mastodon *does not* do.)
     */
    public function handle(): void
    {
        $object = $this->request->input('object');

        if (empty($object['inReplyTo']) || ! filter_var($object['inReplyTo'], FILTER_VALIDATE_URL)) {
            return;
        }

        if (empty($object['attributedTo']) || ! filter_var($object['attributedTo'], FILTER_VALIDATE_URL)) {
            return;
        }

        if (! $id = activitypub_object_to_id($object)) {
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

        if ($actor->wasRecentlyCreated) {
            // We should be getting these from cache.
            $meta = activitypub_fetch_profile($actor->url, $this->user);

            /** @todo Somehow do this in one go, using `saveMany()`. */
            foreach (prepare_meta(array_keys($meta), array_values($meta), $actor) as $key => $value) {
                $actor->meta()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

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

        if (! empty($object['url']) && filter_var($object['url'], FILTER_VALIDATE_URL)) {
            $comment->meta()->create([
                'key' => 'activitypub_url',
                'value' => (array) filter_var($object['url'], FILTER_SANITIZE_URL),
            ]);
        }
    }
}
