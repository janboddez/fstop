<?php

namespace App\Support\ActivityPub;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use TorMorten\Eventy\Facades\Events as Eventy;

function fetch_object(string $url, ?User $user = null): array
{
    if (empty($user->id)) {
        // Shared inbox request. Find the oldest user with both a private and a public key. We'll eventually want to
        // look for, like, a super admin instead.
        $user = User::orderBy('id', 'asc')
            ->whereHas('meta', function ($query) {
                $query->where('key', 'private_key')
                    ->whereNotNull('value');
            })
            ->whereHas('meta', function ($query) {
                $query->where('key', 'public_key')
                    ->whereNotNull('value');
            })
            ->first();
    }

    $url = strtok($url, '#');
    strtok('', '');

    $response = Cache::remember('activitypub:entry:' . md5($url), 60 * 60 * 24, function () use ($url, $user): array {
        Log::debug("[ActivityPub] Attempting to fetch (uncached) object at $url");

        try {
            $response = Http::withHeaders(HttpSignature::sign(
                $user,
                $url,
                null,
                ['Accept' => 'application/activity+json, application/json'],
                'get'
            ))
            ->get($url)
            ->json();
        } catch (\Exception $e) {
            Log::warning("[ActivityPub] Failed to fetch $url (" . $e->getMessage() . ')');
        }

        return isset($response) && is_array($response)
            ? $response
            : [];
    });

    /** @todo We may eventually want to also store (and locally cache) avatars. And an `@-@` handle. */
    return (array) $response; // For now.
}

function fetch_profile(string $url, ?User $user = null, bool $cacheAvatar = false): array
{
    if (empty($user->id)) {
        // Shared inbox request. Find the oldest user with both a private and a public key. We'll eventually want to
        // look for, like, a super admin instead.
        $user = User::orderBy('id', 'asc')
            ->whereHas('meta', function ($query) {
                $query->where('key', 'private_key')
                    ->whereNotNull('value');
            })
            ->whereHas('meta', function ($query) {
                $query->where('key', 'public_key')
                    ->whereNotNull('value');
            })
            ->first();
    }

    $url = strtok($url, '#');
    strtok('', '');

    $response = Cache::remember('activitypub:profile:' . md5($url), 60 * 60 * 24, function () use ($url, $user): array {
        try {
            Log::debug("[ActivityPub] Fetching profile at $url");

            $response = Http::withHeaders(HttpSignature::sign(
                $user,
                $url,
                null,
                ['Accept' => 'application/activity+json, application/json'],
                'get'
            ))
            ->get($url)
            ->json();
        } catch (\Exception $e) {
            Log::warning("[ActivityPub] Failed to fetch the profile at $url (" . $e->getMessage() . ')');
        }

        return isset($response) && is_array($response)
            ? $response
            : [];
    });

    /** @todo We may eventually want to also store (and locally cache) avatars. And an `@-@` handle. */
    if (! empty($response['publicKey']['id'])) {
        return array_filter([
            'username' => isset($response['preferredUsername']) && is_string($response['preferredUsername'])
                ? strip_tags($response['preferredUsername'])
                : null,
            'name' => isset($response['name']) && is_string($response['name'])
                ? strip_tags($response['name'])
                : null,
            // Profile URL, which may (return HTML and) be different from their "ID."
            'url' => isset($response['url']) && Str::isUrl($response['url'], ['http', 'https'])
                ? filter_var($response['url'], FILTER_SANITIZE_URL)
                : null,
            'inbox' => isset($response['inbox']) && Str::isUrl($response['inbox'], ['http', 'https'])
                ? filter_var($response['inbox'], FILTER_SANITIZE_URL)
                : null,
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'shared_inbox' => isset($response['endpoints']['sharedInbox']) && Str::isUrl($response['endpoints']['sharedInbox'], ['http', 'https'])
                ? filter_var($response['endpoints']['sharedInbox'], FILTER_SANITIZE_URL)
                : null,
            'outbox' => isset($response['outbox']) && Str::isUrl($response['outbox'], ['http', 'https'])
                ? filter_var($response['outbox'], FILTER_SANITIZE_URL)
                : null,
            'key_id' => $response['publicKey']['id'] ?? null,
            'public_key' => $response['publicKey']['publicKeyPem'] ?? null,
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'avatar' => $cacheAvatar && isset($response['icon']['url']) && Str::isUrl($response['icon']['url'], ['http', 'https'])
                ? store_avatar($response['icon']['url'], $url)
                : null,
        ]);
    }

    return [];
}

