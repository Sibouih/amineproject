<?php

namespace App\Http\Controllers;

/**
 * Build of the front end currently on the server (upload time of public/js/main.min.js).
 * Open tabs compare it with the build they loaded and offer to reload when a new version is deployed.
 */
class AppVersionController extends Controller
{
    public function show()
    {
        $build = @filemtime(public_path('js/main.min.js'));

        return response()
            ->json(['build' => $build ? (string) $build : ''])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }
}
