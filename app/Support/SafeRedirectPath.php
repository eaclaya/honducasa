<?php

namespace App\Support;

class SafeRedirectPath
{
    /**
     * A same-origin relative path, or null if the given value isn't one.
     *
     * Lets a client (e.g. a login/register modal shown mid-browse) say
     * "come back here" without becoming an open redirect: anything that
     * isn't a plain path starting with a single `/` is rejected.
     */
    public static function resolve(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if ($path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '://')) {
            return null;
        }

        return $path;
    }
}
