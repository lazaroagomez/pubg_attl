FROM php:8.3-apache

# Install necessary extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    cron \
    supervisor \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set up directory structure
RUN mkdir -p /app/database /app/logs

# Configure PHP
RUN echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/timezone.ini

# Create supervisor config for cron.php
RUN echo "[supervisord]" > /etc/supervisor/conf.d/supervisord.conf && \
    echo "nodaemon=true" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "[program:apache2]" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "command=/usr/sbin/apache2ctl -D FOREGROUND" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "autostart=true" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "autorestart=true" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "[program:cron]" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "command=/usr/local/bin/php /var/www/html/cron.php" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "autostart=true" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "autorestart=true" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "stderr_logfile=/app/logs/cron.err.log" >> /etc/supervisor/conf.d/supervisord.conf && \
    echo "stdout_logfile=/app/logs/cron.out.log" >> /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html /app

# Expose port
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 