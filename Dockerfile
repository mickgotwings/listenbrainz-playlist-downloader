FROM php:8.4-cli-alpine
WORKDIR /opt/lbdl
COPY composer.json composer.lock ./
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
RUN apk add --no-cache tzdata yt-dlp-core ffmpeg
RUN composer install --no-ansi --no-interaction --no-progress --no-dev
RUN echo "30 0 * * * /opt/lbdl/bin/run" > /etc/crontabs/root
COPY . ./
RUN composer dump-autoload -o
CMD ["crond", "-f"]
