#!/bin/sh
set -eu
cd /var/www/html
backup_id=$1
case "$backup_id" in ''|*[!a-zA-Z0-9_-]*) echo 'Invalid backup ID' >&2; exit 1;; esac
source="/backups/$backup_id"
test -f "$source/database.sql"
test -f "$source/wordpress.tar.gz"
# All deletions are confined to this Compose project's named HTML volume.
# Keep the read-only local mail tooling bind mounted by Compose.
find /var/www/html -mindepth 1 -maxdepth 1 ! -name wp-content -exec rm -rf -- {} +
find /var/www/html/wp-content -mindepth 1 -maxdepth 1 ! -name mu-plugins -exec rm -rf -- {} +
find /var/www/html/wp-content/mu-plugins -mindepth 1 -maxdepth 1 ! -name 000-local-mail.php -exec rm -rf -- {} +
# Docker creates the parent of the read-only MU bind as root. Preserve existing
# directory ownership/modes/times so extraction also works as Apache's UID 33.
tar -xzmf "$source/wordpress.tar.gz" --no-same-owner --no-same-permissions -C /var/www/html
wp db reset --yes
wp db import "$source/database.sql"
test "$(wp core version)" = "$LOCAL_EXPECTED_WORDPRESS_VERSION"
wp core is-installed
