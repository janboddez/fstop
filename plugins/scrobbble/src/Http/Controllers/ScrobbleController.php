<?php

namespace Plugins\Scrobbble\Http\Controllers;

use App\Models\Entry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use TorMorten\Eventy\Facades\Events as Eventy;

class ScrobbleController
{
    public function handshake(Request $request): Response
    {
        \Log::debug($request->all());

        abort_unless($request->filled('u'), 401, "FAILED\n", ['Content-Type' => 'text/plain; charset=UTF-8']);

        $user = User::where('email', $request->input('u'))
            ->first();

        abort_unless($user, 401, "FAILED\n", ['Content-Type' => 'text/plain; charset=UTF-8']);

        $sessionKey = $request->input('sk');
        $token = $request->input('a');
        $timestamp = $request->input('t');

        $authenticated = ($sessionKey && $this->webAuth($sessionKey, $user))
            || $this->standardAuth($token, $timestamp, $user);

        abort_unless($authenticated, 403, "FAILED\n", ['Content-Type' => 'text/plain; charset=UTF-8']);

        /** @todo Do we ever get a session key but no token? */

        $client = $request->input('c');
        $sessionId = md5($token . time());

        $result = DB::insert(
            'INSERT INTO scrobbble_sessions (session_id, client, expires, user_id) VALUES (?, ?, ?, ?)',
            [
                $sessionId,
                $client,
                now()->addMonth()->toDateTimeString(),
                $user->id,
            ]
        );

        abort_unless($result, 500, "FAILED\n", ['Content-Type' => 'text/plain; charset=UTF-8']);

        Log::info('[Scrobbble] Handshake succeeded.');

        $output = "OK\n" .
            "$sessionId\n" .
            url('scrobbble/v1/nowplaying') . "\n" .
            url('scrobbble/v1/submissions') . "\n";

        return response($output, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function now(Request $request): Response
    {
        if ($request->isMethod('get')) {
            // Return the "currently playing" track, if any.
            return response()->json(
                Cache::get('scrobbble:nowplaying', new \stdClass())
            );
        }

        \Log::debug($request->all());

        // Authenticate using session key.
        abort_unless($request->filled('s'), 401, "FAILED\n", ['Content-Type' => 'text/plain; charset=UTF-8']);

        $session = $this->getSession($request->input('s'));
        $userId = ! empty($session->user_id)
            ? (int) $session->user_id
            : 0;

        abort_unless($userId, 403, "FAILED\n", ['Content-Type' => 'text/plain; charset=UTF-8']);

        $title = $request->input('t');
        $artist = $request->input('a');
        $album = $request->input('b');
        $track = $request->filled('n') ? (int) $request->input('n') : null;
        $length = intval($request->input('l', 300));
        $mbid = $request->input('m');

        abort_unless(
            is_string($artist) && is_string($title),
            400,
            "FAILED\n",
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );

        $data = array_filter([
            'title' => Eventy::filter('scrobbble:title', strip_tags($title)),
            'artist' => Eventy::filter('scrobbble:artist', strip_tags($artist)),
            'album' => Eventy::filter('scrobbble:album', strip_tags($album)),
            'track' => Eventy::filter('scrobbble:track', strip_tags($track)),
            'mbid' => ! empty($mbid) ? $this->sanitizeMbid($mbid) : '',
        ]);

        if (Eventy::filter('scrobbble:skip_track', false, $data)) {
            Cache::forget('scrobbble:nowplaying'); // Just in case.
        } else {
            // Cache for the duration of the track, with a maximum of 90 minutes. (Default to 10 minutes.)
            Cache::put('scrobbble:nowplaying', $data, $length < 5400 ? $length : 600);
        }

        return response("OK\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function scrobble(Request $request): Response
    {
        \Log::debug($request->all());

        // Authenticate using session key.
        abort_unless($request->filled('s'), 401, "FAILED\n", ['Content-Type' => 'text/plain; charset=UTF-8']);

        $session = $this->getSession($request->input('s'));
        $userId = ! empty($session->user_id)
            ? (int) $session->user_id
            : 0;

        abort_unless($userId, 403, "FAILED\n", ['Content-Type' => 'text/plain; charset=UTF-8']);

        $artists = (array) $request->input('a', []);
        $titles = (array) $request->input('t', []);
        $albums = (array) $request->input('b', []);
        $tracks = (array) $request->input('n', []);
        $times = (array) $request->input('i', []);
        $mbids = (array) $request->input('m', []);

        abort_if(
            empty($artists) || empty($titles) || empty($times), // These are required.
            400,
            "FAILED\n",
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );

        $count = count($titles);

        for ($i = 0; $i < $count; $i++) {
            $title = Eventy::filter('scrobbble:title', strip_tags($titles[$i]));
            $artist = Eventy::filter('scrobbble:artist', strip_tags($artists[$i]));

            if (empty($title) || empty($artist)) {
                // If after filtering either of these is "empty," skip.
                continue;
            }

            $data = array_filter([
                'title'  => $title,
                'artist' => $artist,
                'album' => Eventy::filter('scrobbble:album', isset($albums[$i]) ? strip_tags($albums[$i]) : ''),
                'track' => Eventy::filter('scrobbble:track', isset($tracks[$i]) ? (int) $tracks[$i] : null),
                'mbid' => isset($mbids[$i]) ? $this->sanitizeMbid($mbids[$i]) : null,
                'time' => $times[$i],
            ]);

            if (Eventy::filter('scrobbble:skip_track', false, $data)) {
                // Skip this track.
                continue;
            }

            $this->createEntry($data, $session);
        }

        return response("OK\n", 201, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    protected function createEntry(array $data, object $session): void
    {
        // Generate content off the title and artist (and album) data.
        if (! empty($data['album'])) {
            $artistHtml = '<span class="p-author h-card"><span class="p-name">' .
                    e($data['artist']) .
                '</span></span><span class="sr-only"> (' . e($data['album']) . ')</span>';
        } else {
            $artistHtml = '<span class="p-author h-card"><span class="p-name">' .
                    e($data['artist']) .
                '</span></span>';
        }

        $content = __('Listening to :title by :artist.', [
            'title' => '<cite class="p-name">' . e($data['title']) . '</cite>',
            'artist' => $artistHtml,
        ]);

        // Make filterable.
        $content = Eventy::filter(
            'scrobbble:content',
            '<span class="p-listen-of h-cite">' . $content . '</span>',
            $data
        );

        // `$data['time']` oughta be GMT, but our site (and database) may not be.
        $time = isset($data['time'])
            ? Carbon::createFromTimestamp($data['time'], config('app.timezone', env('APP_TIMEZONE', 'UTC')))
                ->toDateTimeString()
            : now();

        // Avoid duplicates, so we don't have to rely on clients for this.
        if (Entry::where('content', $content)->where('created_at', $time)->exists()) {
            Log::warning('[Scrobbble] Listen already exists');
            return;
        }

        $entry = Entry::create([
            'content' => $content,
            'type' => 'listen',
            'status' => 'published',
            'user_id' => $session->user_id,
            'created_at' => $time,
        ]);

        if (isset($data['mbid'])) {
            $entry->meta()->updateOrCreate(
                ['key' => 'mbid'],
                ['value' => (array) $data['mbid']]
            );
        }
    }

    protected function webAuth(
        ?string $sessionKey,
        User $user
    ): bool {
        if (! $sessionKey) {
            return false;
        }

        /** @todo Hash these keys, or even use Sanctum. */

        $session = $this->getSession($sessionKey);
        if (! empty($session->user_id) && intval($session->user_id) === $user->id) {
            return true;
        }

        return false;
    }

    protected function standardAuth(
        ?string $token,
        ?string $timestamp,
        User $user
    ): bool {
        /** @todo Allow per-user "passwords." */
        $password = config('scrobbble.password', env('SCROBBBLE_PASS', null));

        if (empty($password)) {
            Log::warning('[Scrobbble] No password in config. Check cache?');
            return false;
        }

        return md5(md5($password) . $timestamp) === $token;
    }

    protected function getSession(string $sessionKey): ?object
    {
        // Delete expired sessions.
        DB::delete(
            'DELETE FROM scrobbble_sessions WHERE expires < ?',
            [now()->toDateTimeString()]
        );

        $session = DB::table('scrobbble_sessions')
            ->where('session_id', $sessionKey)
            ->first();

        if (! $session) {
            Log::error('[Scrobbble] User has no active sessions');
            return null;
        }

        return $session;
    }

    protected function sanitizeMbid(string $mbid): ?string
    {
        $mbid = strtolower(trim($mbid));

        if (preg_match('~^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$~D', $mbid)) {
            return $mbid;
        }

        return null;
    }
}
