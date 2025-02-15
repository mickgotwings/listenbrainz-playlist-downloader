<?php declare(strict_types=1);

namespace App;

use App\ApiWrapper\ListenbrainzPlaylistApiWrapper;
use App\ApiWrapper\Model\ApiPlaylist;
use App\Model\Playlist;
use App\Model\PlaylistItem;
use App\Model\Track;
use App\MusicSources\Exception\DownloadException;
use App\MusicSources\Exception\TrackMismatchException;
use App\MusicSources\Exception\TrackNotFoundException;
use App\MusicSources\MusicSourceInterface;
use App\MusicSources\YoutubeMusic\YoutubeMusicSource;
use App\MusicSources\YoutubeMusic\YtDlpDownloader;
use App\PlaylistGenerator\PlaylistGenerator;
use App\Processor\ID3Processor;
use Dotenv\Dotenv;
use Exception;
use Listenbrainz\Api\LbPlaylistsApi;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use YoutubeDl\YoutubeDl;
use Ytmusicapi\YTMusic;

class Application
{
    private readonly LoggerInterface $logger;
    private readonly ListenbrainzPlaylistApiWrapper $lbApi;
    private readonly ID3Processor $id3Processor;

    public function __construct(Dotenv $env)
    {
        $this->ensureValidEnv($env);

        $this->logger = new Logger('lbdl');
        $this->logger->pushHandler(
            new StreamHandler(
                'php://stderr',
                ($_ENV['DEBUG'] ?? false) === true ? Level::Debug : Level::Warning
            )
        );
        $this->logger->pushHandler(
            new StreamHandler(
                'php://stdout',
                ($_ENV['DEBUG'] ?? false) === true ? Level::Debug : Level::Info
            )
        );

        $this->lbApi = new ListenbrainzPlaylistApiWrapper(
            new LbPlaylistsApi(),
            new UrlParser(),
        );

        $this->id3Processor = new ID3Processor();
    }

    private function ensureValidEnv(Dotenv $env): void
    {
        $env->required(['LISTENBRAINZ_USERNAME'])->notEmpty();

        $env->ifPresent('YTDLP_MAX_SLEEP_SECONDS')->isInteger();

        $env->ifPresent('DOWNLOAD_PATH')
            ->assertNullable(
                fn ($path) => file_exists($path) && is_dir($path),
                'Download path does not exist or you do not have access to it'
            );

        $env->ifPresent('TRY_SONGS')->isInteger();
    }

    public function run(): void
    {
        foreach ($this->fetchPlaylists() as $apiPlaylist) {
            $this->processPlaylist($apiPlaylist);
        }

        $this->logger->info('Done');
    }

    private function fetchPlaylists(): iterable
    {
        return $this->lbApi->getCreatedFor($_ENV['LISTENBRAINZ_USERNAME']);
    }

    private function fetchPlaylistTracks(UuidInterface $playlistUuid): iterable
    {
        $apiTracks = $this->lbApi->getTracks($playlistUuid);
        foreach ($apiTracks as $apiTrack) {
            yield new Track(
                $apiTrack->artist,
                $apiTrack->album,
                $apiTrack->title,
            );
        }
    }

    /**
     * @param string $downloadPath
     * @return MusicSourceInterface[]
     */
    private function buildSources(string $downloadPath): array
    {
        return [
            new YoutubeMusicSource(
                new YTMusic(),
                new YtDlpDownloader(
                    $downloadPath,
                    new YoutubeDl(),
                    $this->logger
                )
            )
        ];
    }

    private function processPlaylist(ApiPlaylist $playlist): void
    {
        $this->logger->info("Processing playlist $playlist->name");

        $playlistPath = sprintf(
            '%s/%s',
            realpath($_ENV['DOWNLOAD_PATH'] ?? '/tmp/music'),
            FilenameSanitizer::sanitize($playlist->name)
        );

        if (file_exists($playlistPath)) {
            $this->logger->info("Skipping, since this playlist already exists in the download directory");
            return;
        }

        mkdir($playlistPath);

        $sources = $this->buildSources($playlistPath);

        $playlistItems = [];

        foreach ($this->fetchPlaylistTracks($playlist->uuid) as $track) {
            foreach ($sources as $source) {
                try {
                    $this->logger->info("Downloading $track->artist - $track->title ($track->album)");

                    $download = $source->grab($track);

                    $this->id3Processor->fillTags($download, $track);

                    $playlistItems[] = new PlaylistItem(
                        $track,
                        $download,
                    );
                } catch (TrackNotFoundException|TrackMismatchException|DownloadException $e) {
                    $this->logDownloadException($e);
                }
            }
        }

        $this->logger->info("Generating playlist file for $playlist->name");

        $playlistGenerator = new PlaylistGenerator($playlistPath);

        $playlistGenerator->generate(
            new Playlist(
                $playlist->name,
                $playlistItems,
            )
        );
    }

    private function logDownloadException(Exception $exception): void
    {
        switch (get_class($exception)) {
            case TrackNotFoundException::class:
                $this->logger->error(
                    'Track not found',
                    ['exception' => $exception]
                );
                break;
            case TrackMismatchException::class:
                $this->logger->error(
                    'Got a mismatched track',
                    ['exception' => $exception]
                );
                break;
            case DownloadException::class:
                $this->logger->error(
                    'Could not download track',
                    ['exception' => $exception]
                );
                break;
            default:
                $this->logger->critical(
                    'Unknown error',
                    ['exception' => $exception]
                );
        }
    }
}
