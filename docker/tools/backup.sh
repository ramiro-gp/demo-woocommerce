#!/bin/sh
set -eu
cd /var/www/html
backup_id=$1
case "$backup_id" in ''|*[!a-zA-Z0-9_-]*) echo 'Invalid backup ID' >&2; exit 1;; esac
target="/backups/$backup_id"
mkdir -p "$target"
wp db export "$target/database.sql" --single-transaction --quick --skip-lock-tables --add-drop-table
tar -czf "$target/wordpress.tar.gz" --exclude='./wp-content/mu-plugins/000-local-mail.php' -C /var/www/html .
