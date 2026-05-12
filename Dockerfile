FROM php:8.3-cli

# Install PDO MySQL + other extensions
RUN docker-php-ext-install pdo pdo_mysql

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Create uploads directory
RUN mkdir -p /app/uploads && chmod 777 /app/uploads

EXPOSE 8080

CMD ["php", "-d", "display_errors=1", "-S", "0.0.0.0:8080", "-t", "/app"]
