<?php

namespace App\Http\Controllers\ActivityPub;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OutboxController extends Controller
{
    public function __invoke(Request $request, User $user): Response
    {
        $entries = $user->entries()
            ->whereIn('type', get_registered_entry_types('slug', 'page'))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->published()
            ->public()
            ->with('user')
            ->paginate();

        // `Response` rather than an array, so we can set a Content-Type header.
        return response()->json(
            array_filter([
                '@context' => ['https://www.w3.org/ns/activitystreams'],
                'id' => $user->outbox,
                'actor' => $user->actor_url,
                'type' => 'OrderedCollectionPage',
                'partOf' => $user->outbox,
                'totalItems' => $entries->total(),
                'orderedItems' => array_map(
                    function ($entry) {
                        $object = $entry->serialize();
                        $activity = array_filter([
                            '@context' => ['https://www.w3.org/ns/activitystreams'],
                            'id' => $entry['id'] . '#activity',
                            'type' => 'Create',
                            'actor' => $entry->user->actor_url,
                            'object' => $object,
                            'published' => $object['published'],
                            'updated' => $object['updated'] ?? null,
                            'to' => $object['to'] ?? ['https://www.w3.org/ns/activitystreams#Public'],
                            'cc' => $object['cc'] ?? [url("activitypub/users/{$entry->user->id}/followers")],
                        ]);

                        return $activity;
                    },
                    $entries->items()
                ),
                'first' => $user->outbox . '?page=1',
                'last' => $user->outbox . '?page=' . $entries->lastPage(),
                'next' => $entries->nextPageUrl(),
                'prev' => $entries->previousPageUrl(),
            ]),
            200,
            ['Content-Type' => 'application/activity+json']
        );
    }
}
