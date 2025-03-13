# Afrigig - African Freelance Marketplace

Afrigig is a professional freelance marketplace platform designed to empower African talent by connecting skilled freelancers with clients worldwide.

## Features

- Modern and responsive design
- Secure user authentication and authorization
- Job posting and bidding system
- Real-time messaging
- File upload and management
- M-Pesa payment integration
- Admin dashboard
- Notification system
- User profiles and portfolios

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Composer
- Node.js and NPM (for asset compilation)
- Apache/Nginx web server
- SSL certificate (for HTTPS)
- M-Pesa API credentials

## Installation

1. Extract the project files to your web server directory:

```bash
# Extract the project to your web server directory
# For example:
unzip afrigig.zip -d /var/www/html/afrigig
cd afrigig
```

2. Install PHP dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

3. Create environment file:

```bash
cp .env.example .env
```

4. Configure your `.env` file with your database and other settings.

5. Create the database:

```bash
mysql -u root -p
CREATE DATABASE afrigig;
```

6. Run database migrations:

```bash
php artisan migrate
```

7. Set proper permissions:

```bash
chmod -R 755 .
chmod -R 777 storage
chmod -R 777 bootstrap/cache
chmod -R 777 uploads
```

## Deployment

1. Ensure your server meets all requirements.

2. Set up your web server (Apache/Nginx) with proper configurations.

3. Configure SSL certificate for HTTPS.

4. Run the deployment script:

```bash
./deploy.sh
```

## Security Considerations

- Always use HTTPS in production
- Keep all dependencies updated
- Regularly backup your database
- Monitor error logs
- Implement rate limiting
- Use strong passwords
- Enable firewall rules

## Cron Jobs

Add the following cron job to your server:

```bash
* * * * * cd /path/to/afrigig && php artisan schedule:run >> /dev/null 2>&1
```

## Monitoring

Monitor the following:

- Server resources (CPU, memory, disk)
- Database performance
- Error logs
- Failed login attempts
- Payment transactions
- File uploads

## Contributing

We welcome contributions to the Afrigig project! Here's how you can contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please make sure to update tests as appropriate and follow the code style guidelines.

## Support

For support, please email support@afrigig.com

## License

Copyright © 2024 Afrigig. All rights reserved.
