FROM php:8.3-cli

# Install PDO MySQL + other extensions
RUN docker-php-ext-install pdo pdo_mysql

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Create uploads + session directories
RUN mkdir -p /app/uploads /tmp/sessions && chmod 777 /app/uploads /tmp/sessions

EXPOSE 8080

CMD ["php", "-d", "display_errors=1", "-d", "session.save_path=/tmp/sessions", "-d", "session.cookie_secure=0", "-d", "session.cookie_samesite=Lax", "-S", "0.0.0.0:8080", "-t", "/app"]
