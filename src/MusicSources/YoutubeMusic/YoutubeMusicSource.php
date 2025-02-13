<?php declare(strict_types=1);

namespace App\MusicSources\YoutubeMusic;

use App\Model\Download;
use App\Model\Track;
use App\MusicSources\Exception\TrackNotFoundException;
use App\MusicSources\MusicSourceInterface;
use stdClass;
use Ytmusicapi\SearchResult;
use Ytmusicapi\YTMusic;

class YoutubeMusicSource implements MusicSourceInterface
{
    public function __construct(
        private readonly YTMusic $ytmusicClient,
        private readonly YtDlpDownloader $ytDlpDownloader,
    ) {}

    public function grab(Track $track): Download
    {
        $results = $this->ytmusicClient->search(
            query: (string) $track,
            filter: 'songs',
            limit: 5,
        );

        if (empty($results)) {
            throw new TrackNotFoundException();
        }

        foreach ($results as $result) {
            if ($this->checkTrackMatch($result, $track)) {
                $match = $result;
                break;
            }
        }

        if (!isset($match)) {
            throw new TrackNotFoundException();
        }

        return $this->ytDlpDownloader->download($match);
    }

    /**
     * @param SearchResult $searchResult
     */
    private function checkTrackMatch(object $searchResult, Track $track): bool
    {
        if ($searchResult->resultType !== 'song') {
            return false;
        }
        if (mb_strtolower($searchResult->title) !== mb_strtolower($track->title)) {
            return false;
        }
        if (mb_strtolower($searchResult->artists[0]->name) !== mb_strtolower($track->artist)) {
            return false;
        }

        return true;
    }
}
