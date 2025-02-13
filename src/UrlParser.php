<?php declare(strict_types=1);

namespace App;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UrlParser
{
    public function parseTrailingUuid(string $url): UuidInterface
    {
        return Uuid::fromString(
            substr(
                $url,
                strrpos($url, '/') + 1
            )
        );
    }
}
