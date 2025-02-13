<?php declare(strict_types=1);

namespace App\ApiWrapper\Model;

use Ramsey\Uuid\UuidInterface;

readonly class TrackModel
{
    public function __construct(
        public UuidInterface $uuid,
        public string $artist,
        public string $album,
        public string $title,
    ) {}
}
