<?php

use App\Models\Attachment;
use App\Models\Entry;
use App\Models\Option;
use App\Models\User;
use App\Support\ActivityPub\HttpSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Events as Eventy;

function is_archive(string $type = null): bool
{
    if (request()->is('admin/*')) {
        return false;
    }

    if ($type) {
        if (Route::currentRouteName() === Str::plural($type) . '.index') {
            return true;
        }

        return false;
    }

    if (is_tag()) {
        // Tag archive.
        return true;
    }

    if (request()->is('users/*')) {
        // "Author archive."
        return true;
    }

    if (request()->is('/')) {
        // Homepage. Which may be an "alias" for `articles.index`.
        return true;
    }

    if (request()->is('search')) {
        // Search results.
        return true;
    }

    if (request()->is('stream')) {
        // "Stream," i.e., all entry types.
        return true;
    }

    foreach (get_registered_entry_types() as $type) {
        if (Route::currentRouteName() === Str::plural($type) . '.index') {
            return true;
        }
    }

    return false;
}

function is_tag(string $name = null): bool
{
    if (! request()->is('tags/*')) {
        return false;
    }

    if (! $name) {
        return true;
    }

    if (! request()->is("tags/$name")) {
        return false;
    }

    return true;
}

function is_page(string $name = null): bool
{
    if (! is_singular('page')) {
        return false;
    }

    if (! $name) {
        return true;
    }

    if (! request()->is($name)) {
        return false;
    }

    return true;
}

function is_singular(string $type = null): bool
{
    if (request()->is('admin/*')) {
        return false;
    }

    if ($type) {
        if (Route::currentRouteName() === Str::plural($type) . '.show') {
            return true;
        }

        return false;
    }

    foreach (get_registered_entry_types() as $type) {
        if (Route::currentRouteName() === Str::plural($type) . '.show') {
            return true;
        }
    }

    return false;
}

function body_class(): string
{
    $classes = [];

    if (request()->is('/')) {
        $classes[] = 'home';
    }

    if (is_archive()) {
        $classes[] = 'archive';
    }

    if (is_singular()) {
        $classes[] = 'single';
    }

    if (request()->is('users/*')) {
        $classes[] = 'author';
    }

    if (is_tag()) {
        $classes[] = 'tag';
    }

    foreach (get_registered_entry_types() as $type) {
        if (Route::currentRouteName() === Str::plural($type) . '.index') {
            $classes[] = $type;
        }

        if (Route::currentRouteName() === Str::plural($type) . '.show') {
            $classes[] = $type;
        }
    }

    if (request()->is('stream')) {
        $classes[] = 'stream';
    }

    return implode(' ', array_unique($classes));
}

function random_slug(): string
{
    do {
        $slug = bin2hex(openssl_random_pseudo_bytes(5));
    } while (Entry::where('slug', $slug)->withTrashed()->exists());

    return $slug;
}

/**
 * From Laravel's `Str::slug()`.
 *
 * The one difference is that this here method allows forward slashes, too.
 */
function slugify(string $value, string $separator = '-', string $language = 'en'): string
{
    $flip = $separator === '-' ? '_' : '-';

    $value = $language ? Str::ascii($value, $language) : $value;
    $value = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $value);
    $value = str_replace('@', "$separator'at'$separator", $value);

    // Allow forward slashes, too.
    // $value = preg_replace('![^'.preg_quote($separator).'\pL\pN\s]+!u', '', Str::lower($value));
    $value = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s/]+!u', '', Str::lower($value));

    $value = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $value);
    $value = trim($value, $separator);

    return $value;
}

