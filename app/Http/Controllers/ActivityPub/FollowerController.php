<?php

namespace App\Http\Controllers\ActivityPub;

use App\Http\Controllers\Controller;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class FollowerController extends Controller
{
    public function __invoke(User $user): Response
    {
        $followers = $user->followers()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->paginate();

        // `Response` rather than an array, so we can set a Content-Type header.
        return response()->json(
            array_filter([
                '@context' => ['https://www.w3.org/ns/activitystreams'],
                'id' => route('activitypub.followers', $user),
                'actor' => $user->actor_url,
                'type' => 'OrderedCollectionPage',
                'partOf' => route('activitypub.followers', $user),
                'totalItems' => $followers->total(),
                'orderedItems' => array_map(
                    fn ($follower) => filter_var($follower->url, FILTER_SANITIZE_URL),
                    $followers->items()
                ),
                'first' => route('activitypub.followers', ['user' => $user, 'page' => 1]),
                'last' => route('activitypub.followers', ['user' => $user, 'page' => $followers->lastPage()]),
                'next' => $followers->nextPageUrl(),
                'prev' => $followers->previousPageUrl(),
            ], fn ($value) => $value || $value === 0), // Allow literal `0`.
            200,
            ['Content-Type' => 'application/activity+json']
        );
    }
}
