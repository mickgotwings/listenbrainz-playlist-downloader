# listenbrainz-playlist-downloader

## Usage
For now you'll have to build the docker image yourself and then run it
Example:
```shell
docker build -t lbdl .
docker run -v /path/to/your/.env:/opt/lbdl/.env -v /path/to/your/music/dir:/tmp/music lbdl
```
