# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: build the frontend (Vite + React). Only the compiled output is kept.
# Debian (not Alpine) because Rollup/Tailwind/lightningcss ship glibc binaries.
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js tsconfig.json components.json ./
COPY resources ./resources
RUN npm run build


# ---------------------------------------------------------------------------
# Stage 2: the app, served by Apache + mod_php.
# ---------------------------------------------------------------------------
FROM php:8.4-apache AS app

# unzip: Composer needs it to extract package archives.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

# Production PHP defaults (no error output to the browser, sane limits).
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Serve only public/ (Laravel's single entry point is public/index.php) and
# enable mod_rewrite so public/.htaccess can route every URL through it.
# This is the approach documented for the official php:apache image.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html

# Install PHP dependencies first so this layer is cached until composer.lock changes.
# --no-scripts: Laravel's post-install scripts need the app code, which isn't copied yet.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build

# Build the optimized autoloader. This also runs Laravel's package:discover
# (via composer.json's post-autoload-dump script) against the packages
# actually installed, without dev-only ones.
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chmod +x docker/entrypoint.sh \
    && mkdir -p /data \
    && chown -R www-data:www-data storage bootstrap/cache /data

# SQLite database + generated APP_KEY live here (a named volume in compose).
VOLUME /data

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
