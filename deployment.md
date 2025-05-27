# Deployment Notes

The deployment setup uses Nginx as the web server, PHP-FPM to process PHP scripts, and SQLite as the database, running on a Linux server (e.g., Ubuntu 20.04 LTS). This setup is designed to serve this application (HTML5, CSS, PHP, no JavaScript or frameworks) and is prepared for a future WebSocket-based chat feature via a reverse proxy.

This is intended to state the complete deployment configuration, including all relevant files and steps, ensuring compatibility with the project’s features: case-insensitive login/registration, CSRF protection, rate limiting, password strength, roles, invitation codes, password confirmation, and logging (SQLite logs table and PHP error logs).

I’ll include the Nginx configuration, PHP-FPM setup, SQLite configuration, and related system-level instructions (e.g., permissions, logging, HTTPS).

## Deployment Overview

* Web Server: Nginx handles HTTP requests, serves static files (e.g., style.css), and proxies PHP requests to PHP-FPM. It’s configured to support a future WebSocket reverse proxy.

* PHP-FPM: Processes PHP scripts (index.html, login.php, etc.), interfacing with SQLite for database operations.

* SQLite: Stores data in a single file (database.sqlite) outside the web root for security.

* OS: Ubuntu 20.04 LTS (standard for small PHP applications).

* Logging: Application events to SQLite logs table, PHP errors to `/var/log/php_errors.log`, Nginx logs to `/var/log/nginx/access.log` and `/var/log/nginx/error.log`, cron logs to `/var/log/chat-site-cron.log`.

* HTTPS: Secured with Let’s Encrypt for production, ensuring safe session and WebSocket communication.

## Deployment Files and Configuration

### Nginx Configuration

Nginx is configured to serve the application, proxy PHP requests to PHP-FPM, and reserve a location for future WebSocket proxying. The configuration file is typically located at `/etc/nginx/sites-available/chat-site`.

Here's the unencrypted HTTP nginx setup for pre-deployment testing:

**/etc/nginx/sites-available/chat-site:**
```
server {
    listen 80;
    server_name example.com www.example.com;

    root /var/www/html;
    index index.html index.php;

    # Serve PHP files
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; # Adjust PHP version
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static files
    location / {
        try_files $uri $uri/ /index.php;
    }

    # Future WebSocket proxy (placeholder)
    location /ws {
        proxy_pass http://localhost:8080; # WebSocket server address
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    # Logging
    access_log /var/log/nginx/chat-site.access.log;
    error_log /var/log/nginx/chat-site.error.log;
}
```

Here's the HTTPS using an Let's Encrypt cert:
```
server {
    listen 80;
    server_name example.com www.example.com;
    return 301 https://$server_name$request_uri; # Redirect HTTP to HTTPS
}

server {
    listen 443 ssl;
    server_name example.com www.example.com;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root /var/www/html;
    index index.html index.php;

    # Serve PHP files
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; # Adjust PHP version
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static files
    location / {
        try_files $uri $uri/ /index.php;
    }

    # Future WebSocket proxy
    location /ws {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    # Logging
    access_log /var/log/nginx/chat-site.access.log;
    error_log /var/log/nginx/chat-site.error.log;
}
```

#### Setup Instructions

* **Install `nginx`:**
  ```
  sudo apt install nginx
  ```

* **Create Configuration:**

  + Save the configuration file above as `/etc/nginx/sites-available/chat-site`.

  + Link to enable: `sudo ln -s /etc/nginx/sites-available/chat-site /etc/nginx/sites-enabled/`.

  + Remove default site if still present: `sudo rm /etc/nginx/sites-enabled/default`.

* **Test and Reload:**

  ```
  sudo nginx -t
  sudo systemctl reload nginx
  ```

* **Setup Logging:**

  ```
  sudo touch /var/log/nginx/chat-site.access.log /var/log/nginx/chat-site.error.log
  sudo chown www-data:www-data /var/log/nginx/chat-site*.log
  sudo chmod 644 /var/log/nginx/chat-site*.log
  ```

* **HTTPS with Let's Encrypt:**

  ```
  sudo apt install certbot python3-certbot-nginx
  sudo certbot --nginx -d example.com -d www.example.com
  ```

  + Follow prompts to configure HTTPS, updating the Nginx configuration automatically.

  + Verify auto-renewal: `sudo certbot renew --dry-run`.

* **Log Rotation:**

  Nginx log rotation is setup by default, but, to verify check that the Logrotate configuration file `/etc/logrotate.d/nginx` exists and is similar to the following:

  ```
  /var/log/nginx/*.log {
      weekly
      rotate 4
      compress
      maxsize 100M
      missingok
      notifempty
      create 644 www-data www-data
      sharedscripts
      postrotate
        if [ -f /var/run/nginx.pid ]; then
            kill -USR1 `cat /var/run/nginx.pid`
        fi
      endscript
  }
  ```

### PHP-FPM Configuration

PHP-FPM processes PHP scripts, interfacing with SQLite. The configuration is typically in `/etc/php/7.4/fpm` (adjust for your PHP version, e.g., 8.1).

**Key Configuration File:** `/etc/php/7.4/fpm/php.ini`
```
[PHP]
error_log = /var/log/php_errors.log
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
session.cookie_httponly = 1
session.cookie_secure = 1 ; Enable after HTTPS
session.gc_maxlifetime = 604800 ; Match CSRF_TOKEN_TIMEOUT (1 week)
display_errors = Off
display_startup_errors = Off

[sqlite3]
sqlite3.extension_dir =
```

