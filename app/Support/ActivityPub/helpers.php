<?php

namespace App\Support\ActivityPub;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            Log::warning("[ActivityPub] Failed to get $url (" . $e->getMessage() . ')');
        }

        return null;
    });

    /** @todo We may eventually want to also store (and locally cache) avatars. And an `@-@` handle. */
    if (empty($response['@context'])) {
        return [];
    }

    return (array) $response; // For now.
}

function fetch_profile(string $url, User $user = null): array
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
            Log::warning("[ActivityPub] Failed to get $url (" . $e->getMessage() . ')');
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
            Log::warning("[ActivityPub] Failed to get $url (" . $e->getMessage() . ')');
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
