<?php

namespace App\Http\Controllers\ActivityPub;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebFingerController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($resource = $request->input('resource'), 400);

        $resource = ltrim(Str::replaceStart('acct:', '', $resource), '@');

        if (filter_var($resource, FILTER_VALIDATE_EMAIL) && $pos = strpos($resource, '@')) {
            $host = substr($resource, $pos + 1);
            $login = substr($resource, 0, $pos);
        } elseif (preg_match('~https?://([^/]+)/(?:users|author)/(.*)~', $resource, $matches)) {
            $host = $matches[1];
            $login = $matches[2];
        }

        abort_if(empty($login), 400, __('Unknown resource'));
        abort_unless($user = User::where('login', $login)->first(), 400, __('Unknown resource'));

        abort_if(empty($host), 400, __('Invalid host name'));
        abort_unless($host === parse_url(url('/'), PHP_URL_HOST), 400, __('Invalid host name'));

        $output = [
            'subject' => sprintf('acct:%s@%s', $login, $host),
            'aliases' => [
                $user->actor_url,
                $user->author_url,
                url("author/{$login}"), // "Legacy."
            ],
            'links' => [
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $user->actor_url,
                ],
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $user->author_url,
                ],
            ],
        ];

        if (Str::isUrl($user->avatar, ['http', 'https'])) {
            $output['links'][] = [
                'rel' => 'http://webfinger.net/rel/avatar',
                'type' => ($attachment = url_to_attachment($user->avatar))
                    ? $attachment->mime_type
                    : 'application/octet-stream',
                'href' => $user->avatar,
            ];
        }

        return $output;
    }
}
