<?php declare(strict_types=1);

namespace App\MusicSources\YoutubeMusic;

use Ytmusicapi\SearchResult;

class YoutubeUrlBuilder
{
    private const string URL_MASK = 'https://www.youtube.com/watch?v=%s';

    /**
     * @param SearchResult $searchResult
     */
    public static function fromSearchResult(object $searchResult): string
    {
        return sprintf(self::URL_MASK, $searchResult->videoId);
    }
}
