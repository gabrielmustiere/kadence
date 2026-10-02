# syntax=docker/dockerfile:1.7
FROM dunglas/frankenphp:1-php8.5-bookworm AS app

RUN install-php-extensions calendar intl pdo_sqlite \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV SERVER_NAME=:80 \
    APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

COPY php.ini /usr/local/etc/php/conf.d/zz-kadence.ini

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-progress --prefer-dist \
    && composer clear-cache

COPY . .

# Les auto-scripts Composer lancent vendor/bin/mate, absent sans les dépendances de dev :
# les étapes utiles sont rejouées ici, importmap avant Tailwind qui scanne assets/vendor.
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && bin/console assets:install public \
    && bin/console importmap:install \
    && bin/console tailwind:build --minify \
    && bin/console asset-map:compile \
    && bin/console cache:warmup

EXPOSE 80

# Les fixtures (DoctrineFixturesBundle, namespace DataFixtures\) n'existent qu'avec les dépendances de dev.
FROM app AS seed

ENV APP_ENV=dev

RUN composer install --no-scripts --no-progress --prefer-dist \
    && composer clear-cache

CMD ["sh", "-c", "bin/console doctrine:database:drop --force && bin/console doctrine:migrations:migrate -n && bin/console doctrine:fixtures:load -n"]
