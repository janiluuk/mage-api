FROM php:8.2-fpm

# Arguments defined in docker-compose.yml
ARG user
ARG uid

# Install system dependencies including SQLite for testing
RUN apt-get update && apt-get install -y \
    git \
    curl \
    ffmpeg \
    pngcrush \
    imagemagick \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libgd-dev \
    jpegoptim optipng pngquant gifsicle \
    zip \
    sudo \
    unzip \
    libsqlite3-dev \
    sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Install xdebug (PECL extension)
RUN pecl install xdebug-3.2.2 && docker-php-ext-enable xdebug

# Install PHP extensions including SQLite PDO for testing
# Note: pdo_sqlite is sufficient for Laravel tests - it provides SQLite support via PDO
RUN docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
#RUN useradd -G $user,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www
USER $user
#RUN composer install
# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
