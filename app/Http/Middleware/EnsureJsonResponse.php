<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonResponse
{
    /**
     * "Trick" Laravel into providing a JSON response. Allows us to use `abort()` and the like.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('.well-known/webfinger', 'activitypub/*', 'indieauth/token*', 'micropub*', 'webmention*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
