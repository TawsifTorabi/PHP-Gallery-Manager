FROM php:8.2-apache

# Install system dependencies + FFmpeg + PHP extensions dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application source
COPY . /var/www/html

# Set correct ownership for Apache
RUN chown -R www-data:www-data /var/www/html

# Expose Apache port
EXPOSE 80