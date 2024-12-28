<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonResponse
{
    /**
     * "Trick" Laravel into providing a JSON response. Allows us to use `abort()` and the like.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('indieauth/token*', 'micropub*', 'webmention*', 'activitypub*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
