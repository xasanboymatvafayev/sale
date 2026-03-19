FROM php:8.2-cli

# mysqli kengaytmasini o'rnatish
RUN docker-php-ext-install mysqli

WORKDIR /app
COPY . .

# Server ishga tushadi
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
