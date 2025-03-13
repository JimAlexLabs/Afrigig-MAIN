#!/bin/bash

# Exit on error
set -e

echo "Starting Afrigig deployment..."

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "Error: .env file not found!"
    echo "Please create .env file from .env.example"
    exit 1
fi

# Backup the current application
echo "Creating backup..."
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="backups/${TIMESTAMP}"
mkdir -p $BACKUP_DIR
cp -R * $BACKUP_DIR 2>/dev/null || true
echo "Backup created at $BACKUP_DIR"

# Install/update dependencies
echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Clear cache
echo "Clearing cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Optimize application
echo "Optimizing application..."
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set correct permissions
echo "Setting file permissions..."
chmod -R 755 .
chmod -R 777 storage
chmod -R 777 bootstrap/cache
chmod -R 777 uploads

# Update crontab for scheduled tasks
echo "Updating cron jobs..."
crontab -l > mycron
echo "* * * * * cd /path/to/afrigig && php artisan schedule:run >> /dev/null 2>&1" >> mycron
crontab mycron
rm mycron

# Restart services
echo "Restarting services..."
sudo systemctl restart php-fpm
sudo systemctl restart nginx

echo "Deployment completed successfully!" 