**PHP-FPM Pool Configuration:** `/etc/php/7.4/fpm/pool.d/www.conf`
```
[www]
; User and group that will run PHP scripts
user = www-data
group = www-data

; Unix socket for PHP-FPM communication with Nginx
listen = /var/run/php/php7.4-fpm.sock

; Set ownership of the socket to match the web server process
listen.owner = www-data
listen.group = www-data

; Use dynamic process management to optimize resource usage
pm = dynamic

; Maximum number of child processes that can be spawned
pm.max_children = 10  ; Consider increasing if high traffic is expected

; Initial number of children spawned when PHP-FPM starts
pm.start_servers = 4  ; Adjust based on expected baseline traffic

; Minimum number of idle processes before additional ones are created
pm.min_spare_servers = 2  ; Helps ensure quick responses to traffic spikes

; Maximum number of idle processes before excess ones are terminated
pm.max_spare_servers = 5  ; Prevents unnecessary resource usage

; Limit the number of requests each child process handles before being restarted
pm.max_requests = 500  ; Helps mitigate potential memory leaks over time

; Logging settings
slowlog = /var/log/php-fpm.slow.log  ; Logs slow requests for debugging
request_terminate_timeout = 30s  ; Terminates slow requests exceeding this duration

; Security enhancements
php_admin_value[session.cookie_secure] = 1  ; Ensures session cookies are transmitted over HTTPS
php_admin_value[session.cookie_httponly] = 1  ; Prevents client-side script access to session cookies
```

#### Setup Instructions

* **Install PHP-FPM and SQLite Extension:**
  ```
  sudo apt install php-fpm php-sqlite3 php-cli
  ```

  + **Check PHP Version:** `php -v` (e.g. 7.4 or 8.1)

* **Configure PHP-FPM:**

  + Edit `php.ini` as above

  + Verify `www.conf` settings

* **Create Error Log:**
  ```
  sudo touch /var/log/php_errors.log
  sudo chown www-data:www-data /var/log/php_errors.log
  sudo chmod 644 /var/log/php_errors.log
  ```

* **Restart PHP-FPM:**
  ```
  sudo systemctl restart php7.4-fpm
  ```

* **Log Rotation:**

  Create `/etc/logrotate.d/php_errors`:
  ```
  /var/log/php_errors.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
    create 644 www-data www-data
   }
   ```

### SQLite Configuration

SQLite uses a single file (`database.sqlite`) for the database, stored outside the web root for security.

#### Database File

* **Schema**: Includes `users`, `login_attempts`, `user_login_attempts`, `invitation_codes`, and `logs` tables (see previous database schema).

* **Permissions:**
  ```
  sudo mkdir -p /var/www/data
  sudo touch /var/www/data/database.sqlite
  sudo chown www-data:www-data /var/www/data/database.sqlite
  sudo chmod 664 /var/www/data/database.sqlite
  ```

#### Setup Instructions

* **Initialize Database:** 

  + Create the database file and apply the schema (e.g., using sqlite3 CLI or DB Browser for SQLite).

  + Example:
    ```
    sqlite3 /var/www/data/database.sqlite
    ```
    This will open the SQL prompt where you can run the contents of the `database.sql`


  + **Update PHP Scripts:** Ensure each PHP script points to the correct database file by checking
  lines like this:
    ```
    $db = new PDO('sqlite:/var/www/data/database.sqlite');
    ```

* **Schedule Periodic Backups:**
  You can add backups via CRON.
  ```
0 2 * * * tar -czf /var/www/data/backup/database-$(date +\%F).tar.gz /var/www/data/database.sqlite
  ```

### Application Deployment
Deploy all the application files and set permissions.

* **Files:** index.html, style.css, login.php, config.php, welcome.php, logout.php, manage_invites.php, register.php, cleanup.php 

* **Copy Files:**
  ```
  sudo cp -r project/* /var/www/html
  ```

* **Set Permissions:**
  ```
  sudo chown -R www-data:www-data /var/www/html
  sudo chmod -R 755 /var/www/html
  sudo chmod 644 /var/www/html/*.php /var/www/html/*.css /var/www/html/*.html
  ```

* **Verify Access:**

  + Visit `https://example.com`

  + Test login, registration, and admin features

### Cron Configuration

The `cleanup.php` script should run daily to maintain the database.

* **Crontab Entry:**
  ```
  0 0 * * * /usr/bin/php /var/www/html/cleanup.php >> /var/log/chat-site-cron.log 2>&1
  ```

* **Setup:**

  + **Edit Crontab**:
  Add the entry above by using this command to edit the crontab entry.
    ```
    sudo crontab -e -u www-data
    ```

  + **Create Cron Log**:
    ```
    sudo touch /var/log/chat-site-cron.log
    sudo chown www-data:www-data /var/log/chat-site-cron.log
    sudo chmod 644 /var/log/chat-site-cron.log
    ```

  + **Ensure Log Rotation:**
    Create `/etc/logrotate.d/chat-site-cron`:
    ```
    /var/log/chat-site-cron.log {
      weekly
      rotate 4
      compress
      missingok
      notifempty
      create 644 www-data www-data
    }
    ```
### Leftover Setup

* **Firewall Setup:**
  ```
  sudo apt install ufw
  sudo ufw allow 80/tcp
  sudo ufw allow 443/tcp
  sudo ufw enable
  ```

* **Cron Service:**
  ```
  sudo systemctl enable cron
  sudo systemctl start cron
  ```
