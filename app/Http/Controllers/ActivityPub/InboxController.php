<?php

namespace App\Http\Controllers\ActivityPub;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Follower;
use App\Models\User;
use App\Support\HttpSignature;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InboxController extends Controller
{
    public function inbox(User $user = null, Request $request): Response
    {
        // We're going to want a valid signature header.
        abort_unless(is_string($signature = $request->header('signature')), 400);
        $signatureData = HttpSignature::parseSignatureHeader($signature);
        abort_if(isset($signatureData['error']), 400);

        // Dirty catch-all for our single-user blog.
        if (empty($user->id)) {
            $user = User::find(1);
        }

        // Make it slightly easier to work with the activity's object, if any.
        $object = $request->input('object'); // Could still be `null`; that's okay.

        /**
         * Delete.
         */
        if (
            $request->input('type') === 'Delete' &&
            filter_var($object, FILTER_VALIDATE_URL)
        ) {
            // We only care about profiles we actually know about.
            if ($follower = Follower::where('url', filter_var($object, FILTER_SANITIZE_URL))->first()) {
                // If the delete concerns a profile known to us, we're going to have to verify the request. If the
                // remote actor really is deleted, we won't be able to retrieve a public key and will have to go by the
                // one we've got.
                if (empty($follower->public_key)) {
                    /** @todo This is where we would fetch the profile and look for a 410 Gone? */
                    Log::error('[ActivityPub] Could not find public key');
                    abort(401);
                }

                list($verified, $headers) = HttpSignature::verify($follower->public_key, $signatureData, $request);

                /** @todo Alternatively, we could try and fetch the profile and look for a 410 Gone. */
                abort_unless($verified === 1, 403);

                // Actually delete.
                $follower->meta()->delete();
                $follower->delete();
            } else {
                $comment = Comment::whereHas('meta', function ($query) use ($object) {
                    $query->where('key', 'source')
                        ->where('value', json_encode((array) filter_var($object, FILTER_SANITIZE_URL)));
                })
                ->without('comments')
                ->first();

                abort_unless($comment && is_string($request->input('actor')), 400);

                // We'll want to verify the signature.
                $actorUrl = filter_var($request->input('actor'), FILTER_SANITIZE_URL);
                /** @todo Store these profiles someplace. Or cache them for like a month or so. */
                $actor = activitypub_fetch_profile($actorUrl, $user);

                if (! empty($actor['public_key'])) {
                    list($verified, $headers) = HttpSignature::verify(
                        $actor['public_key'],
                        $signatureData,
                        $request
                    );
                }

                abort_unless(isset($verified) && $verified === 1, 403);

                /** @todo Delete children, too. "Problem" is, how do we ensure their meta gets deleted, too? */
                $comment->meta()->delete();
                $comment->delete();
            }

            // Stop right here.
            return response()->json(new \stdClass(), 202);
        }

        Log::info($request->getRequestUri());
        Log::info($request->all());

        // See if the used `keyId` somehow belongs to one of our followers.
        $follower = Follower::whereHas('meta', function ($query) use ($signatureData) {
            $query->where('key', 'key_id')
                ->where('value', json_encode((array) $signatureData['keyId']));
        })
        ->first();

        if (! empty($follower->public_key)) {
            $publicKey = $follower->public_key;
        } else {
            // Try and fetch the remote public key.
            $data = activitypub_fetch_profile($signatureData['keyId'], $user);
            $publicKey = $data['public_key'] ?? null;
        }

        if (empty($publicKey)) {
            Log::error('[ActivityPub] Could not find public key');
            abort(401);
        }

        list($verified, $headers) = HttpSignature::verify($publicKey, $signatureData, $request);

        if ($verified !== 1 && ! empty($follower->public_key)) {
            // The key we stored previously may be outdated.
            $meta = activitypub_fetch_profile($signatureData['keyId'], $user);

            if (! empty($meta['public_key'])) {
                // Update meta.
                foreach (prepare_meta(array_keys($meta), array_values($meta), $follower) as $key => $value) {
                    $follower->meta()->updateOrCreate(
                        ['key' => $key],
                        ['value' => $value]
                    );
                }

                // Try again.
                list($verified, $headers) = HttpSignature::verify($meta['public_key'], $signatureData, $request);
            }
        }

        abort_unless($verified === 1, 403);

        /**
         * @todo Move `followers` to `profiles`, or `actors`, and implement a pivot table to link them to users. And
         *       don't delete them if they're still following anyone else.
         */

        /**
         * Unfollow.
         */
        if (
            $request->input('type') === 'Undo' &&
            ! empty($object['type']) &&
            $object['type'] === 'Follow' &&
            ! empty($object['actor']) &&
            filter_var($object['actor'], FILTER_VALIDATE_URL)
        ) {
            $follower = $user->followers()
                ->where('url', filter_var($object['actor'], FILTER_SANITIZE_URL))
                ->first();

            if ($follower) {
                $follower->meta()->delete();
                $follower->delete();
            }

            return response()->json(new \stdClass(), 202);
        }

        /**
         * Follow.
         */
        if (
            $request->input('type') === 'Follow' &&
            ($actor = filter_var($request->input('actor'), FILTER_SANITIZE_URL))
        ) {
            $follower = $user->followers()
                ->where('url', $actor)
                ->first();

            if (! $follower) {
                // Before we add a new follower, we should fetch their ActivityPub actor profile.
                $meta = activitypub_fetch_profile($actor, $user);

                if ((empty($meta['inbox']) && empty($meta['sharedInbox']))) {
                    Log::warning("[ActivityPub] Something went wrong fetching the profile at $actor");

                    return response()->json(new \stdClass(), 500);
                }

                $follower = $user->followers()
                    ->create(['url' => $actor]);

                /** @todo Store all of these at once. */
                foreach (prepare_meta(array_keys($meta), array_values($meta), $follower) as $key => $value) {
                    $follower->meta()->updateOrCreate(
                        ['key' => $key],
                        ['value' => $value]
                    );
                }
            }

            $response = $this->sendAccept($request, $follower, $user);

            if (! $response->successful()) {
                Log::warning("[ActivityPub] Something went wrong accepting a follow request by $actor");
                Log::debug($response);
            }
        }

        /**
         * Like.
         */
        if (
            $request->input('type') === 'Like' &&
            ($actor = filter_var($request->input('actor'), FILTER_VALIDATE_URL)) &&
            ($id = filter_var($request->input('id'), FILTER_VALIDATE_URL))
        ) {
            if (! empty($object['id']) && filter_var($object['id'], FILTER_VALIDATE_URL)) {
                $entry = url_to_entry(filter_var($object['id'], FILTER_SANITIZE_URL));
            } elseif (filter_var($object, FILTER_VALIDATE_URL)) {
                $entry = url_to_entry(filter_var($object, FILTER_SANITIZE_URL));
            }

            if (empty($entry)) {
                // Quit.
                return response()->json(new \stdClass(), 202);
            }

            if ($follower = Follower::where('url', filter_var($actor, FILTER_SANITIZE_URL))->first()) {
                // If we happen to know this person.
                $author = $follower->name;
            }
            /** @todo Store profiles for everyone we ever interact with, and user a pivot table for actual followers. */

            $data = [
                'author' => strip_tags($author ?? filter_var($actor, FILTER_SANITIZE_URL)),
                'author_url' => filter_var($actor, FILTER_SANITIZE_URL),
                'content' => __('… liked this!'),
                'status' => 'pending',
                'type' => 'like',
                'created_at' => now(), /** @todo Replace with parsed `$request->input('published')`, I guess. */
            ];

            // Look for an existing comment.
            $comment = $entry->comments()
                ->whereHas('meta', function ($query) use ($id) {
                    $query->where('key', 'source')
                        ->where('value', json_encode((array) $id));
                })
                ->first();

            if (! empty($comment)) {
                Log::debug("[ActivityPub] Looks like a duplicate ({$id})");

                // Quit.
                return response()->json(new \stdClass(), 202);
            } else {
                Log::debug("[ActivityPub] Creating new like of {$entry->permalink} for {$id}");
                $comment = $entry->comments()->create($data);
            }

            $comment->meta()->updateOrCreate(
                ['key' => 'source'], // We use `source` for webmentions, too. Though it made sense to reuse that name.
                ['value' => (array) $id],
            );

            // All done.
            return response()->json(new \stdClass(), 202);
        }

        /**
         * Undo like.
         */
        if (
            $request->input('type') === 'Undo' &&
            ! empty($object['type']) && $object['type'] === 'Like' &&
            ! empty($object['id']) && ($id = filter_var($object['id'], FILTER_SANITIZE_URL))
        ) {
            // Look for an existing comment.
            $comment = Comment::whereHas('meta', function ($query) use ($id) {
                $query->where('key', 'source')
                    ->where('value', json_encode((array) $id));
            })
            ->first();

            if (! empty($comment)) {
                Log::debug("[ActivityPub] Deleting \"Like\" with source ({$id})");
                $comment->meta()->delete();
                $comment->delete();
            }

            // All done.
            return response()->json(new \stdClass(), 202);
        }

        /**
         * Reply create or update.
         *
         * @todo Add support for likes, reposts.
         */
        if (
            in_array($request->input('type'), ['Create', 'Update'], true) &&
            ! empty($object['inReplyTo']) &&
            filter_var($object['inReplyTo'], FILTER_VALIDATE_URL) &&
            ! empty($object['attributedTo']) &&
            filter_var($object['attributedTo'], FILTER_VALIDATE_URL)
        ) {
            if (empty($object['id']) || ! filter_var($object['id'], FILTER_VALIDATE_URL)) {
                // Bail.
                return response()->json(new \stdClass(), 202);
            }
            $id = filter_var($object['id'], FILTER_SANITIZE_URL);

            $parent = url_to_entry(filter_var($object['inReplyTo'], FILTER_SANITIZE_URL));

            if (! $parent) {
                // Could be a reply to a reply, still.
                $parent = Comment::whereHas('meta', function ($query) use ($object) {
                    $query->where('key', 'source')
                        ->where('value', json_encode((array) filter_var($object['inReplyTo'], FILTER_SANITIZE_URL)));
                })
                ->without('comments')
                ->first();
            }

            if (! $parent) {
                // Reply to neither an entry nor a comment. Bail.
                return response()->json(new \stdClass(), 202);
            }

            if (! empty($object['content'])) {
                /** @todo Properly "HTMLPurifier" this. */
                $content = strip_tags(
                    $object['content'],
                    '<a><b><blockquote><cite><i><em><li><ol><p><pre><strong><ul>'
                );
            }

            if (empty($content)) {
                // Bail.
                return response()->json(new \stdClass(), 202);
            }

            if ($follower = Follower::where('url', filter_var($object['attributedTo'], FILTER_SANITIZE_URL))->first()) {
                // If we happen to know this person.
                $author = $follower->name;
            }
            /** @todo Store profiles for everyone we ever interact with, and user a pivot table for actual followers. */

            $data = [
                'author' => strip_tags($author ?? filter_var($object['attributedTo'], FILTER_SANITIZE_URL)),
                'author_url' => filter_var($object['attributedTo'], FILTER_SANITIZE_URL),
                'content' => $content,
                'status' => 'pending',
                'type' => 'reply',
                'created_at' => now(), /** @todo Replace with parsed `$object['published']`, I guess. */
                'entry_id' => $parent instanceof Entry ? $parent->id : $parent->entry_id,
                'parent_id' => $parent instanceof Comment ? $parent->id : null,
            ];

            // Look for an existing comment.
            $comment = $parent->comments()
                ->whereHas('meta', function ($query) use ($object) {
                    $query->where('key', 'source')
                        ->where('value', json_encode((array) ($object['url'] ?? $object['id'])));
                })
                ->first();

            if (! empty($comment)) {
                if ($request->input('type') === 'Update') {
                    Log::debug("[ActivityPub] Found existing reaction to {$parent->permalink} for {$object['id']}");
                    $comment->update($data);
                } else {
                    Log::debug("[ActivityPub] Could this be a duplicate? ({$object['id']})");

                    // Bail.
                    return response()->json(new \stdClass(), 202);
                }
            } else {
                Log::debug("[ActivityPub] Creating new reaction to {$parent->permalink} for {$object['id']}");
                $comment = $parent->comments()->create($data);
            }

            $comment->meta()->updateOrCreate(
                ['key' => 'source'], // We use `source` for webmentions, too. Though it made sense to reuse that name.
                ['value' => (array) $id],
            );

            if (! empty($object['url']) && filter_var($object['url'], FILTER_VALIDATE_URL)) {
                // While IDs are generally valid URLs, Mastodon also publishes slightly different URLs, which it itself
                // uses to point to posts.
                $comment->meta()->updateOrCreate(
                    ['key' => 'activitypub_url'],
                    ['value' => (array) filter_var($object['url'], FILTER_SANITIZE_URL)],
                );
            }
        }

        return response()->json(new \stdClass(), 202);
    }

    protected function sendAccept(Request $request, Follower $follower, User $user): ClientResponse
    {
        $body = json_encode([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $user->actor_url . '#follow-' . bin2hex(random_bytes(16)),
            'type' => 'Accept',
            'actor' => $user->actor_url,
            'object' => json_decode($request->getContent(), true),
        ]);

        $headers = HttpSignature::sign(
            $user,
            $follower->inbox,
            $body,
            [
                'Accept' => 'application/activity+json, application/json',
                'Content-Type' => 'application/activity+json', // Must be the same as the `$contentType` argument below.
            ],
        );

        return Http::withHeaders($headers)
            ->withBody($body, 'application/activity+json')
            ->post($follower->inbox);
    }
}
