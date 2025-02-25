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
    private const TMP_PATH = '/tmp/music';
    private const DESTINATION_PATH = '/music';

    private readonly LoggerInterface $logger;
    private readonly ListenbrainzPlaylistApiWrapper $lbApi;
    private readonly ID3Processor $id3Processor;
    private readonly PlaylistGenerator $playlistGenerator;
    private readonly iterable $sources;

    public function __construct(Dotenv $env)
    {
        $this->ensureValidEnv($env);

        $this->logger = new Logger('lbdl');
        $this->logger->pushHandler(
            new StreamHandler(
                'php://stdout',
                ($_ENV['DEBUG'] ?? 'false') === 'true' ? Level::Debug : Level::Info
            )
        );

        $this->lbApi = new ListenbrainzPlaylistApiWrapper(
            new LbPlaylistsApi(),
            new UrlParser(),
        );

        $this->id3Processor = new ID3Processor();

        $this->playlistGenerator = new PlaylistGenerator(self::TMP_PATH);

        $this->sources = $this->buildSources();
    }

    private function ensureValidEnv(Dotenv $env): void
    {
        $env->required(['LISTENBRAINZ_USERNAME'])->notEmpty();

        $env->ifPresent('YTDLP_MAX_SLEEP_SECONDS')->isInteger();

        $env->ifPresent('TRY_SONGS')->isInteger();

        $env->ifPresent('DEBUG')->isBoolean();
    }

    /**
     * @return MusicSourceInterface[]
     */
    private function buildSources(): array
    {
        return [
            new YoutubeMusicSource(
                new YTMusic(),
                new YtDlpDownloader(
                    self::TMP_PATH,
                    new YoutubeDl(),
                    $this->logger
                )
            )
        ];
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

    private function processPlaylist(ApiPlaylist $apiPlaylist): void
    {
        $this->logger->info("Processing playlist $apiPlaylist->name");

        if ($this->checkIfPlaylistExists($apiPlaylist->name)) {
            $this->logger->info("Skipping, since this playlist already exists in the download directory");
            return;
        }

        $playlistItems = [];

        foreach ($this->fetchPlaylistTracks($apiPlaylist->uuid) as $track) {
            foreach ($this->sources as $source) {
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

        $this->logger->info("Generating playlist file for $apiPlaylist->name");

        $playlist = new Playlist(
            $apiPlaylist->name,
            $playlistItems,
        );

        $this->playlistGenerator->generate($playlist);

        $this->movePlaylist($playlist);
    }

    private function checkIfPlaylistExists(string $playlistTitle): bool
    {
        $path = sprintf(
            '%s/%s',
            self::DESTINATION_PATH,
            FilenameSanitizer::sanitize($playlistTitle)
        );

        $this->logger->debug("Checking if playlist exists at $path");

        return file_exists($path);
    }

    private function movePlaylist(Playlist $playlist): void
    {
        $destinationDir = sprintf(
            '%s/%s',
            self::DESTINATION_PATH,
            FilenameSanitizer::sanitize($playlist->title),
        );

        if (!file_exists($destinationDir)) {
            mkdir($destinationDir);
        }

        $this->logger->debug("moving playlist to $destinationDir");

        foreach ($playlist->items as $item) {
            rename(
                $item->download->audioPath,
                sprintf('%s/%s',
                    $destinationDir,
                    basename($item->download->audioPath)
                ),
            );
        }

        rename(
            $playlist->getPath(),
            sprintf('%s/%s',
                $destinationDir,
                basename($playlist->getPath())
            ),
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
