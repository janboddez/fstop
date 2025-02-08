<?php

namespace App\Http\Controllers\Micropub;

use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Entry;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Events as Eventy;

class MicropubController extends Controller
{
    /**
     * Handle `GET` requests.
     */
    public function get(Request $request)
    {
        switch ($request->input('q')) {
            case 'config':
                $types = Eventy::filter(
                    'micropub:post_types',
                    config('micropub.post_types', ['article'])
                );

                $types = array_map(fn ($type) => ['type' => $type, 'name' => ucfirst($type)], $types);

                return response()->json(array_filter([
                    'post-types' => $types,
                    'syndicate-to' => Eventy::filter('micropub:syndicate_to', [], $request),
                    'media-endpoint' => route('micropub.media-endpoint'),
                    'q' => ['config', 'syndicate-to', 'category', 'source'],
                ]));

            case 'syndicate-to':
                return response()->json(Eventy::filter('micropub:syndicate_to', [], $request));

            case 'category':
                return response()->json([
                    'categories' => Tag::pluck('name')->toArray(),
                ]);

            case 'source':
                if ($request->filled('url')) {
                    $entry = Enty::urlToEntry($request->input('url'));

                    return response()->json([
                        'properties' => $entry->getProperties((array) $request->input('properties')),
                    ]);
                }

                $entries = Entry::orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->limit($request->filled('limit') ? (int) $request->input('limit') : 10);

                if (
                    $request->filled('post-type') &&
                    in_array($request->input('post-type'), get_registered_entry_types(), true)
                ) {
                    // Note that we support only article, and (but only when the "entry types" plugin is active) note
                    // and like. Bookmarks, reposts, and the like, get converted to "notes." So you can't actually
                    // filter for them this way; not yet, at least.
                    $entries = $entries->ofType($request->input('post-type'));
                } else {
                    // Just return all currently active entry types.
                    $entries = $entries->whereIn('type', get_registered_entry_types('slug', 'page'));
                }

                // Not that we return also draft and private, etc., entries. Given that, well, this is Micropub. We may
                // want to eventually restrict by user/author, though.
                $entries = $entries->get();

                return response()->json([
                    'items' => $entries->map(fn ($entry) => [
                        'type' => 'h-entry',
                        'properties' => $entry->getProperties(),
                    ]),
                ]);
        }

        abort(400, __('Missing or invalid `q` parameter.'));
    }

