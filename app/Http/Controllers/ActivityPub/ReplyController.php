<?php

namespace App\Http\Controllers\ActivityPub;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReplyController extends Controller
{
    public function __invoke(Request $request, Entry $entry): Response
    {
        $comments = $entry->comments()
            ->approved()
            ->whereHas('meta', function ($query) {
                $query->where('key', 'activitypub_url') // Or `source`?
                    ->whereNotNull('value');
            })
            ->with('entry')
            ->paginate();

        // `Response` rather than an array, so we can set a Content-Type header.
        return response()->json(
            array_filter([
                '@context' => ['https://www.w3.org/ns/activitystreams'],
                'id' => route('activitypub.replies', ['entry' => $entry, 'page' => $request->input('page')]),
                'type' => 'CollectionPage',
                'partOf' => route('activitypub.replies', $entry),
                'totalItems' => $comments->total(),
                'items' => array_filter(array_map(
                    fn ($comment) => (($meta = $comment->meta->firstWhere('key', 'source')) && ! empty($meta->value[0]))
                        ? filter_var($meta->value[0], FILTER_SANITIZE_URL)
                        : null,
                    $comments->items()
                )),
                'first' => route('activitypub.replies', ['entry' => $entry, 'page' => 1]),
                'last' => route('activitypub.replies', ['entry' => $entry, 'page' => $comments->lastPage()]),
                'next' => $comments->nextPageUrl(),
                'prev' => $comments->previousPageUrl(),
            ]),
            200,
            ['Content-Type' => 'application/activity+json']
        );
    }
}
