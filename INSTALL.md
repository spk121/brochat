# A WARNING

I called this project Bro Chat because I thought it was a funny name.
But, for the love of all that is holy, do not attempt to look at
`brochat.com`.  It is, as of 2025, a scam site; I have nothing to
do with that.

# Installation

This document is intended to be the complete manual installation instructions
for the Bro Chat weblog engine, music streamer, and chat server. This setup is
designed to serve this application (HTML5, CSS, PHP without any specific
framework, limited JavaScript without frameworks) and a
WebSocket-based chat feature via a reverse proxy.

This is intended to state the complete deployment configuration, including all
relevant files and steps, ensuring compatibility with the project’s features:
case-insensitive login/registration, CSRF protection, rate limiting, password
strength, roles, invitation codes, password confirmation, and logging (SQLite
logs table and PHP error logs).

## Software Stack

Bro Chat uses the LNSP stack: Linux, Nginx, SQLite and PHP

* GNU/Linux OS: Ubuntu 24.04 LTS (common for small PHP applications).

* **Nginx**: A web server. Nginx handles HTTP requests, serves static files
  (e.g., style.css), and proxies PHP requests to PHP-FPM. It is configured to
  support a future WebSocket reverse proxy.

* **SQLite**: A lightweight database that stores data in a single file.

* **PHP and PHP-FPM**: The PHP programming language is used
  to generate HTML files server-side. Processes PHP scripts (index.php,
  login.php, etc.), interfacing with SQLite for database operations.

Bro Chat has additional programs and setup required for the logging and
secure socket layer.

* Logging: Loggin requires Vixie Cron and Red Hat Logrotate. Application events
  get logged to SQLite logs table, PHP errors to
  `/var/log/php_errors.log`, Nginx logs to `/var/log/nginx/access.log`
  and `/var/log/nginx/error.log`, cron logs to `/var/log/brochat-cron.log`.

* HTTPS: Secured with Let’s Encrypt for production, which requires the `certbot`
  program, ensuring safe session and WebSocket communication.

## Installing the LNSP Stack

These instruction presume we're starting with a clean, vanilla installation
of Ubuntu 24.04.01 LTS.

To check the version of the OS is being run, the following command can be
entered:

    $ lsb_release -a
    > No LSB modules are available.
    > Distributor ID: Ubuntu
    > Description:    Ubuntu 24.04.1 LTS
    > Release:        24.04
    > Codename:       noble

To get Nginx, install the following package with `apt`, which
will install both the `nginx` and `nginx-common` packages.

    $ sudo apt install nginx

To check the version of nginx installed enter `nginx -v`.

    $ nginx -v
    > nginx version: nginx/1.24.0 (Ubuntu)

This package installation also preps Nginx to be managed by Systemd.  It performs
the following link so that Nginx is a Systemd service.

    Created symlink /etc/systemd/system/multi-user.target.wants/nginx.service
    → /usr/lib/systemd/system/nginx.service.

To install PHP and PHP-FPM along with the PHP SQLite extension, use the following
command:

    $ sudo apt install php-fpm php-sqlite3 php-cli

This installs the following packages:

    php-common php8.3-cli php8.3-common php8.3-fpm php8.3-opcache php8.3-readline php8.3-sqlite3

And among many config files, it creates the main config file:

    /etc/php/8.3/fpm/php.ini

In this case, is clear that the version of PHP that is installed is 8.3, but, to check that
from the command line, enter the following command:

    $ php -v
    > PHP 8.3.6 (cli) (built: Mar 19 2025 10:08:38) (NTS)
    > Copyright (c) The PHP Group
    > Zend Engine v4.3.6, Copyright (c) Zend Technologies
    >    with Zend OPcache v8.3.6, Copyright (c), by Zend Technologies

Next, we'll need the SQLite package.  To install SQLite, enter the following command:

    $ sudo apt install sqlite3

This will install the `sqlite3` package.

To check the version of `sqlite3` that was installed, enter the following command:

    $ sqlite3 -version
    > 3.45.1 2024-01-30 16:01:20 e876e51a0ed5c5b3126f52e532044363a014bc594cfefa87ffb5b82257ccalt1 (64-bit)

