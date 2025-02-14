<?php declare(strict_types=1);

namespace App;

class FilenameSanitizer
{
    public static function sanitize(string $fileName): string
    {
        return str_replace(
            array_merge(
                array_map('chr', range(0, 31)),
                array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
            ),
            '',
            $fileName
        );
    }
}
