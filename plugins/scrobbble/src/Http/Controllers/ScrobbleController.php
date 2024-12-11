<?php

namespace Plugins\Scrobbble\Http\Controllers;

use App\Models\Entry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TorMorten\Eventy\Facades\Events as Eventy;

class ScrobbleController
{
    /**
     * @todo Return proper Laravel responses.
     */
    public function handshake(Request $request)
    {
        header('Content-Type: text/plain; charset=UTF-8');

        if (! $username = $request->input('u')) {
            Log::error('[Scrobbble] Missing username');
            die("FAILED\n");
        }

        $user = User::where('email', $username)
            ->first();

        if (! $user) {
            Log::error('[Scrobbble] Invalid username.');
            die("FAILED\n");
        }

        $timestamp = $request->input('t');
        $token = $request->input('a');
        $client = $request->input('c');
        $sessionKey = $request->input('sk');

        $authenticated = false;
        if ($sessionKey && $this->checkWebAuth($sessionKey, $user)) {
            $authenticated = true;
        } elseif ($this->checkStandardAuth($token, $timestamp, $user)) {
            $authenticated = true;
        }

        if (! $authenticated) {
            Log::error('[Scrobbble] Authentication failed.');
            die("FAILED\n");
        }

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

        if (! $result) {
            Log::error('[Scrobbble] Handshake failed.');
            die("FAILED\n");
        }

        Log::info('[Scrobbble] Handshake succeeded.');

        echo "OK\n";
        echo "$sessionId\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo url('scrobbble/v1/nowplaying') . "\n";
        echo url('scrobbble/v1/submissions') . "\n";
        exit;
    }

    /**
     * @todo Return proper Laravel responses.
     */
    public function now(Request $request)
    {
        if ($request->isMethod('get')) {
            return response()->json(
                Cache::get('scrobbble:nowplaying', [])
            );
        }

        header('Content-Type: text/plain; charset=UTF-8');

        $sessionKey = $request->input('s');
        $title = $request->input('t');
        $artist = $request->input('a');
        $album = $request->input('b');
        $mbid = $request->input('m');
        $length = intval($request->input('l', 300));

        $session = $this->getSession($sessionKey);
        $userId = ! empty($session->user_id)
            ? $session->user_id
            : 0;

        if ($userId === 0) {
            Log::error('[Scrobbble] Invalid session key');
            die("FAILED\n");
        }

        \Log::debug('Now playing ...');
        \Log::debug($request->all());

        if (empty($artist) || empty($title)) {
            Log::error('[Scrobbble] Incomplete scrobble');
            die("FAILED\n");
        }

        if (! is_string($artist) || ! is_string($title)) {
            Log::error('[Scrobbble] Incorrectly formatted data');
            die("FAILED\n");
        }

        $data = array_filter([
            'title' => Eventy::filter('scrobbble:title', strip_tags($title)),
            'artist' => Eventy::filter('scrobbble:artist', strip_tags($artist)),
            'album' => Eventy::filter('scrobbble:album', strip_tags($album)),
            'mbid' => ! empty($mbid) ? static::sanitizeMbid($mbid) : '',
        ]);

        if (Eventy::filter('scrobbble:skip_track', false, $data)) {
            Cache::forget('scrobbble:nowplaying');
        }

        Cache::put('scrobbble:nowplaying', $data, $length < 5400 ? $length : 600);

        die("OK\n");
    }

    /**
     * @todo Return proper Laravel responses.
     */
    public function scrobble(Request $request)
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $sessionKey = $request->input('s');
        $titles = $request->input('t', []);
        $artists = $request->input('a', []);
        $albums = $request->input('b', []);
        $mbids = $request->input('m', []);
        $times = $request->input('i', []);

        $session = $this->getSession($sessionKey);

        $userId = ! empty($session->user_id)
            ? $session->user_id
            : 0;

        if ($userId === 0) {
            Log::error('[Scrobbble] Invalid session key');
            die("FAILED\n");
        }

        \Log::debug('Scrobbling ...');
        \Log::debug($request->all());

        if (empty($artists) || empty($titles) || empty($times)) {
            Log::error('[Scrobbble] Incomplete scrobble');
            die("FAILED\n");
        }

        // phpcs:ignore Generic.Files.LineLength.TooLong
        if (! is_array($artists) || ! is_array($titles) || ! is_array($times) || ! is_array($albums) || ! is_array($mbids)) {
            Log::error('[Scrobbble] Incorrectly formatted data');
            die("FAILED\n");
        }

        $count = count($titles);

        for ($i = 0; $i < $count; $i++) {
            $title = Eventy::filter('scrobbble:title', strip_tags($titles[$i]));
            $artist = Eventy::filter('scrobbble:artist', strip_tags($artists[$i]));

            if (empty($title) || empty($artist)) {
                // Skip.
                continue;
            }

            $data = array_filter([
                'title'  => $title,
                'artist' => $artist,
                'album' => Eventy::filter('scrobbble:album', strip_tags($albums[$i])),
                'mbid' => isset($mbids[$i]) ? $this->sanitizeMbid($mbids[$i]) : null,
                'time' => $times[$i] ?? time(),
            ]);

            if (Eventy::filter('scrobbble:skip_track', false, $data)) {
                // Skip this track.
                continue;
            }

            $this->createEntry($data, $session);
        }

        die("OK\n");
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

    protected function checkWebAuth(
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

    protected function checkStandardAuth(
        ?string $token,
        ?string $timestamp,
        User $user
    ): bool {
        /** @todo Allow per-user "passwords." */
        $password = config('scrobbble.password', env('SCROBBBLE_PASS', null));

        if (empty($password)) {
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