If using Let's Encrypt SSL certificates, Certbot will also need to be installed.
To install Certbot enter the following command:

    sudo apt install certbot python3-certbot-nginx

This will install the following additional packages:

    python3-acme python3-certbot python3-configargparse python3-icu python3-josepy
    python3-parsedatetime python3-rfc3339

It will also create a Systemd service for `certbot.timer`:

    Created symlink /etc/systemd/system/timers.target.wants/certbot.timer
    → /usr/lib/systemd/system/certbot.timer

The Cron and Logrotate services are required, and they should be default on Ubuntu.
For Cron, Ubuntu appears to use Vixie Cron. I don't know how to identify the version of Cron
that is being run other than by checking its Apt package. `crontab -h` will
demonstrate that `cron` is installed. For the log rotator, Red Hat `logrotate`
is installed. `logrotate -v` will demonstrate that `logrotate` is installed.

With that, all required software packages should be installed, and now we can move
on to installing the Bro Chat application.

## The Directory Structure of the Installed Application

It is a fact universally regarded that installing a web application on
Linux is a dark art, because files get smeared all over the place.
This is part of the reason that madness like Docker exists, but, for now,
let's figure out how to install the Bro Chat application into an appropriate
location if we're not using Docker.

Installation directories are an endless source of debate.
There are basically three strategies:
1. Follow PHP application practice and copy the whole project into a
   subdirectory of `/var/www`
2. Use GNU Coding Standards strictly
3. Do whatever makes Docker happy

Each approach has its merits: GNU Coding Standards promote system-wide
consistency, `/var/www` is a typical location for PHP apps, and Docker
dictates its own best practices for containerized applications.
Let's try to enumerate and name all of the directories relevant to this
application.

| Name               | Description |
|--------------------|-------------|
| BROCHAT_PROJECTDIR |  This directory holds all idiosyncratic read-only files from the Bro Chat distribution including HTML, CSS, PHP, and SQL, with the exception of those idiosyncratic read-only files in BROCHAT_WEBROOT. |
| BROCHAT_WEBROOT    | This directory is the webroot directory of the public files being served by the webserver.  It includes public HTML, PHP, CSS and JS. |
| BROCHAT_DBDIR      | The location of the SQLite3 database for this application |
| BROCHAT_LOGDIR     | The location of the logs generated by this application. There are other log directories listed below | 
| BROCHAT_CONFIGDIR  | The location of configuration files specific to this application. |
| SSL_CERTDIR        | The location of SSL public keys |
| SSL_PRIVATEDIR     | The location of SSL private keys |

Here are the default installation directories in the two different styles.

| Name               | PHP App style             | GNU Directory                      |
|--------------------|---------------------------|------------------------------------|
| BROCHAT_PROJECTDIR | `/var/www/brochat`        | `/usr/local/share/brochat`         |
| BROCHAT_WEBROOT    | `/var/www/brochat/public` | `/usr/local/share/brochat/webroot` |
| BROCHAT_DBDIR      | `/var/www/brochat/data`   | `/usr/local/var/brochat/data`      |
| BROCHAT_LOGDIR     | `/var/www/brochat/logs`   | `/usr/local/var/brochat/logs`      |
| BROCHAT_CONFIGDIR  | `/var/www/brochat/config` | `/usr/local/etc/brochat`           |
| SSL_CERTDIR        | `/etc/ssl/certs`          | `/etc/ssl/certs`                   |
| SSL_PRIVATEDIR     | `/etc/ssl/private`        | `/etc/ssl/private`                 |

There is a provided `makefile` you can use to install and uninstall the project.

