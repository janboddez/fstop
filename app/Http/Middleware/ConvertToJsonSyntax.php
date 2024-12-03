<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConvertToJsonSyntax
{
    /**
     * Convert Micropub *form* requests to JSON, so that we can treat them both the same inside our Micropub controller.
     *
     * Note that this means files can be uploaded only through the "media endpoint."
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isJson()) {
            $properties = [];

            // Convert form requests to "JSON," i.e., an array.
            foreach ($request->all() as $key => $value) {
                if ($key === 'h') {
                    $request->merge(['type' => ["h-$value"]]);
                } else {
                    $properties[$key] = (array) $value;
                }
            }
        } else {
            $properties = $request->input('properties');
        }

        /**
         * Handle HTML content. Reason we do this here and not in the controller is that it makes validation slightly
         * simpler.
         *
         * @link https://www.w3.org/TR/micropub/#new-article-with-html
         */
        $properties['content'] = (array) ($properties['content'][0]['html'] ?? $properties['content'] ?? null);

        $request->merge(['properties' => $properties]);

        return $next($request);
    }
}
