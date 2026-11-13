<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Support;

/**
 * Reads the bundled CSS/JS so pages work without a build step or published
 * assets. A file published to resources/vendor/connex/ takes precedence.
 */
final class Assets
{
    public static function inline(string $path): string
    {
        $published = resource_path('vendor/connex/'.$path);
        $file = is_file($published) ? $published : dirname(__DIR__, 2).'/resources/'.$path;

        return (string) file_get_contents($file);
    }
}
