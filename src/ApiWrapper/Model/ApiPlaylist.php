<?php declare(strict_types=1);

namespace App\ApiWrapper\Model;

use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;

readonly class ApiPlaylist
{
    public function __construct(
        public UuidInterface $uuid,
        public string $name,
        public string $description,
        public DateTimeInterface $createdAt,
        public ?string $type,
    ) {}
}
