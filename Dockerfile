FROM php:8.3-cli

# Install system dependencies + PHP extensions
RUN apt-get update && apt-get install -y libcurl4-openssl-dev libssl-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_mysql curl

# Enable openssl (already built-in with php:8.3-cli)
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

WORKDIR /app
COPY . /app

RUN mkdir -p /app/uploads /tmp/sessions && chmod 777 /app/uploads /tmp/sessions

EXPOSE 8080

CMD ["php", "-d", "display_errors=1", "-d", "session.save_path=/tmp/sessions", "-d", "session.cookie_secure=0", "-d", "session.cookie_samesite=Lax", "-S", "0.0.0.0:8080", "-t", "/app"]
