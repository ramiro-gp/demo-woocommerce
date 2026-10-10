#!/bin/sh
set -eu
backup_id=$1
case "$backup_id" in ''|*[!a-zA-Z0-9_-]*) echo 'Invalid backup ID' >&2; exit 1;; esac
archive="/backups/$backup_id/wordpress.tar.gz"
tar -tzf "$archive" > /tmp/backup-entries.txt
while IFS= read -r name; do
    case "$name" in
        /*|..|../*|*/../*|*/..|*\\*|*//*|*'/./'*|wp-content/mu-plugins/000-local-mail.php|./wp-content/mu-plugins/000-local-mail.php)
            echo 'Unsafe archive path or local tooling overwrite' >&2; exit 1;;
    esac
done < /tmp/backup-entries.txt
# This local backup format accepts regular files and directories only.
tar -tvzf "$archive" | awk 'substr($0,1,1) != "-" && substr($0,1,1) != "d" {bad=1} END {exit bad}'
