# syntax=docker/dockerfile:1
FROM php:7.4-cli

RUN apt-get update -y && apt-get install -y --no-install-recommends \
    libzip-dev libbz2-dev libpq-dev libmemcached-dev curl && \
    curl -L -o /tmp/memcached.tar.gz "https://github.com/php-memcached-dev/php-memcached/archive/refs/tags/v3.1.5.tar.gz" \
        && mkdir -p /usr/src/php/ext/memcached \
        && tar -C /usr/src/php/ext/memcached -zxvf /tmp/memcached.tar.gz --strip 1 \
        && docker-php-ext-configure memcached \
        && docker-php-ext-install memcached \
        && rm /tmp/memcached.tar.gz && \
    docker-php-ext-install pcntl && \
    docker-php-ext-install bz2 && \
    docker-php-ext-install zip && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');" && \
    composer --global config process-timeout 1000