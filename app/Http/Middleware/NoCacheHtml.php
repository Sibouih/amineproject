<?php

namespace App\Http\Middleware;

use Closure;

/**
 * HTML pages (the app shell, the login page) are never cached by the browser:
 * they always point to the latest JS/CSS files (versioned by upload time in the templates).
 * An Expires header from the app also stops Apache's mod_expires (".htaccess": text/html 1 hour) from adding its own.
 */
class NoCacheHtml
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $type = (string) $response->headers->get('Content-Type');
        if ($type === '' || stripos($type, 'text/html') !== false) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        }

        return $response;
    }
}
