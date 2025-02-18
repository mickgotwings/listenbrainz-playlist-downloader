# Listenbrainz "Created for you" playlist downloader

This software regularly gets your "Created for you" playlist from [listenbrainz.org](https://listenbrainz.org),
downloads songs from YouTube and puts those in a separate directory, alongside with the m3u playlist file.

It runs every day at 00:30 am (timezone can be specified) to get the latest playlists as soon as they are available.

You can also use it alongside pretty much any self-hosted music service, though,
I recommend creating a separate directory (and a library for that matter) for the playlists.

# Usage
## Docker compose
```yml
services:
  lbdl:
    container_name: lbdl
    environment:
      - TZ=Etc/UTC # Put your time zone here, so that lbdl can download your new playlists shortly as they are available
    volumes:
      - /path/to/your/.env:/opt/lbdl/.env
      - /path/to/your/music/dir:/tmp/music # Container path can be configured in .env, though it is not required
    restart: unless-stopped
    image: ghcr.io/mickgotwings/listenbrainz-playlist-downloader:master
```

## Manual
You can also run it manually (e.g. for debug purposes)
```shell
docker run -v /path/to/your/.env:/opt/lbdl/.env -v /path/to/your/music/dir:/tmp/music -it ghcr.io/mickgotwings/listenbrainz-playlist-downloader:master ./bin/run 
```

# Configuration (.env)

All the configuration is done via .env file. You can see all the available variables with comments in [.env.example](.env.example)

Minimal configuration requires only the Listenbrainz username:
```dotenv
LISTENBRAINZ_USERNAME=yourusername
```
