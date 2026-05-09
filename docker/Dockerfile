FROM php:8.3-apache-trixie

# Full distro upgrade to eliminate known CVEs in the base image packages.
# Rebuild the image periodically to keep up with new fixes.
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get dist-upgrade -y \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        libapache2-mod-auth-gssapi \
        krb5-user \
        libldap2-dev \
        libicu-dev \
        curl \
    && docker-php-ext-install pdo_sqlite ldap intl \
    && a2enmod auth_gssapi rewrite headers \
    && a2dismod status autoindex 2>/dev/null || true \
    && rm -rf /var/lib/apt/lists/*

# All configuration files (apache.conf, security.conf, php-security.ini) are
# mounted at runtime via docker-compose.yml — no COPY needed, no rebuild required
# when they change.

# Create the data directory. App files are provided at runtime via a volume mount.
RUN mkdir -p /var/www/data && chown www-data:www-data /var/www/data

EXPOSE 80