    /**
     * Handle media uploads.
     */
    public function media(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file',
        ]);

        $path = $validated['file']->storeAs(
            gmdate('Y/m'), // Add year and month, WordPress-style.
            $validated['file']->hashName(), // Micropub filenames are `file` always, so generate a random filename.
            'public'
        );

        $attachment = Attachment::updateOrCreate(
            [
                'path' => $path,
                'user_id' => $request->user()->id,
            ],
            $validated
        );

        AttachmentController::createThumbnails($attachment);

        return response()->json(
            new \StdClass(),
            201,
            ['Location' => $attachment->url]
        );
    }

    /**
     * Handle `POST` requests.
     */
    public function post(Request $request)
    {
        // Bit of a hack using form validation rules here, no?
        $validated = $request->validate(Entry::validationRules());

        if (in_array($request->input('action'), ['create', null], true)) {
            abort_unless($request->user()->tokenCan('create'), 403, __('Insufficient scope.'));

            $properties = $validated['properties'];

            // Copy `mp-slug` to `slug`, and `post-status` to `status`.
            $properties['slug'] = $properties['mp-slug']
                ?? $properties['slug']
                ?? null;

            // Things like replies or likes benefit from some prepended "context."
            $properties['content'] = (array) static::generateContent($properties);
            $properties['status'] = $properties['post-status'] ?? ['published'];

            if (isset($properties['latitude'][0]) && isset($properties['longitude'][0])) {
                $properties['geo'] = [
                    'latitude' => $properties['latitude'][0],
                    'longitude' => $properties['longitude'][0],
                ];
            } elseif (
                isset($properties['location'][0]) &&
                Str::startsWith($properties['location'][0], 'geo:')
            ) {
                /** @todo Move this to a helper function. */
                $geo = substr(urldecode($properties['location'][0]), 4);
                $geo = explode(';', $geo);
                $coords = explode(',', $geo[0]);

                $properties['geo'] = [
                    'lat' => (float) trim($coords[0]),
                    'lon' => (float) trim($coords[1]),
                ];

                if (isset($coords[2])) {
                    $properties['geo']['alt'] = (float) trim($coords[2]);
                }
            }

            // Note that despite everything in Micropub being an array, we only ever "process" one URL.
            if (! empty($properties['in-reply-to'][0])) {
                $type = 'note';
            } elseif (! empty($properties['like-of'][0])) {
                $type = 'like';
            } elseif (! empty($properties['bookmark-of'][0])) {
                $type = 'note';
            } elseif (! empty($properties['repost-of'][0])) {
                $type = 'note';
            } elseif (! empty($properties['name'][0])) {
                $type = 'article';
            }

            $properties['type'] = [$type ?? 'note'];

            $properties['created_at'] = [
                ! empty($properties['published'][0])
                    ? new Carbon($properties['published'][0])
                    : now(),
            ];

            $properties['user_id'] = (array) $request->user()->id;

            $entry = Entry::create(array_map(
                fn ($value) => reset($value),
                array_filter($properties)
            ));

            abort_unless($entry, 500, __('Oops, something went wrong.'));

            // Process tags.
            if (! empty($properties['category'])) {
                $tagIds = [];

                foreach ((array) $properties['category'] as $category) {
                    $tag = Tag::firstOrCreate(
                        ['slug' => Str::slug($category)],
                        ['name' => $category]
                    );

                    $tagIds[] = $tag->id;
                }

                $entry->tags()->sync($tagIds);
            }

            // Process meta.
            $meta = array_intersect_key($properties, array_flip(Entry::$supportedProperties['meta']));

            if (! empty($meta)) {
                add_meta($meta, $entry);
            }

            Eventy::action('entries:saved', $entry);

            return response()->json(
                new \StdClass(),
                201,
                // This may cause some Micropub clients to redirect here, which could result in a 404 if the post is
                // still draft, but they don't seem to like it when we don't return a URL either, so ...
                ['Location' => route(Str::plural($entry->type) . '.show', ['slug' => $entry->slug])]
            );
        } // End "create."

        // Scenarios other than "create" require a URL.
        abort_unless($request->filled('url'), 400, __('Missing URL parameter.'));
        abort_unless(Str::isUrl($request->input('url'), ['http', 'https']), 400, __('Invalid URL parameter.'));

        $entry = url_to_entry($request->input('url'));

        abort_unless($entry, 404, __('Not found'));

        /** @todo Add other actions, maybe, one day. */
        switch ($request->input('action')) {
            case 'delete':
                abort_unless($request->user()->tokenCan('delete'), 403, __('Insufficient scope.'));

                $entry->delete();

                return response()
                    ->json(new \StdClass(), 204);

            case 'undelete':
                abort_unless($request->user()->tokenCan('update'), 403, __('Insufficient scope.'));

                $entry->restore();

                return response()
                    ->json(new \StdClass(), 204);
        }

        abort(400, __('Unsupported action.'));
    }

    /**
     * @todo Make this filterable, or actually fetch the pages in question and base the output off of the response.
     */
    protected static function generateContent(array $properties): ?string
    {
        if (! empty($properties['in-reply-to'][0])) {
            $context = __(
                '*In reply to [:name](:url){.u-in-reply-to}.*',
                [
                    'name' => e(
                        ! empty($properties['name'][0])
                            ? strip_tags($properties['name'][0])
                            : filter_var($properties['in-reply-to'][0], FILTER_SANITIZE_URL)
                    ),
                    'url' => e(filter_var($properties['in-reply-to'][0], FILTER_SANITIZE_URL)),
                ]
            );

            return trim(
                $context . "\n\n" . (
                    ! empty($properties['content'][0])
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    ? "<div class=\"e-content\" markdown=\"1\">\n" . $properties['content'][0] . "\n</div>"
                    : ''
                )
            );
        }

        if (! empty($properties['like-of'][0])) {
            $context = __(
                '*Liked [:name](:url){.u-like-of}.*',
                [
                    'name' => e(
                        ! empty($properties['name'][0])
                            ? strip_tags($properties['name'][0])
                            : filter_var($properties['like-of'][0], FILTER_SANITIZE_URL)
                    ),
                    'url' => e(filter_var($properties['like-of'][0], FILTER_SANITIZE_URL)),
                ]
            );

            return trim(
                $context . "\n\n" . (
                    ! empty($properties['content'][0])
                    ? "<div class=\"e-content\" markdown=\"1\">\n" . $properties['content'][0] . "\n</div>"
                    : ''
                )
            );
        }

        if (! empty($properties['bookmark-of'][0])) {
            $context = __(
                '*Bookmarked [:name](:url){.u-bookmark-of}.*',
                [
                    'name' => e(
                        ! empty($properties['name'][0])
                            ? strip_tags($properties['name'][0])
                            : filter_var($properties['bookmark-of'][0], FILTER_SANITIZE_URL)
                    ),
                    'url' => e(filter_var($properties['bookmark-of'][0], FILTER_SANITIZE_URL)),
                ]
            );

            return trim(
                $context . "\n\n" . (
                    ! empty($properties['content'][0])
                    ? "<div class=\"e-content\" markdown=\"1\">\n" . $properties['content'][0] . "\n</div>"
                    : ''
                )
            );
        }

        if (! empty($properties['repost-of'][0])) {
            $context = __(
                '*Reposted [:name](:url){.u-repost-of}.*',
                [
                    'name' => e(
                        ! empty($properties['name'][0])
                            ? strip_tags($properties['name'][0])
                            : filter_var($properties['repost-of'][0], FILTER_SANITIZE_URL)
                    ),
                    'url' => e(filter_var($properties['repost-of'][0], FILTER_SANITIZE_URL)),
                ]
            );

            return trim(
                $context . "\n\n" . (
                    ! empty($properties['content'][0])
                    ? "<blockquote class=\"e-content\" markdown=\"1\">\n" . $properties['content'][0] . "\n</blockquote>"
                    : ''
                )
            );
        }

        return $properties['content'][0] ?? null;
    }
}
