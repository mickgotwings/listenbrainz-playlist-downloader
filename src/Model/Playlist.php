<?php declare(strict_types=1);

namespace App\Model;

class Playlist
{
    private string $path;

    /**
     * @param PlaylistItem[] $items
     */
    public function __construct(
        public readonly string $title,
        public readonly array $items,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}
