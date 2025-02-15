FROM php:8.4-cli-alpine
COPY . /opt/lbdl
WORKDIR /opt/lbdl
RUN apk add yt-dlp-core
RUN apk add ffmpeg
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
RUN composer install --no-dev
CMD ["./bin/run"]
