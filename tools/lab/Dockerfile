FROM php:8.3-apache

ARG MOODLE_TAG=v5.3.1
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get -o Acquire::Retries=3 update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
        libfreetype6-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl mysqli zip soap exif opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
RUN git clone --depth 1 --branch "$MOODLE_TAG" https://github.com/moodle/moodle.git . \
    && rm -rf .git \
    && composer install --no-interaction --prefer-dist \
    && if [ -d public/local ]; then ln -s /var/www/html/public /opt/moodle-web; \
        else ln -s /var/www/html /opt/moodle-web; fi \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /opt/moodle-web|' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /opt/moodle-web>\nOptions FollowSymLinks\nAllowOverride None\nRequire all granted\n</Directory>\n' \
        > /etc/apache2/conf-enabled/moodle-lab.conf \
    && printf 'max_input_vars=5000\nmemory_limit=512M\n' > /usr/local/etc/php/conf.d/moodle.ini

COPY tools/lab/config.php /var/www/html/config.php
COPY tools/lab/start.sh /usr/local/bin/moodlexhub-start
RUN apt-get -o Acquire::Retries=3 update \
    && apt-get install -y --no-install-recommends locales \
    && printf 'en_AU.UTF-8 UTF-8\nen_US.UTF-8 UTF-8\nes_ES.UTF-8 UTF-8\n' > /etc/locale.gen \
    && locale-gen && rm -rf /var/lib/apt/lists/*
COPY . /opt/moodlexhub
RUN mkdir -p /opt/moodle-web/local/moodlexhub \
    && tar -C /opt/moodlexhub --exclude='./tools' --exclude='./.github' --exclude='./compose.yaml' \
        --exclude='./.dockerignore' -cf /tmp/moodlexhub.tar . \
    && tar -C /opt/moodle-web/local/moodlexhub -xf /tmp/moodlexhub.tar \
    && rm /tmp/moodlexhub.tar

ENTRYPOINT ["sh", "/usr/local/bin/moodlexhub-start"]
CMD ["apache2-foreground"]
