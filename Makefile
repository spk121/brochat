# Configuration variable (set to DEVEL or PRODUCTION)
CONFIG ?= DEVEL

# Validate CONFIG
ifeq ($(filter $(CONFIG),DEVEL PRODUCTION),)
	$(error CONFIG must be DEVEL or PRODUCTION)
endif

# Define installation style: 'gnu' or 'php'
INSTALL_STYLE ?= php  # Default to PHP app style

# Validate INSTALL_STYLE
ifeq ($(filter $(INSTALL_STYLE),gnu php),)
	$(error INSTALL_STYLE must be gnu or php)
endif

# Directories based on installation style
PACKAGE_NAME=brochat
ifeq ($(INSTALL_STYLE), gnu)
	PREFIX=/usr/local
	DATADIR=$(PREFIX)/share
	PKGDATADIR=$(DATADIR)/$(PACKAGE_NAME)
	LOCALSTATEDIR=$(PREFIX)/var
	PKGLOCALSTATEDIR=$(LOCALSTATEDIR)/$(PACKAGE_NAME)
	SYSCONFDIR=$(PREFIX)/etc
	PKGSYSCONFDIR=$(SYSCONFDIR)/$(PACKAGE_NAME)
	BROCHAT_PROJECTDIR=$(PKGDATADIR)
	BROCHAT_WEBROOT=$(PKGDATADIR)/public
	BROCHAT_DBDIR=$(PKGLOCALSTATEDIR)/data
	BROCHAT_LOGDIR=$(PKGLOCALSTATEDIR)/logs
	BROCHAT_CONFIGDIR=$(PKGSYSCONFDIR)
else
	INSTALLDIR=/var/www/$(PACKAGE_NAME)
	BROCHAT_PROJECTDIR=$(INSTALLDIR)
	BROCHAT_WEBROOT=$(INSTALLDIR)/public
	BROCHAT_DBDIR=$(INSTALLDIR)/data
	BROCHAT_LOGDIR=$(INSTALLDIR)/logs
	BROCHAT_CONFIGDIR=$(INSTALLDIR)/config
endif

# Website Domain Name
BROCHAT_DOMAIN ?= yourdomain.com

# SSL Certificates associated with this domain
SSL_CERTDIR=/etc/ssl/certs
SSL_PRIVATEDIR=/etc/ssl/private
SSL_CERT_FILE = /etc/ssl/certs/cert.pem
SSL_KEY_FILE = /etc/ssl/private/cert.key

# PHP-FPM paths
PHP_FPM_SOCK = /run/php/php8.3-fpm.sock

# Websocket server
# This port can be any port above 1024 since it only visible on localhost.
WEBSOCKET_PORT = 4567

# Nginx directories
NGINX_SITES_AVAILABLE_DIR = /etc/nginx/sites-available
NGINX_SITES_ENABLED_DIR = /etc/nginx/sites-enabled

# Target for building the database
BROCHAT_DB=data/brochat.db
INIT_SQL=database/init.sql

# Default rule
all: \
  data/brochat.db \
  config/brochat.conf \
  config/brochat-devel.conf

# Build the database
data/brochat.db:
	@echo "Initializing BroChat database..."
	mkdir -p data
	sqlite3 $@ < $(INIT_SQL)
	@echo "Database creation complete!"

# Rule to generate brochat.conf from brochat.conf.in
config/brochat.conf: config/brochat.conf.in
	sed -e 's|@BROCHAT_WEBROOT@|$(BROCHAT_WEBROOT)|g' \
		 -e 's|@BROCHAT_PROJECTDIR@|$(BROCHAT_PROJECTDIR)|g' \
		 -e 's|@BROCHAT_LOGDIR@|$(BROCHAT_LOGDIR)|g' \
		 -e 's|@PHP_FPM_SOCK@|$(PHP_FPM_SOCK)|g' \
		 -e 's|@SSL_CERT_FILE@|$(SSL_CERT_FILE)|g' \
		 -e 's|@SSL_KEY_FILE@|$(SSL_KEY_FILE)|g' \
		 -e 's|@BROCHAT_DOMAIN@|$(BROCHAT_DOMAIN)|g' \
		 -e 's|@PROD_WEBSOCKET_PORT@|$(PROD_WEBSOCKET_PORT)|g' \
		 $< > $@

