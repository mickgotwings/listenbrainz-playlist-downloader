<?php declare(strict_types=1);

namespace App\MusicSources\YoutubeMusic;

use App\Model\Download;
use App\MusicSources\Exception\DownloadException;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;
use Ytmusicapi\SearchResult;

class YtDlpDownloader
{
    private readonly string $downloadPath;

    public function __construct(
        string $downloadPath,
        private readonly YoutubeDl $youtubeDl,
    ) {
        if (!file_exists($downloadPath)) {
            mkdir($downloadPath, recursive: true);
        }

        if (!is_dir($downloadPath)) {
            throw new InvalidArgumentException('Given download path is not a directory');
        }

        $this->downloadPath = realpath($downloadPath);
    }

    /**
     * @param SearchResult $searchResult
     * @throws DownloadException
     */
    public function download(object $searchResult): Download
    {
        // mimicking human behavior
        //$microseconds = rand(0, 1_000_000);
        $microseconds = 0;
        echo "Sleeping for $microseconds microseconds...\n";
        usleep($microseconds);

        $collection = $this->youtubeDl->download(
            Options::create()
                ->downloadPath($this->downloadPath)
                ->extractAudio(true)
                ->audioFormat('mp3')
                ->audioQuality('0') // best
                ->output('%(artist)s - %(track)s.%(ext)s')
                //->output('%(title)s.%(ext)s')
                ->url(YoutubeUrlBuilder::fromSearchResult($searchResult))
        );

        foreach ($collection->getVideos() as $video) {
            if ($video->getError() !== null) {
                throw new DownloadException($video->getError());
            } else {
                $file = $video->getFile();
                break;
            }
        }

        if (empty($file)) {
            throw new DownloadException();
        }

        return new Download(
            $file->getRealPath(),
            $this->downloadCover($searchResult),
        );
    }

    /**
     * @param SearchResult $searchResult
     */
    private function downloadCover(object $searchResult): ?string
    {
        if (empty($searchResult->thumbnails)) {
            return null;
        }

        $coverFileName = Uuid::uuid4()->toString() . '.jpg';
        $coverPath = $this->downloadPath . '/' . $coverFileName;

        file_put_contents(
            $coverPath,
            file_get_contents(
                $searchResult->thumbnails[0]->url
            )
        );

        return $coverPath;
    }
}
