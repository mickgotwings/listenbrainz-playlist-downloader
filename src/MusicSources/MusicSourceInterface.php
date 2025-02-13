<?php declare(strict_types=1);

namespace App\MusicSources;

use App\Model\Download;
use App\Model\Track;

interface MusicSourceInterface
{
    public function grab(Track $track): Download;
}
