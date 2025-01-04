<?php

namespace Plugins\Scrobbble\Http\Controllers\V2;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Plugins\Scrobbble\Http\Controllers\ScrobbleController as BaseController;
use Symfony\Component\HttpFoundation\Response;
use TorMorten\Eventy\Facades\Events as Eventy;

class ScrobbleController extends BaseController
{
    public function handle(Request $request): Response
    {
        \Log::debug($request->all());

        if (! is_string($method = $request->input('method'))) {
            Log::error('[Scrobbble/V2] Missing or invalid method');

            return response()->json([
                'error' => 3,
                'message' => 'Invalid Method - No method with that name in this package',
            ]);
        }

        $method = str_replace('.', '_', strtolower($method));
        $method = Str::camel($method);

        if (empty($method) || ! method_exists(__CLASS__, $method)) {
            Log::error('[Scrobbble/V2] Unsupported method');

            return response()->json([
                'error' => 3,
                'message' => 'Invalid Method - No method with that name in this package',
            ]);
        }

        return $this->$method($request);
    }

    protected function authGetmobilesession(Request $request): Response
    {
        if (! is_string($password = $request->input('password'))) {
            Log::error('[Scrobbble/V2] Invalid login parameters');

            return response()->json([
                'error' => 6,
                'message' => 'Invalid parameters - Your request is missing a required parameter',
            ]);
        }

        $user = User::where('email', $request->input('username'))
            ->first();

        if (! $user) {
            Log::error('[Scrobbble/V2] Invalid username');

            return response()->json([
                'error' => 7,
                'message' => 'Invalid resource specified',
            ]);
        }

        if (! $this->auth($user, $password)) {
            // Invalid username or password.
            return response()->json([
                'error' => 4,
                'message' => 'Invalid authentication token supplied',
            ]);
        }

        $sessionKey = md5(time() . mt_rand());

        $result = DB::insert(
            'INSERT INTO scrobbble_sessions (session_id, client, expires, user_id) VALUES (?, ?, ?, ?)',
            [
                $sessionKey,
                ($apiKey = $request->input('api_key')) && is_string($apiKey) ? strip_tags($apiKey) : null,
                now()->addYear()->toDateTimeString(),
                $user->id,
            ]
        );

        if (! $result) {
            // return response("FAILED\n", 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response()->json([
            'session' => [
                'name' => $user->email,
                'key' => $sessionKey,
                'subscriber' => 0,
            ],
        ]);
    }

    protected function trackUpdatenowplaying(Request $request): Response
    {
        $sessionKey = $request->input('sk');
        $title = $request->input('track');
        $artist = $request->input('artist');
        $album = $request->input('album');
        $length = intval($request->input('duration', 300));

        $session = $this->getSession($sessionKey);
        if (empty($session->user_id)) {
            Log::error('[Scrobbble/2.0] Could not find user by session key');

            return response()->json([
                'error' => 9,
                'message' => 'Invalid session key - Please re-authenticate',
            ]);
        }

        if (empty($artist) || empty($title) || ! is_string($artist) || ! is_string($title)) {
            Log::error('[Scrobbble/2.0] Invalid format');

            return response()->json([
                'error' => 6,
                'message' => 'Invalid parameters - Your request is missing a required parameter',
            ]);
        }

        $data = array_filter([
            'title' => Eventy::filter('scrobbble:title', strip_tags($title)),
            'artist' => Eventy::filter('scrobbble:artist', strip_tags($artist)),
            'album' => Eventy::filter('scrobbble:album', strip_tags($album)),
            'mbid' => ! empty($mbid) ? $this->sanitizeMbid($mbid) : null,
        ]);

        if (Eventy::filter('scrobbble:skip_track', false, $data)) {
            Cache::forget('scrobbble:nowplaying'); // Just in case.
        } else {
            Cache::put('scrobbble:nowplaying', $data, $length < 5400 ? $length : 600);
        }

        return response()->json([
            'nowplaying' => array_filter([
                'track' => [
                    '#text' => $title,
                    'corrected' => $title,
                ],
                'artist' => [
                    '#text' => $artist,
                    'corrected' => $artist,
                ],
                'album' => [
                    '#text' => $album,
                    'corrected' => $album,
                ],
                'ignoredMessage' => [
                    '#text' => '',
                    'code' => 0,
                ],
            ]),
        ]);
    }

    protected function trackScrobble(Request $request): Response
    {
        $sessionKey = $request->input('sk');
        $artists = (array) $request->input('artist');
        $titles = (array) $request->input('track');
        $albums = (array) $request->input('album');
        $tracks = (array) $request->input('tracknumber');
        $times = (array) $request->input('timestamp');
        $mbids = (array) $request->input('mbid');

        $session = $this->getSession($sessionKey);
        if (empty($session->user_id)) {
            Log::error('[Scrobbble/2.0] Could not find user by session ID');

            return response()->json([
                'error' => 9,
                'message' => 'Invalid session key - Please re-authenticate',
            ]);
        }

        if (empty($artists) || empty($titles) || empty($times)) {
            Log::error('[Scrobbble/2.0] Incomplete scrobble');

            return response()->json([
                'error' => 6,
                'message' => 'Invalid parameters - Your request is missing a required parameter',
            ]);
        }

        $count = count($titles);
        $accepted = 0;
        $ignored = 0;
        $tracks = [];

        for ($i = 0; $i < $count; $i++) {
            $title = Eventy::filter('scrobbble:title', strip_tags($titles[$i]));
            $artist = Eventy::filter('scrobbble:artist', strip_tags($artists[$i]));

            if (empty($title) || empty($artist)) {
                // If after filtering either of these is "empty," skip.
                continue;
            }

            $data = array_filter([
                'title' => $title,
                'artist' => $artist,
                'album' => Eventy::filter('scrobbble:album', isset($albums[$i]) ? strip_tags($albums[$i]) : ''),
                'track' => Eventy::filter('scrobbble:track', isset($tracks[$i]) ? (int) $tracks[$i] : 0),
                'mbid' => isset($mbids[$i]) ? $this->sanitizeMbid($mbids[$i]) : null,
                'time' => $times[$i],
            ]);

            // Of course, the 2.0 API expects a much more elaborate response.
            $track = [
                'track' => [
                    '#text' => $title,
                    'corrected' => $title,
                ],
                'artist' => [
                    '#text' => $artist,
                    'corrected' => $artist,
                ],
                'album' => [
                    '#text' => $data['album'],
                    'corrected' => $data['album'],
                ],
                'timestamp' => $times[$i],
                'ignoredMessage' => [
                    '#text' => '',
                    'code' => 0,
                ],
            ];

            if (Eventy::filter('scrobbble:skip_track', false, $data)) {
                // Skip this track.
                ++$ignored;

                $track['ignoredMessage'] = [
                    '#text' => 'Track was ignored',
                    'code' => 2,
                ];
            } else {
                // Save scrobble.
                $result = $this->createEntry($data, $session);

                // Avoid duplicates, so we don't have to rely on clients for this.
                if (is_int($result)) {
                    ++$accepted;
                } elseif ($result === 'duplicate') {
                    // Duplicate.
                    ++$ignored;

                    $track['ignoredMessage'] = [
                        '#text' => 'Already scrobbled',
                        'code' => 91,
                    ];
                } else {
                    // Something else went wrong.
                    ++$ignored;

                    $track['ignoredMessage'] = [
                        '#text' => 'Service temporary unavailable', // I mean, what do we do?
                        'code' => 16,
                    ];
                }
            }

            $tracks[] = $track;
        }

        return response()->json([
            'scrobbles' => [
                'scrobble' => count($tracks) === 1 ? $tracks[0] : $tracks,
                '@attr' => [
                    'accepted' => $accepted,
                    'ignored' => $ignored,
                ],
            ],
        ]);
    }

    /**
     * @todo Implement per-user authentication. We could store a (hashed) `scrobbble_pass` in user meta and compare
     *       against that.
     */
    protected function auth(User $user, string $password): bool
    {
        $storedPassword = config('scrobbble.password', env('SCROBBBLE_PASS', null));

        if (empty($storedPassword)) {
            Log::warning('[Scrobbble] No password in config. Check cache?');

            return false;
        }

        return $password === $storedPassword;
    }
}
