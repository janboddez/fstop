<?php

namespace App\Support\ActivityPub;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use TorMorten\Eventy\Facades\Events as Eventy;

function fetch_object(string $url, User $user = null): array
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

    $response = Cache::remember('activitypub:entry:' . md5($url), 60 * 60 * 24, function () use ($url, $user) {
        try {
            return Http::withHeaders(HttpSignature::sign(
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

        return null;
    });

    /** @todo We may eventually want to also store (and locally cache) avatars. And an `@-@` handle. */
    if (empty($response['@context'])) {
        return [];
    }

    return (array) $response; // For now.
}

function fetch_profile(string $url, User $user = null, bool $cacheAvatar = false): array
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

    $response = Cache::remember('activitypub:profile:' . md5($url), 60 * 60 * 24, function () use ($url, $user) {
        try {
            Log::debug("[ActivityPub] Fetching profile at $url");

            return Http::withHeaders(HttpSignature::sign(
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

        return null;
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

    $response = Cache::remember('activitypub:webfinger:' . md5($url), 60 * 60 * 24, function () use ($url) {
        try {
            return Http::withHeaders(['Accept' => 'application/jrd+json'])
                ->get($url)
                ->json();
        } catch (\Exception $e) {
            Log::warning("[ActivityPub] Failed to fetch $url (" . $e->getMessage() . ')');
        }

        return null;
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

function store_avatar(string $avatarUrl, string $authorUrl = '', int $size = 150): ?string
{
    $hash = md5(! empty($authorUrl) ? $authorUrl : $avatarUrl);
    $relativeAvatarPath = 'activitypub/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
    $fullAvatarPath = Storage::disk('public')->path($relativeAvatarPath);

    foreach (glob($fullAvatarPath . '.*') as $match) {
        if (time() - filectime($match) < 60 * 60 * 24 * 30) {
            Log::debug('[ActivityPub] Found a recently cached image for ' . $avatarUrl);
            return Storage::disk('public')->url(get_relative_path($match));
        }

        break;
    }

    // Download image.
    Log::debug('[ActivityPub] Downloading the image at ' . $avatarUrl);

    $blob = Cache::remember('activitypub:avatar:' . md5($avatarUrl), 60 * 60 * 24, function () use ($avatarUrl) {
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

            return null;
        }

        $blob = $response->body();

        if (empty($blob)) {
            Log::warning('[ActivityPub] Missing image data');

            return null;
        }

        return $blob;
    });

    if (empty($blob)) {
        return null;
    }

    try {
        // Recursively create directory if it doesn't exist, yet.
        if (! Storage::disk('public')->has($dir = dirname($relativeAvatarPath))) {
            Storage::disk('public')->makeDirectory($dir);
        }

        // Store original. (Somehow resizing PNGs may lead to corrupted images if we don't save them locally first.)
        Storage::disk('public')->put($relativeAvatarPath, $blob);

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

        // Load image.
        $image = $manager->read($fullAvatarPath);
        // Resize.
        $image->cover($size, $size);
        // Save image, overwriting the original.
        $image->save($fullAvatarPath);

        unset($image); // Free up memory.

        if (! file_exists($fullAvatarPath)) {
            Log::warning('[ActivityPub] Something went wrong saving the thumbnail');

            return null;
        }

        // Try and apply a meaningful file extension.
        $finfo = new \finfo(FILEINFO_EXTENSION);
        $extension = explode('/', $finfo->file($fullAvatarPath))[0];
        if (! empty($extension) && $extension !== '???') {
            // Rename file.
            Storage::disk('public')->move(
                $relativeAvatarPath,
                $relativeAvatarPath . ".$extension"
            );
        }

        // Return the (absolute) local avatar URL.
        /** @todo Verify this file actually exists? */
        return Storage::disk('public')->url($relativeAvatarPath . ".$extension");
    } catch (\Exception $e) {
        //
    }

    return null;
}

function get_relative_path(string $absolutePath, string $disk = 'public'): string
{
    return Str::replaceStart(Storage::disk($disk)->path(''), '', $absolutePath);
}
