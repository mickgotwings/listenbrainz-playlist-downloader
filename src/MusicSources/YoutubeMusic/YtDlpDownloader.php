<?php declare(strict_types=1);

namespace App\MusicSources\YoutubeMusic;

use App\Model\Download;
use App\MusicSources\Exception\DownloadException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
    ) {
        if (!file_exists($downloadPath)) {
            throw new InvalidArgumentException('Given download path does not exist');
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
        $maxSleepSeconds = $_ENV['YTDLP_MAX_SLEEP_SECONDS'] ?? 5;
        if ($maxSleepSeconds > 0) {
            $microseconds = rand(0, $maxSleepSeconds * 1_000_000);
            $this->logger->info(
                sprintf(
                    'Sleeping for %d seconds to mimic human behavior',
                    round($microseconds / 1_000_000, 3)
                )
            );
            usleep($microseconds);
        }

        $collection = $this->youtubeDl->download(
            Options::create()
                ->downloadPath($this->downloadPath)
                ->extractAudio(true)
                ->audioFormat('mp3')
                ->audioQuality('0') // best
                ->output('%(artist)s - %(track)s.%(ext)s')
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
