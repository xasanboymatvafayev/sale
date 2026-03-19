FROM php:8.2-cli

# mysqli extension o'rnatish
RUN docker-php-ext-install mysqli

# ishchi katalog
WORKDIR /app

# fayllarni konteynerga nusxalash
COPY . .

# serverni ishga tushirish
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