function fetch_webfinger(string $resource): ?string
{
    $resource = ltrim($resource, '@');
    if (filter_var($resource, FILTER_VALIDATE_EMAIL) && $pos = strpos($resource, '@')) {
        $host = substr($resource, $pos + 1);
        $login = substr($resource, 0, $pos);
    }

    if (empty($host) || empty($login)) {
        return null;
    }

    $url = "https://{$host}/.well-known/webfinger?resource=" . rawurlencode("acct:{$login}@{$host}");

    $response = Cache::remember('activitypub:webfinger:' . md5($url), 60 * 60 * 24, function () use ($url): array {
        try {
            return Http::withHeaders(['Accept' => 'application/jrd+json'])
                ->get($url)
                ->json(null, []);
        } catch (\Exception $e) {
            Log::warning("[ActivityPub] Failed to fetch $url (" . $e->getMessage() . ')');
        }

        return []; // Instead of `null`.
    });

    /** @todo We may eventually want to also store (and locally cache) avatars. And an `@-@` handle. */
    if (empty($response['links'])) {
        return null;
    }

    foreach ($response['links'] as $link) {
        if (empty($link['rel']) || $link['rel'] !== 'self') {
            continue;
        }

        if (
            empty($link['type']) ||
            ! in_array($link['type'], [
                'application/activity+json',
                'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
            ], true)
        ) {
            continue;
        }

        if (! Str::isUrl($link['href'], ['http', 'https'])) {
            continue;
        }

        return filter_var($link['href'], FILTER_SANITIZE_URL);
    }

    return null;
}

function get_inbox(string $url): ?string
{
    $data = fetch_profile($url);

    return $data['inbox'] ?? null;
}

function object_to_id(mixed $object): ?string
{
    if (Str::isUrl($object, ['http', 'https'])) {
        $id = filter_var($object, FILTER_SANITIZE_URL);
    } elseif (! empty($object['id']) && Str::isUrl($object['id'], ['http', 'https'])) {
        $id = filter_var($object['id'], FILTER_SANITIZE_URL);
    }

    return isset($id) && is_string($id)
        ? $id
        : null;
}

function generate_activity(string $type, Entry|User $model): ?array
{
    $model->refresh(); // Just in case.

    // Turn entry into Activity Streams object (array).
    $object = $model->serialize();

    if ($model instanceof Entry) {
        $entry = $model;
        $user = $model->user;
    } else {
        $user = $model;
    }

    // Wrap in "Activity."
    $activity = array_filter([
        '@context' => ['https://www.w3.org/ns/activitystreams'],
        'type' => $type,
        'actor' => $user->author_url,
        'object' => $object,
        'published' => $object['published'],
        'updated' => $type === 'Create' ? null : ($object['updated'] ?? null),
        'to' => $object['to'] ?? ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => $object['cc'] ?? [url("activitypub/users/{$user->id}/followers")],
    ]);

    if ($model instanceof Entry) {
        // This is where we add like and repost support ...
        if (($likeOf = $entry->meta->firstWhere('key', '_like_of')) && ! empty($likeOf->value[0])) {
            // This would be a "like."
            if (
                $type === 'Create' &&
                ($author = $entry->meta->firstWhere('key', '_like_of_author')) &&
                ! empty($author->value)
            ) {
                // Convert to Like activity.
                $activity['type'] = 'Like';
                $activity['object'] = filter_var($likeOf->value[0], FILTER_VALIDATE_URL);
                unset($activity['updated']);
            } elseif (
                $type === 'Delete' &&
                ($like = $entry->meta->firstWhere('key', '_activitypub_activity')) &&
                ! empty($like->value)
            ) {
                // Undo previous like.
                $activity['type'] = 'Undo';
                $activity['object'] = $like->value; // The Like activity from before.
                unset($activity['updated']);
            } else {
                // Either we're dealing with an Update, or the remote server seemingly doesn't support ActivityPub.
                return null;
            }
        } elseif (($repostOf = $entry->meta->firstWhere('key', '_repost_of')) && ! empty($repostOf->value[0])) {
            // This would be a "repost."
            if (
                $type === 'Create' &&
                ($author = $entry->meta->firstWhere('key', '_repost_of_author')) &&
                ! empty($author->value)
            ) {
                // Convert to Announce activity.
                $activity['type'] = 'Announce';
                $activity['object'] = filter_var($repostOf->value[0], FILTER_VALIDATE_URL);
                unset($activity['updated']);
            } elseif (
                $type === 'Delete' &&
                ($announce = $entry->meta->firstWhere('key', '_activitypub_activity')) &&
                ! empty($announce->value)
            ) {
                // Undo previous Announce.
                $activity['type'] = 'Undo';
                $activity['object'] = $announce->value; // The Announce activity from before.
                unset($activity['updated']);
            } else {
                // Either we're dealing with an Update, or the remote server seemingly doesn't support ActivityPub.
                return null;
            }
        }
    }

    // Neither like nor repost. Continue as per the uge.
    if ($activity['type'] === 'Update') {
        // We want (only) Updates to have a truly unique activity ID.
        $activity['id'] = $object['id'] . '#' . strtolower($activity['type'] ?? $type) . '-'
            . bin2hex(random_bytes(16));
    } else {
        $activity['id'] = $object['id'] . '#' . strtolower($activity['type']);
    }

    return $activity;
}

