# syntax=docker/dockerfile:1
ARG PHP_VERSION=8.5

# Shared base: install the PHP extensions once so the vendor, frontend, and prod
# stages reuse a single layer instead of compiling ICU/zip/gd three times (which
# tripled build-time disk usage). install-php-extensions also leaves curl in
# place for the runtime healthcheck.
FROM dunglas/frankenphp:php${PHP_VERSION} AS base
RUN install-php-extensions pcntl pdo_pgsql redis intl zip opcache bcmath gd
WORKDIR /app

# Node is needed by three stages (the asset build, the runtime JS deps, and the
# runtime itself for Browsershot). Installing it once here means the nodesource
# script and its apt fetch run a single time per build instead of three.
FROM base AS node_base
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer Dependencies
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
COPY composer.json composer.lock ./
# Cache mount so a changed composer.lock only re-downloads what actually
# changed, rather than the whole dependency tree.
RUN --mount=type=cache,target=/tmp/composer-cache,sharing=locked \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
# bootstrap/cache is dockerignored (host artifacts must not be baked in), so
# package:discover needs the directory recreated before it can write to it.
RUN mkdir -p bootstrap/cache \
    && composer dump-autoload --optimize --no-dev   # runs package:discover with full app present

# Frontend assets (needs PHP for wayfinder:generate, plus Node)
FROM node_base AS frontend
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
# Vite inlines VITE_* vars into the JS bundle at build time; the runtime .env
# cannot change them afterwards. Must be present before `npm run build`.
ARG VITE_APP_NAME="Martori Farms"
ENV VITE_APP_NAME=${VITE_APP_NAME}
# Echo's browser connection (configureEcho reads these). The host is the public
# app host: Octane's Caddy proxies /app/* to the reverb container.
ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST=""
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}
# This stage only compiles assets. puppeteer is a runtime dependency, so its
# postinstall would otherwise pull ~650MB of Chrome that never leaves the stage
# -- and fail outright, since the base image has no unzip.
ENV PUPPETEER_SKIP_DOWNLOAD=true
# Install before copying the source, so editing app code reuses the cached
# install instead of re-running npm ci on every deploy. node_modules is
# dockerignored, so the source copy below cannot clobber it.
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm,sharing=locked npm ci
COPY --from=vendor /app/vendor ./vendor
COPY . .
# The vendor stage's package:discover output, generated without dev packages.
# The wayfinder vite plugin shells out to artisan, which cannot boot without it.
COPY --from=vendor /app/bootstrap/cache ./bootstrap/cache
RUN npm run build

# Production JS deps plus the Chrome that Browsershot drives. Separate from the
# `frontend` stage so the dev tree (playwright, vite, tsc) never reaches the
# runtime image, and so only these layers rebuild when app code changes.
FROM node_base AS node_deps
# unzip is required: @puppeteer/browsers extracts the Chrome archive with it and
# fails with "no zip archiver is available" without it.
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
# .puppeteerrc.cjs points the cache at ./.puppeteer, inside the project, so it
# can be COPY'd out. The default $HOME/.cache/puppeteer cannot.
COPY package.json package-lock.json .puppeteerrc.cjs ./
# Only chrome-headless-shell, not full Chrome: browser.cjs launches with
# headless: 'shell', so the 389MB full build would never be used. Anything that
# calls Browsershot->newHeadless() would need it and must add it back.
ENV PUPPETEER_SKIP_DOWNLOAD=true
RUN --mount=type=cache,target=/root/.npm,sharing=locked \
    npm ci --omit=dev \
    && npx puppeteer browsers install chrome-headless-shell

# Runtime — extensions, curl and Node come from the shared bases, so no
# reinstall here.
FROM node_base AS prod
# Opcache tuned for long-running Octane workers
RUN { \
      echo "opcache.enable=1"; \
      echo "opcache.enable_cli=1"; \
      echo "opcache.jit=tracing"; \
      echo "opcache.jit_buffer_size=64M"; \
      echo "opcache.validate_timestamps=0"; \
      echo "opcache.memory_consumption=256"; \
      echo "opcache.max_accelerated_files=20000"; \
    } > /usr/local/etc/php/conf.d/zz-opcache.ini
# Deployed commit SHA, surfaced in-app via config('app.commit').
ARG GIT_COMMIT=unknown
ENV GIT_COMMIT=${GIT_COMMIT}
# Chrome's shared libraries and base fonts. Not present in the frankenphp image;
# the Node that Browsershot shells out to already came from node_base.
RUN apt-get update && apt-get install -y --no-install-recommends \
      libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 \
      libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libpango-1.0-0 \
      libcairo2 libasound2 fonts-liberation \
    && rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=vendor --chown=www-data:www-data /app/bootstrap/cache ./bootstrap/cache
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build
COPY --from=node_deps --chown=www-data:www-data /app/node_modules ./node_modules
COPY --from=node_deps --chown=www-data:www-data /app/.puppeteer ./.puppeteer
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
# www-data's home is not writable; Chrome needs somewhere to put its user data
# dir, and Browsershot writes temp files.
ENV HOME=/tmp
USER www-data
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
    CMD curl -fsS http://localhost/up || exit 1
# Base image ENTRYPOINT (docker-php-entrypoint) execs this CMD; compose overrides it for queue/scheduler
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=80", "--admin-port=2019"]
