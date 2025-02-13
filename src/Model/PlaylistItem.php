<?php declare(strict_types=1);

namespace App\Model;

readonly class PlaylistItem
{
    public function __construct(
        public Track $track,
        public Download $download,
    ) {}
}
