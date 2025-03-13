# Hostinger Deployment Guide for Afrigig

## Initial Setup

1. Log in to Hostinger Control Panel
2. Go to "Hosting" → "Manage"
3. Select your domain (afrigig.org)

## Domain Setup

1. Point your domain to Hostinger nameservers:

   - ns1.hostinger.com
   - ns2.hostinger.com
   - ns3.hostinger.com
   - ns4.hostinger.com

2. Enable SSL Certificate:
   - Go to SSL section in cPanel
   - Click "Setup" for Let's Encrypt SSL
   - Wait for SSL to be installed

## Database Setup

1. Go to MySQL Databases in cPanel
2. Create a new database:
   - Name: u123456789_afrigig
   - User: u123456789_afrigig
   - Set a strong password
   - Grant all privileges

## File Upload

1. Use File Manager or FTP:

   - Host: ftp.afrigig.org
   - Username: your-hostinger-username
   - Password: your-hostinger-password
   - Port: 21

2. Upload files to public_html directory

## Email Setup

1. Create email accounts:

   - Go to Email Accounts in cPanel
   - Create noreply@afrigig.org
   - Create support@afrigig.org

2. Configure SPF and DKIM records:
   ```
   v=spf1 include:_spf.hostinger.com ~all
   ```

## Cron Jobs

1. Go to Cron Jobs in cPanel
2. Add new cron job:
   ```
   * * * * * cd /home/username/public_html && php cron.php
   ```

## PHP Configuration

1. Go to PHP Configuration in cPanel
2. Set the following values:
   - memory_limit = 256M
   - upload_max_filesize = 64M
   - post_max_size = 64M
   - max_execution_time = 300
   - max_input_time = 300

## Backup Setup

1. Enable Auto-Backups in Hostinger dashboard
2. Configure backup retention period
3. Set up additional backup methods:
   ```bash
   # Daily database backup
   0 0 * * * /home/username/public_html/backup-db.sh
   ```

## Security Settings

1. Enable Two-Factor Authentication for cPanel
2. Configure Hostinger Firewall
3. Set up Password Protection for admin directories
4. Enable Hotlink Protection

## Performance Optimization

1. Enable LiteSpeed Cache:

   - Create .htaccess rules
   - Configure cache settings

2. Enable Hostinger CDN:
   - Go to CDN section
   - Enable for your domain
   - Configure cache rules

## Monitoring

1. Set up Hostinger monitoring:

   - Enable Error Logs
   - Configure Email Alerts
   - Monitor Resource Usage

2. Configure custom monitoring:
   ```bash
   */5 * * * * /home/username/public_html/monitor.php
   ```

## Troubleshooting

Common issues and solutions:

1. 500 Internal Server Error:

   - Check error logs in cPanel
   - Verify .htaccess configuration
   - Check PHP version compatibility

2. SSL Issues:

   - Force HTTPS in .htaccess
   - Clear browser cache
   - Verify SSL configuration

3. Permission Issues:
   ```bash
   find . -type f -exec chmod 644 {} \;
   find . -type d -exec chmod 755 {} \;
   chmod -R 777 uploads
   ```

## Support

For Hostinger-specific issues:

- Hostinger Support: support.hostinger.com
- Live Chat: Available 24/7
- Knowledge Base: knowledge.hostinger.com

For Afrigig support:

- Email: support@afrigig.org
- Technical Issues: tech@afrigig.org
