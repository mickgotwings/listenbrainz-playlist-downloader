<?php declare(strict_types=1);

namespace App\Model;

use Stringable;

readonly class Track implements Stringable
{
    public function __construct(
        public string $artist,
        public string $album,
        public string $title,
    ) {}

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->artist, $this->title);
    }
}
