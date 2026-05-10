FROM debian:trixie-slim

# Full distro upgrade to eliminate known CVEs in the base image packages.
# Rebuild the image periodically to keep up with new fixes.
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get dist-upgrade -y \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php \
        libapache2-mod-auth-gssapi \
        php-sqlite3 \
        php-ldap \
        php-intl \
        php-xml \
        krb5-user \
        curl \
    && a2enmod rewrite headers auth_gssapi \
    && a2dismod status autoindex 2>/dev/null || true \
    && PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;') \
    && ln -s /etc/php/${PHP_VERSION}/apache2/conf.d /etc/php/apache2-conf.d \
    && rm -rf /var/lib/apt/lists/*

# All configuration files (apache.conf, security.conf, php-security.ini) are
# mounted at runtime via docker-compose.yml — no COPY needed, no rebuild required
# when they change.

# Create the data directory. App files are provided at runtime via a volume mount.
RUN mkdir -p /var/www/data && chown www-data:www-data /var/www/data

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
