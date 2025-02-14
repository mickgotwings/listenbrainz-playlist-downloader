<?php declare(strict_types=1);

namespace App;

use App\ApiWrapper\ListenbrainzPlaylistApiWrapper;
use App\Model\Playlist;
use App\Model\PlaylistItem;
use App\Model\Track;
use App\MusicSources\Exception\DownloadException;
use App\MusicSources\Exception\TrackMismatchException;
use App\MusicSources\Exception\TrackNotFoundException;
use App\MusicSources\YoutubeMusic\YoutubeMusicSource;
use App\MusicSources\YoutubeMusic\YtDlpDownloader;
use App\PlaylistGenerator\PlaylistGenerator;
use App\Processor\ID3Processor;
use Dotenv\Dotenv;
use Listenbrainz\Api\LbPlaylistsApi;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use YoutubeDl\YoutubeDl;
use Ytmusicapi\YTMusic;

class Application
{
    private readonly LoggerInterface $logger;

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
        $lbApi = new ListenbrainzPlaylistApiWrapper(
            new LbPlaylistsApi(),
            new UrlParser(),
        );

        $id3Processor = new ID3Processor();

        $result = $lbApi->getCreatedFor($_ENV['LISTENBRAINZ_USERNAME']);

        foreach ($result as $apiPlaylist) {
            $this->logger->info("Processing playlist $apiPlaylist->name");

            $apiTracks = $lbApi->getTracks($apiPlaylist->uuid);

            $playlistPath = realpath($_ENV['DOWNLOAD_PATH'] ?? '/tmp/music') . DIRECTORY_SEPARATOR . FilenameSanitizer::sanitize($apiPlaylist->name);

            if (file_exists($playlistPath)) {
                $this->logger->info("Skipping, since this playlist already exists in the download directory");
                continue;
            }

            mkdir($playlistPath);

            $source = new YoutubeMusicSource(
                new YTMusic(),
                new YtDlpDownloader(
                    $playlistPath,
                    new YoutubeDl(),
                    $this->logger
                )
            );

            $playlistItems = [];

            foreach ($apiTracks as $apiTrack) {
                $track = new Track(
                    $apiTrack->artist,
                    $apiTrack->album,
                    $apiTrack->title,
                );

                try {
                    $this->logger->info("Downloading $track->artist - $track->title ($track->album)");

                    $download = $source->grab($track);

                    $id3Processor->fillTags($download, $track);

                    $playlistItems[] = new PlaylistItem(
                        $track,
                        $download,
                    );
                } catch (TrackNotFoundException $e) {
                    $this->logger->error(
                        "Track $track->artist - $track->title ($track->album) not found",
                        ['exception' => $e]
                    );
                } catch (TrackMismatchException $e) {
                    $this->logger->error(
                        "Requested $track->artist - $track->title ($track->album), got a different track",
                        ['exception' => $e]
                    );
                } catch (DownloadException $e) {
                    $this->logger->error(
                        "Could not download $track->artist - $track->title ($track->album)",
                        ['exception' => $e]
                    );
                }

                break; // debug
            }

            $this->logger->info("Generating playlist file for $apiPlaylist->name");

            $playlistGenerator = new PlaylistGenerator($playlistPath);

            $playlistGenerator->generate(
                new Playlist(
                    $apiPlaylist->name,
                    $playlistItems,
                )
            );

            break; // debug
        }

        $this->logger->info('Done');
    }
}