# Rule to generate brochat-devel.conf from brochat-devel.conf.in
config/brochat-devel.conf: config/brochat-devel.conf.in
	sed -e 's|@BROCHAT_WEBROOT@|$(BROCHAT_WEBROOT)|g' \
		 -e 's|@BROCHAT_PROJECTDIR@|$(BROCHAT_PROJECTDIR)|g' \
		 -e 's|@BROCHAT_LOGDIR@|$(BROCHAT_LOGDIR)|g' \
		 -e 's|@PHP_FPM_SOCK@|$(PHP_FPM_SOCK)|g' \
		 -e 's|@BROCHAT_DOMAIN@|$(BROCHAT_DOMAIN)|g' \
		 -e 's|@WEBSOCKET_PORT@|$(WEBSOCKET_PORT)|g' \
		 -e 's|@SSL_CERT_FILE@|$(SSL_CERT_FILE)|g' \
		 -e 's|@SSL_KEY_FILE@|$(SSL_KEY_FILE)|g' \
		 $< > $@

# Clean rule
clean:
	@echo "Cleaning up..."
	rm -f config/brochat.conf
	rm -f config/brochat-devel.conf
	@echo "Cleanup complete!"

# Installation commands
install: config/brochat.conf config/brochat-devel.conf
	@echo "Installing BroChat using $(INSTALL_STYLE) style..."
	@install -d $(NGINX_SITES_AVAILABLE_DIR)
	@install -d $(NGINX_SITES_ENABLED_DIR)
	@install -d $(BROCHAT_WEBROOT)
	@install -d $(BROCHAT_PROJECTDIR)/private
	@install -d $(BROCHAT_PROJECTDIR)/assets
	@chmod 755 $(BROCHAT_WEBROOT) $(BROCHAT_PROJECTDIR)/private $(BROCHAT_PROJECTDIR)/assets
	@install -d $(BROCHAT_LOGDIR)
	@chown www-data:www-data $(BROCHAT_LOGDIR)
	@chmod 770 $(BROCHAT_LOGDIR)
	@if [ -f $(BROCHAT_DBDIR)/brochat.db ]; then \
		echo "ERROR: brochat.db already exists in $(BROCHAT_DBDIR). Installation aborted."; \
		exit 1; \
	fi
	@mkdir -p $(BROCHAT_DBDIR)
	@cp $(BROCHAT_DB) $(BROCHAT_DBDIR)/brochat.db	
	@mkdir -p $(BROCH$(BROCHAT_CONFIGDIR)
	@if [ "$(CONFIG)" = "DEVEL" ]; then \
		install -m 644 config/brochat-devel.conf $(NGINX_SITES_AVAILABLE_DIR)/brochat.conf; \
		ln -sf $(NGINX_SITES_AVAILABLE_DIR)/brochat.conf $(NGINX_SITES_ENABLED_DIR)/brochat.conf; \
		echo "Installed development configuration to $(NGINX_SITES_AVAILABLE_DIR)/brochat.conf and linked to $(NGINX_SITES_ENABLED_DIR)/brochat.conf"; \
	else \
		install -d $(dir $(SSL_CERT_FILE)) $(dir $(SSL_KEY_FILE)); \
		install -m 644 config/brochat.conf $(NGINX_SITES_AVAILABLE_DIR)/brochat.conf; \
		ln -sf $(NGINX_SITES_AVAILABLE_DIR)/brochat.conf $(NGINX_SITES_ENABLED_DIR)/brochat.conf; \
		echo "Installed production configuration to $(NGINX_SITES_AVAILABLE_DIR)/brochat.conf and linked to $(NGINX_SITES_ENABLED_DIR)/brochat.conf"; \
	fi
	@echo "Please reload Nginx to apply the new configuration (e.g., 'sudo nginx -t && sudo nginx -s reload')"
	@echo "Installation complete!"

# Uninstall commands
uninstall:
	@echo "Removing BroChat installation, but preserving $(BROCHAT_DBDIR)..."
	@mv $(BROCHAT_DBDIR) /tmp/brochat_data || { echo "ERROR: Could not move $(BROCHAT_DBDIR) to /tmp. Aborting uninstall."; exit 1; }
	@rm -rf $(BROCHAT_PROJECTDIR)/*
	@mv /tmp/brochat_data $(BROCHAT_DBDIR)
	@echo "Uninstall complete!"

# Help command
help:
	@echo "Usage: make INSTALL_STYLE=<php|gnu> install"
	@echo "	   make uninstall"

