<?php

namespace App\Support\ActivityPub\Handlers;

use App\Models\Actor;
use App\Models\User;
use App\Support\ActivityPub\HttpSignature;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function App\Support\ActivityPub\fetch_profile;

class Follow
{
    public function __construct(
        protected Request $request,
        protected User $user
    ) {
    }

    public function handle(): void
    {
        if (! $actor = filter_var($this->request->input('actor'), FILTER_VALIDATE_URL)) {
            // Missing or invalid actor URL.
            return;
        }

        $actor = filter_var($actor, FILTER_SANITIZE_URL);

        if (empty($this->user->id)) {
            // Legacy (or shared inbox) request.
            $this->user = User::find(1);
        }

        // if ($this->user->followers()->where('url', $actor)->exists()) {
        //     /**
        //      * @todo We'll want to fetch their profile regardless, and look up their handle, and perform a WebFinger
        //      *       request and verify their URLs.
        //      */
        //     return;
        // }

        // Fetch their ActivityPub actor profile.
        $meta = fetch_profile($actor, $this->user, true);
        if ((empty($meta['inbox']) && empty($meta['sharedInbox']))) {
            Log::warning("[ActivityPub] Something went wrong fetching the profile at $actor");

            return;
        }

        // Save.
        $actor = $this->user->followers()
            ->updateOrCreate(['url' => $actor]);

        // Store metadata as well.
        if ($actor->wasRecentlyCreated) {
            add_meta($meta, $actor);
        } else {
            foreach (prepare_meta($meta, $actor) as $key => $value) {
                $actor->meta()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        $response = $this->sendAccept($actor);

        if (! $response->successful()) {
            Log::warning("[ActivityPub] Something went wrong accepting a follow request by $actor");
            Log::debug($response);
        }
    }

    protected function sendAccept(Actor $follower): Response
    {
        $follower->load('meta');

        $body = json_encode([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->user->actor_url . '#follow-' . bin2hex(random_bytes(16)),
            'type' => 'Accept',
            'actor' => $this->user->actor_url,
            'object' => json_decode($this->request->getContent(), true),
        ]);

        $headers = HttpSignature::sign(
            $this->user,
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
