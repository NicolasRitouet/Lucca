#
# Copyright (c) 2025. Numeric Wave
#
# Affero General Public License (AGPL) v3
#
# For more information, please refer to the LICENSE file at the root of the project.
#

# PHP
FROM php:8.4-fpm AS php-fpm

# Workdir during installation
WORKDIR /tmp

ENV APP_SECRET=${APP_SECRET}
ENV MYSQL_DATABASE=${MYSQL_DATABASE}
ENV MYSQL_USER=${MYSQL_USER}
ENV MYSQL_PASSWORD=${MYSQL_PASSWORD}
ENV MYSQL_HOST=${MYSQL_HOST}

RUN apt-get update && apt-get install -y \
    libzip-dev libicu-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    # Install minimal dependencies for wkhtmltopdf
    libxrender1 \
    libxext6 \
	libfontconfig1 \
	libx11-6 \
	fontconfig \
	fonts-crosextra-carlito \
	xfonts-100dpi \
	xfonts-75dpi \
	xfonts-base \
	libpng16-16 \
	# Configure PHP extensions
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip intl gd \
    # Clean up
    && apt-get clean -y \
    && apt-get autoclean -y \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

RUN ARCH=$(dpkg --print-architecture) && \
    curl -L https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_${ARCH}.deb \
        -o wkhtmltox.deb && \
    dpkg -i wkhtmltox.deb && \
    rm wkhtmltox.deb

# Workdir after installation
WORKDIR /srv/app

ENV WKHTMLTOPDF_PATH=/usr/local/bin/wkhtmltopdf

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

## Copy projet files
COPY . ./
COPY ./docker/scripts/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY ./docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Grant privileges to allow the entrypoint to override some configuration without being Root
RUN chmod +x /usr/local/bin/docker-entrypoint

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="${PATH}:/root/.composer/vendor/bin"

RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer install --prefer-dist --no-progress --no-scripts --no-interaction; \
    chmod +x bin/console; \
    bin/console fos:js-routing:dump --format=json --target=assets/routes.json; \
    bin/console assets:install; \
    bin/console importmap:install; \
    bin/console asset-map:compile; \
    chown -R www-data:www-data /srv/app/var/cache /srv/app/var/log;

RUN mkdir -p /srv/docs/Media /srv/docs/media /srv/docs/tmpFiles && \
    chown -R www-data:www-data /srv/docs

EXPOSE 9000

# Switch to www-data to avoid root
USER www-data

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# Caddy
FROM caddy:2-alpine AS caddy

WORKDIR /srv/app

COPY ./docker/Caddyfile /etc/caddy/Caddyfile
COPY --from=php-fpm /srv/app/public public/

