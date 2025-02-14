<?php declare(strict_types=1);

namespace App\PlaylistGenerator;

use App\Model\Playlist;
use App\PlaylistGenerator\Exception\GenerationException;
use InvalidArgumentException;
use M3uParser\M3uData;
use M3uParser\M3uEntry;
use M3uParser\Tag;

class PlaylistGenerator
{
    private readonly string $path;

    public function __construct(
        string $path,
    ) {
        if (!file_exists($path)) {
            throw new InvalidArgumentException('Given download path does not exist');
        }

        if (!is_dir($path)) {
            throw new InvalidArgumentException('Given download path is not a directory');
        }

        $this->path = rtrim(realpath($path), '/');
    }

    public function generate(Playlist $playlist): void
    {
        $m3u = new M3uData();

        $m3u->append(
            new Tag\Playlist()
            ->setValue($playlist->title)
        );

        foreach ($playlist->items as $item) {
            $filePath = realpath($item->download->audioPath);
            if (str_starts_with($filePath, $this->path)) {
                $filePath = substr($filePath, strlen($this->path));
            }

            $m3u->append(
                new M3uEntry()
                ->setPath($filePath)
            );
        }

        if (!$m3u->valid()) {
            throw new GenerationException('Playlist is invalid');
        }

        file_put_contents(
            sprintf('%s/%s.m3u', $this->path, $playlist->title),
            (string) $m3u
        );
    }
}