function url_to_attachment(string $url): ?Attachment
{
    if (! filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    if (! $path = parse_url($url, PHP_URL_PATH)) {
        return null;
    }

    // Remove the `storage/` bit (or whatever it's set to).
    $path = Str::replaceStart(
        Storage::disk('public')->url(''),
        '',
        url($path)
    );

    $attachment = Attachment::where('path', $path)
        ->first();

    return $attachment;
}

function url_to_entry(string $url): ?Entry
{
    if (! filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    if (! $slug = basename($url)) {
        return null;
    }

    $entry = Entry::where('slug', $slug)
        ->first();

    return $entry;
}

/**
 * @param  string|array $exclude
 */
function get_registered_entry_types(string $returnType = null, mixed $exclude = null): array
{
    $types = (array) Eventy::filter('entries:registered_types', Entry::TYPES);

    if ($exclude) {
        $types = array_diff_key($types, array_flip((array) $exclude));
    }

    if (! empty($types['page'])) {
        // Ensure the `page` type is listed last.
        $page = $types['page'];
        unset($types['page']);
        $types['page'] = $page;
    }

    if ($returnType === 'object') {
        return $types;
    }

    // Return slugs only.
    return array_keys($types);
}

function get_site_settings(): array
{
    static $settings = null; // `static`, to avoid having to hit the database more than once.

    if ($settings) {
        return $settings;
    }

    try {
        DB::connection()
            ->getPdo();

        // Prevent code from running before migrations have run.
        if (Schema::hasTable('options')) {
            $option = Option::where('key', 'site_settings')
                ->first();
        }
    } catch (\Exception $exception) {
        // Do nothing.
    }

    $settings = $option->value ?? [];

    return $settings;
}

function site_name(): string
{
    $settings = get_site_settings();

    if (! empty($settings['name'])) {
        return $settings['name'];
    }

    return (string) config('app.name');
}

function site_tagline(): string
{
    $settings = get_site_settings();

    if (! empty($settings['tagline'])) {
        return $settings['tagline'];
    }

    return '';
}

/**
 * Registers a plugin or theme's namespace(s), for autoloading.
 */
function autoload_register(array $namespaces, string $dir): ?string
{
    foreach ($namespaces as $namespace => $paths) {
        $paths = (array) $paths;

        spl_autoload_register(function ($class) use ($namespace, $paths, $dir) {
            // Check if the namespace matches the class we are looking for.
            if (! preg_match('~^' . preg_quote($namespace) . '~', $class)) {
                return null;
            }

            // Remove the namespace from the file path. (Plugins *must* use PSR4.)
            $class = str_replace($namespace, '', $class);
            $filename = preg_replace('~\\\\~', '/', $class) . '.php';

            foreach ($paths as $path) {
                $filename = "$dir/$path/$filename";

                if (! file_exists($filename)) {
                    return null;
                }

                // Load class file.
                include_once $filename;

                return $filename;
            }
        });
    }

    return null;
}

function list_pages(int $number = 5): string
{
    $html = '';

    $pages = Entry::ofType('page')
        ->orderBy('name', 'asc')
        ->orderBy('id', 'desc')
        ->published()
        ->public()
        ->limit($number)
        ->get();

    foreach ($pages as $page) {
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $html .= '<li ' . (request()->is($page->slug) ? ' class="active"' : '') . '><a href="' . e($page->permalink) . '">' . e($page->name) . "</a></li>\n";
    }

    return $html;
}

/**
 * @todo Can't we do this in an observer class, just prior to saving? Except the `delete()` part ...
 */
function prepare_meta(array $keys, array $values, $metable): array
{
    $temp = [];

    foreach (array_combine($keys, $values) as $key => $value) {
        if (empty($key)) {
            continue;
        }

        if (empty($value)) {
            $metable->meta()->where('key', $key)->delete();
            unset($temp[$key]);
        } elseif (Str::isJson($value)) {
            // *Every* meta field is saved as an array, even if it's single-value.
            $temp[$key] = (array) json_decode($value, true);
        } else {
            $temp[$key] = (array) $value;
        }
    }

    return $temp;
}

function activitypub_fetch_webfinger(string $resource): ?string
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

    $response = Cache::remember("activitypub:webfinger:$resource", 60 * 60 * 6, function () use ($url) {
        return Http::withHeaders(['Accept' => 'application/jrd+json'])
            ->get($url)
            ->json();
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

function activitypub_fetch_profile(string $url, User $user = null): array
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

    $response = Cache::remember("activitypub:profile:$url", 60 * 60 * 6, function () use ($url, $user) {
        return Http::withHeaders(HttpSignature::sign(
            $user,
            $url,
            null,
            ['Accept' => 'application/activity+json, application/json'],
            'get'
        ))
        ->get($url)
        ->json();
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
            'url' => isset($response['url']) && filter_var($response['url'], FILTER_VALIDATE_URL)
                ? filter_var($response['url'], FILTER_SANITIZE_URL)
                : null,
            'inbox' => isset($response['inbox']) && filter_var($response['inbox'], FILTER_VALIDATE_URL)
                ? filter_var($response['inbox'], FILTER_SANITIZE_URL)
                : null,
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'shared_inbox' => isset($response['endpoints']['sharedInbox']) && filter_var($response['endpoints']['sharedInbox'], FILTER_VALIDATE_URL)
                ? filter_var($response['endpoints']['sharedInbox'], FILTER_SANITIZE_URL)
                : null,
            'outbox' => isset($response['outbox']) && filter_var($response['outbox'], FILTER_VALIDATE_URL)
                ? filter_var($response['outbox'], FILTER_SANITIZE_URL)
                : null,
            'key_id' => $response['publicKey']['id'] ?? null,
            'public_key' => $response['publicKey']['publicKeyPem'] ?? null,
        ]);
    }

    return [];
}

function activitypub_get_inbox(string $url): ?string
{
    $data = activitypub_fetch_profile($url);

    return $data['inbox'] ?? null;
}

function activitypub_object_to_id(mixed $object): ?string
{
    if (filter_var($object, FILTER_VALIDATE_URL)) {
        $id = filter_var($object, FILTER_SANITIZE_URL);
    } elseif (! empty($object['id']) && filter_var($object['id'], FILTER_VALIDATE_URL)) {
        $id = filter_var($object['id'], FILTER_SANITIZE_URL);
    }

    return isset($id) && is_string($id)
        ? $id
        : null;
}