Here's the reasoning for the directory structure for strict GNU Coding Standards:

    # GNU Coding Standards Install Directories
    PREFIX=/usr/local
    PACKAGE_NAME=brochat

    # Directory for idiosyncratic read-only architecture-independent
    # data files. All non-public PHP scripts would go here.
    DATADIR=$(PREFIX)/share
    # All read-only project files are in the PROJECTDIR directory.
    BROCHAT_PROJECTDIR=$(DATADIR)/$(PACKAGE_NAME)
    # The public HTML and PHP files are in the WEBROOT directory.
    BROCHAT_WEBROOT=$(DATADIR)/$(PACKAGE_NAME)/public

    # Directory for ordinary ASCII text files that contain configuration
    # information and are not modified during operations
    SYSCONFDIR=$(PREFIX)/etc
    # The Bro Chat configuration files are in the CONFIGDIR directory.
    BROCHAT_CONFIGDIR=$(SYSCONFDIR)/$(PACKAGE_NAME)

    # Directory for data files that the program modifies as it runs.
    # So this would include the database file and the log files.
    LOCALSTATEDIR=$(PREFIX)/var
    # The SQLite3 database is in the DBDIR directory.
    BROCHAT_DBDIR=$(LOCALSTATEDIR)/$(PACKAGE_NAME)/data
    # The log files are in the LOGDIR directory.
    BROCHAT_LOGDIR=$(LOCALSTATEDIR)/$(PACKAGE_NAME)/logs

    # The SSL Certificate directory is standardized by distribtuion
    BROCHAT_SSL_CERTDIR=/etc/ssl/certs
    BROCHAT_SSL_PRIVATEDIR=/etc/ssl/private

I'll cover Docker-like install in their own section, later.

Once a directory structure has been chosen, we can move on to configuring
the Nginx webserver.

## Nginx Installation and Configuration

Nginx is configured to serve the static HTML and image files, proxy PHP
requests to PHP-FPM, and reserve a location for future WebSocket proxying.

In this section, I'll discuss two possible configuration files, a development
configuration that ignores key management required for SSL and does not require
a valid server name or URL; and a production configuration that sets up HTTPS with
a valid certificate and which requires a valid server name and URL.  If you
are not intending to do any tweaking and are just going to install it as is,
there is no need to read the section on the development configuration.

### Nginx on Ubuntu and Disabling the Default Nginx Site

On a standard install on Ubuntu, the Ubuntu project has attempted to provide
a framework for configuration files that avoids common pitfalls. They have
created a configuration framework that allows each web application to use
its own specific configuration file.  Each web application's configuration
file is expected to reside in `/etc/nginx/sites-available` and, to enable the site,
there is expected to be a soft link to that file from the `/etc/nginx/sites-enabled`
directory.

There is a default site configuration file at `/etc/nginx/sites-available/default` which
is linked in the directory `/etc/nginx/sites-enabled`.  The default configuration causes Nginx
to serve the webpages at the default webroot location `/var/www/html`.  Since
we'll be moving the webroot, you should delete the link in `/etc/nginx/sites-enabled`; you
can leave the `default` file in `/etc/nginx/sites-available` for future reference.

### Configuring Nginx for Local Development

In this section, we'll create an Nginx configuration file for local development.
This will allow Nginx to server up the web application using plain HTTP on the
localhost, without requiring security certificates or a valid hostname.

In the `config` directory, copy the `brochat-devel-example.conf` file to a file
named `brochat-devel.conf`.

You will need to edit the file to match your expected install location. First,
search for the following line.

    root /var/www/brochat/public;

Replace that directory with the directory you have chosen to be your `BROCHAT_WEBROOT`.

Search for the following line:

    root /var/www/brochat/private;

And replace the `/var/www/brochat` portion of that path with the directory
you have chosen to be your `BROCHAT_PROJECTDIR`.

Make a similar edit to the following line:

    root /var/www/brochat/assets;

Replace the `/var/www/brochat` portion of that directory path with the
directory you have chosen to be your `BROCHAT_PROJECTDIR`.

Save the file, and then copy the file into the `/etc/nginx/sites-available`
so that it becomes `/etc/nginx/sites-available/brochat-devel.conf`
directory.  You may need to use `sudo` to execute the copy. Enable that
configuration by creating a link to it into the
`/etc/nging/sites-enabled` directory by typing the following command:

    ln -sf /etc/nginx/sites-available/brochat-devel.conf /etc/nginx/sites-enabled/brochat-devel.conf

You will need to restart Nginx for that configuration to be enabled.  You can restart it
as a Systemd service:

    sudo systemctl restart nginx.service

### Configuring Nginx for Production Deployment

Configuring Nginx for deployment is similar the the development deployment
in the previous section, with a few additional steps.  The production deployment
will require a domain name and SSL key information.

