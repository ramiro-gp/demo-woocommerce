#!/bin/sh
set -eu
cd /var/www/html
actual_version=$(wp core version)
if [ "$actual_version" != "$LOCAL_EXPECTED_WORDPRESS_VERSION" ]; then
    echo "Persisted WordPress core differs from the pinned version; migrate deliberately before starting." >&2
    exit 1
fi
pending=/var/www/html/.h1-bootstrap-pending
if wp core is-installed >/dev/null 2>&1 && [ ! -f "$pending" ]; then
    echo "WordPress already installed; existing settings and content preserved."
    exit 0
fi
if ! wp core is-installed >/dev/null 2>&1; then
    touch "$pending"
    # WP-CLI's prompt can echo the completed command even without a TTY.
    # Never forward that transcript: it contains the administrator password.
    if ! wp core install \
        --url="$LOCAL_SITE_URL" \
        --title="Commerce Local" \
        --admin_user="$LOCAL_ADMIN_USER" \
        --admin_email="$LOCAL_ADMIN_EMAIL" \
        --locale=es_AR \
        --skip-email \
        --prompt=admin_password < /run/secrets/wp_admin_password >/dev/null 2>&1; then
        echo 'Initial WordPress installation failed. Check database readiness and local configuration, then retry start.' >&2
        exit 1
    fi
fi
wp option update timezone_string America/Argentina/Buenos_Aires
wp option update blog_public 0
wp language core install es_AR --activate
rm -f "$pending"
echo 'WordPress installed successfully; administrator credentials remain in the private local files.'
