<?php

namespace App\Support\ActivityPub\Handlers;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;

use function App\Support\ActivityPub\object_to_id;

class Update
{
    public function __construct(
        protected Request $request,
        protected User $user
    ) {
    }

    /**
     * Currently, only reply updates are supported.
     *
     * @todo Implement profile/follower updates.
     */
    public function handle(): void
    {
        $object = $this->request->input('object');

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

        $comment = Comment::whereHas('meta', function ($query) use ($id) {
            $query->where('key', 'source')
                ->where('value', json_encode((array) $id));
        })
        ->first();

        if (! $comment) {
            return;
        }

        $comment->update([
            'content' => $content,
            'status' => 'pending',
            'updated_at' => now(), /** @todo Replace with parsed `$object['updated']`, I guess. */
        ]);
    }
}
