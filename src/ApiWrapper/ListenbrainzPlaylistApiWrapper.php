<?php declare(strict_types=1);

namespace App\ApiWrapper;

use App\ApiWrapper\Model\ApiPlaylist;
use App\ApiWrapper\Model\ApiTrack;
use App\UrlParser;
use DateTimeImmutable;
use Listenbrainz\Api\LbPlaylistsApi;
use Listenbrainz\ApiException;
use Ramsey\Uuid\UuidInterface;

class ListenbrainzPlaylistApiWrapper
{
    public function __construct(
        private readonly LbPlaylistsApi $playlistsApi,
        private readonly UrlParser      $urlParser,
    ) {}

    /**
     * @param string $userName
     * @return iterable<ApiPlaylist>
     * @throws ApiException
     */
    public function getCreatedFor(string $userName): iterable {
        $apiPlaylists = $this->playlistsApi->playlistsCreatedForUser($userName);

        foreach ($apiPlaylists->getPlaylists() as $apiPlaylist) {
            $apiPlaylist = $apiPlaylist->getPlaylist();
            yield new ApiPlaylist(
                $this->urlParser->parseTrailingUuid($apiPlaylist->getIdentifier()),
                $apiPlaylist->getTitle(),
                $apiPlaylist->getAnnotation(),
                new DateTimeImmutable($apiPlaylist->getDate()),
                $apiPlaylist
                    ->getExtension()
                    ?->getHttpsMusicbrainzOrgDocJspfplaylist()
                    ?->getAdditionalMetadata()
                    ?->getAlgorithmMetadata()
                    ?->getSourcePatch()
            );
        }
    }


    public function getTracks(UuidInterface $playlistUuid): iterable {
        $apiPlaylist = $this->playlistsApi->fetchPlaylist($playlistUuid);

        foreach ($apiPlaylist->getPlaylist()->getTrack() as $apiPlaylistTrack) {
            yield new ApiTrack(
                $this->urlParser->parseTrailingUuid($apiPlaylistTrack->getIdentifier()[0]),
                $apiPlaylistTrack->getCreator(),
                $apiPlaylistTrack->getAlbum(),
                $apiPlaylistTrack->getTitle(),
            );
        }
    }
}