function store_avatar(string $avatarUrl, string $authorUrl = '', int $size = 150): ?string
{
    if (extension_loaded('imagick') && class_exists('Imagick')) {
        Log::debug('[ActivityPub] Using Imagick');
        $manager = new ImageManager(new ImagickDriver());
    } elseif (extension_loaded('gd') && function_exists('gd_info')) {
        Log::debug('[ActivityPub] Using GD');
        $manager = new ImageManager(new GdDriver());
    } else {
        Log::warning('[ActivityPub] Imagick nor GD installed');

        return null;
    }

    $hash = md5(! empty($authorUrl) ? $authorUrl : $avatarUrl);

    // (Relative) destination path, without extension (for now).
    $relativeAvatarPath = 'activitypub/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;

    foreach (glob(Storage::disk('public')->path($relativeAvatarPath) . '.*') as $match) {
        if (time() - filectime($match) < 60 * 60 * 24 * 30) {
            Log::debug('[ActivityPub] Found a recently cached image for ' . $avatarUrl);

            return Storage::disk('public')->url(get_relative_path($match));
        }

        break;
    }

    // Download image.
    Log::debug('[ActivityPub] Downloading the image at ' . $avatarUrl);

    // phpcs:ignore Generic.Files.LineLength.TooLong
    $blob = Cache::remember('activitypub:avatar:' . md5($avatarUrl), 60 * 60 * 24, function () use ($avatarUrl): string {
        $response = Http::withHeaders(HttpSignature::sign(
            User::find(1),
            $avatarUrl,
            null,
            [
                'Accept' => 'image/*',
                'User-Agent' => Eventy::filter(
                    'activitypub:user_agent',
                    'F-Stop/' . config('app.version') . '; ' . url('/'),
                    $avatarUrl
                ),
            ],
            'get'
        ))
        ->get($avatarUrl);

        if (! $response->successful()) {
            Log::warning('[ActivityPub] Something went wrong fetching the image at ' . $avatarUrl);

            return '';
        }

        return $response->body();
    });

    if (empty($blob)) {
        Log::warning('[ActivityPub] Missing image data');

        return null;
    }

    try {
        // Temporarily store original. (Somehow resizing PNGs may lead to corrupted images if we don't save them locally
        // first.)
        $tempFile = tempnam(sys_get_temp_dir(), $hash);

        if (! file_put_contents($tempFile, $blob)) {
            Log::warning('[ActivityPub] Empty file.');

            return null;
        };

        // Try and apply a meaningful file extension.
        $finfo = new \finfo(FILEINFO_EXTENSION);
        $extension = explode('/', $finfo->file($tempFile))[0];

        if (empty($extension) || ! in_array($extension, ['gif', 'jpeg', 'jpg', 'png', 'webp'], true)) {
            Log::warning('[ActivityPub] Unknown file format.');
            unlink($tempFile); // Delete.

            return null;
        }

        $relativeAvatarPath = $relativeAvatarPath . ".$extension";
        $fullAvatarPath = Storage::disk('public')->path($relativeAvatarPath);

        // Recursively create directory if it doesn't exist, yet.
        if (! Storage::disk('public')->has($dir = dirname($relativeAvatarPath))) {
            Log::debug("[ActivityPub] Creating directory $dir");

            Storage::disk('public')->makeDirectory($dir);
        }

        // Load image.
        $image = $manager->read($tempFile);
        // Resize.
        $image->cover($size, $size);
        // Save image, overwriting the original.
        $image->save($fullAvatarPath);

        unset($image); // Free up memory.
        unlink($tempFile); // Delete temporary file.

        if (! file_exists($fullAvatarPath)) {
            Log::warning('[ActivityPub] Something went wrong saving the thumbnail');

            return null;
        }

        return Storage::disk('public')->url($relativeAvatarPath);
    } catch (\Exception $e) {
        Log::warning('[ActivityPub] Something went wrong saving or resizing the image: ' . $e->getMessage());
    }

    return null;
}

function get_relative_path(string $absolutePath, string $disk = 'public'): string
{
    return Str::replaceStart(Storage::disk($disk)->path(''), '', $absolutePath);
}
