FROM php:8.2-fpm-alpine

RUN apk add --no-cache tzdata \
    && ln -sf /usr/share/zoneinfo/Europe/Warsaw /etc/localtime \
    && echo "Europe/Warsaw" > /etc/timezone \
    && docker-php-ext-install pdo pdo_mysql

COPY crontab /etc/crontabs/root

RUN chmod 0600 /etc/crontabs/root \
    && mkdir -p /var/log/cron \
    && touch /var/log/cron/cron.log

ENV TZ=Europe/Warsaw

WORKDIR /var/www/app

CMD ["crond", "-f"]
