<?php declare(strict_types=1);

namespace App\Model;

readonly class Playlist
{
    /**
     * @param PlaylistItem[] $items
     */
    public function __construct(
        public string $title,
        public array $items,
    ) {}
}
