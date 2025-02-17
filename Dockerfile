FROM php:8.4-cli-alpine
WORKDIR /opt/lbdl
COPY composer.json composer.lock ./
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
RUN apk add -U tzdata yt-dlp-core ffmpeg
RUN composer install --no-dev
RUN echo "30 0 * * * /opt/lbdl/bin/run" > /etc/crontabs/root
COPY . ./
CMD ["crond", "-f"]
