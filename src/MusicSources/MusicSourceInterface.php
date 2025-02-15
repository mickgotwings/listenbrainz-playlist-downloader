<?php declare(strict_types=1);

namespace App\MusicSources;

use App\Model\Download;
use App\Model\Track;
use App\MusicSources\Exception\DownloadException;
use App\MusicSources\Exception\TrackMismatchException;
use App\MusicSources\Exception\TrackNotFoundException;

interface MusicSourceInterface
{
    /**
     * @throws TrackMismatchException
     * @throws DownloadException
     * @throws TrackNotFoundException
     */
    public function grab(Track $track): Download;
}
