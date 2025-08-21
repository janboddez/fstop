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
        if (! $request->has('page')) {
            $total = $user->entries()
                ->whereIn('type', get_registered_entry_types('slug', 'page'))
                ->orderBy('published', 'desc')
                ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
                ->whereDoesntHave('meta', function ($query) {
                    // Exclude likes.
                    $query->where('key', '_like_of')
                        ->whereNotNull('value');
                })
                ->published()
                ->public()
                ->count();

            return response()->json(
                array_filter([
                    '@context' => ['https://www.w3.org/ns/activitystreams'],
                    'id' => $user->outbox,
                    'actor' => $user->author_url,
                    'type' => 'OrderedCollection',
                    'totalItems' => $total,
                    'first' => route('activitypub.outbox', ['user' => $user, 'page' => 1]),
                    'last' => route('activitypub.outbox', ['user' => $user, 'page' => ceil($total / 15)]),
                ]),
                200,
                ['Content-Type' => 'application/activity+json']
            );
        }

        $entries = $user->entries()
            ->whereIn('type', get_registered_entry_types('slug', 'page'))
            ->orderBy('published', 'desc')
            ->orderBy('id', 'desc') // Prevent pagination issues by also sorting by ID.
            ->whereDoesntHave('meta', function ($query) {
                // Exclude likes.
                $query->where('key', '_like_of')
                    ->whereNotNull('value');
            })
            ->published()
            ->public()
            ->with('user')
            ->paginate();

        // `Response` rather than an array, so we can set a Content-Type header.
        return response()->json(
            array_filter([
                '@context' => ['https://www.w3.org/ns/activitystreams'],
                'id' => route('activitypub.outbox', ['user' => $user, 'page' => $request->input('page')]),
                'actor' => $user->author_url,
                'type' => 'OrderedCollectionPage',
                'partOf' => $user->outbox,
                'totalItems' => $entries->total(),
                'orderedItems' => array_map(
                    function ($entry) {
                        $object = $entry->serialize();

                        if (($meta = $entry->meta->firstWhere('key', '_repost_of')) && ! empty($meta->value[0])) {
                            $activity = array_filter([
                                '@context' => ['https://www.w3.org/ns/activitystreams'],
                                'id' => $object['id'] . '#activity',
                                'type' => 'Announce',
                                'actor' => $entry->user->author_url,
                                'object' => filter_var($meta->value[0], FILTER_SANITIZE_URL),
                                'published' => $object['published'],
                                'to' => $object['to'] ?? ['https://www.w3.org/ns/activitystreams#Public'],
                                'cc' => $object['cc'] ?? [url("activitypub/users/{$entry->user->id}/followers")],
                            ]);

                            return $activity;
                        }

                        $activity = array_filter([
                            '@context' => ['https://www.w3.org/ns/activitystreams'],
                            'id' => $object['id'] . '#activity',
                            'type' => 'Create',
                            'actor' => $entry->user->author_url,
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
                'first' => route('activitypub.outbox', ['user' => $user, 'page' => 1]),
                'last' => route('activitypub.outbox', ['user' => $user, 'page' => $entries->lastPage()]),
                'next' => $entries->nextPageUrl(),
                'prev' => $entries->previousPageUrl(),
            ]),
            200,
            ['Content-Type' => 'application/activity+json']
        );
    }
}
