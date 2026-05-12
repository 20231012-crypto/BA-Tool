FROM php:8.3-cli

# Install system dependencies + PHP extensions
RUN apt-get update && apt-get install -y libcurl4-openssl-dev libssl-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_mysql curl

# Enable php.ini
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

WORKDIR /app
COPY . /app

RUN mkdir -p /app/uploads /tmp/sessions && chmod 777 /app/uploads /tmp/sessions
RUN chmod +x /app/start.sh

EXPOSE 8080

CMD ["/app/start.sh"]