In the `config` directory, copy the `brochat-production-example.conf` file to a file
named `brochat-production.conf`.

You will need to edit the file to use a real domain name.  This domain
name needs to be the one associated with your SSL keys.  Edit the following line:

    server_name yourdomain.com;

Replace `yourdomain.com` with your domain. It must be the one associated with your
SSL certificates.

Next, ensure the path to your SSL certificates is correct:

  ssl_certificate /etc/ssl/certs/cert.pem;
  ssl_certificate_key /etc/ssl/private/cert.key;

If you are using Let's Encrypt certificates created with `certbot`, you'll set
these paths to FIXME.

You will need to edit the file to match your expected install location. First,
search for the following line.

    root /var/www/brochat/public;

Replace that directory with the directory you have chosen to be your `BROCHAT_WEBROOT`.

Search for the following line:

    root /var/www/brochat/private;

And replace the `/var/www/brochat` portion of that path with the directory
you have chosen to be your `BROCHAT_PROJECTDIR`.

Make a similar edit to the following line:

    root /var/www/brochat/assets;

Replace the `/var/www/brochat` portion of that directory path with the
directory you have chosen to be your `BROCHAT_PROJECTDIR`.

Save the file, and then copy the file into the `/etc/nginx/sites-available`
so that it becomes `/etc/nginx/sites-available/brochat.conf`
directory.  You may need to use `sudo` to execute the copy. Enable that
configuration by creating a link to it into the
`/etc/nging/sites-enabled` directory by typing the following command:

    ln -sf /etc/nginx/sites-available/brochat.conf /etc/nginx/sites-enabled/brochat.conf

You will need to restart Nginx for that configuration to be enabled.  You can restart it
as a Systemd service:

    sudo systemctl restart nginx.service

With the webserver now up and running, our next task will be to make sure
PHP is properly configured.

## PHP and PHP-FPM Configuration

PHP-FPM processes PHP scripts, interfacing with SQLite. The configuration is typically
in `/etc/php/8.3/fpm`.

The normal configuration supplied by Ubuntu should be fine with just a couple of
small modifications for logging and for security.

In the main PHP configuration file, `/etc/php/8.3/fpm/php.ini`, you should modify the
configuration to save errors to a file and to improve security for the session cookie.
The lines to check are the following:

    # error_log is commented out by default, so errors are just written to
    # STDERR.  For production, it should be set to a file. 
    error_log = /var/log/php_errors.log

    # error_reporting is set to log all errors except deprecated and
    # strict errors.  For Development, it is better to have it as E_ALL,
    # but for Production it should be remain as follows, which it the default
    # on Ubuntu.
    error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

    # Have the session cookie only be available to HTTP, and not JavaScript.
    session.cookie_httponly = 1

    # For production, once HTTPS is working, it improves security if you
    # only enable a session cookie on HTTPS
    session.cookie_secure = 1

    # You can set the maximum lifetime of a session.  This is the minimum number
    # of seconds of inactivity before stored data can be seen as garbage
    # that can be collected. Here it is set to 1 week.
    session.gc_maxlifetime = 604800

In the PHP-FPM Pool configuration file `/etc/php/8.3/fpm/pool.d/www.conf`, there
are a couple of lines to modify to ensure coherence with the changes to `php.ini`

    ; Limit the number of requests each child process handles before being restarted
    pm.max_requests = 500  ; Helps mitigate potential memory leaks over time

    ; Logging settings
    slowlog = /var/log/php-fpm.slow.log  ; Logs slow requests for debugging
    request_terminate_timeout = 30s  ; Terminates slow requests exceeding this duration

    ; Security enhancements
    ; In production, you can set the following value.
    php_admin_value[session.cookie_secure] = 1  ; Ensures session cookies are transmitted over HTTPS

    ; This should always be set.
    php_admin_value[session.cookie_httponly] = 1  ; Prevents client-side script access to session cookies

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

## Meta and Colophon

To write this file you're reading now, I used VS Code's Markdown mode.

To wrap the paragraphs, I used stkb's Rewrap extension v1.16.3, which wraps a paragraph by pressing Alt+Q.

