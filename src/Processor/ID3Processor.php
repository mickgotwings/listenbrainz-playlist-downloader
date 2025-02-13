<?php declare(strict_types=1);

namespace App\Processor;

use App\Model\Download;
use App\Model\Track;
use Kiwilan\Audio\Audio;

class ID3Processor
{
    public function fillTags(Download $download, Track $track): void
    {
        $handle = Audio::read($download->audioPath)->write();

        $handle->title($track->title)
            ->artist($track->artist)
            ->album($track->album)
            ->albumArtist($track->artist);

        if (isset($download->imgPath)) {
            $handle->cover($download->imgPath);
        }

        $handle->save();
    }
}
