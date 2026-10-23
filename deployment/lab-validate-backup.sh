#!/bin/sh
# LAB only; archive must already pass its external SHA256 manifest.
set -eu
archive=/h11-backups/site.tar.gz
tar -tzf "$archive" > /tmp/h11-entries.txt
while IFS= read -r name; do
    case "$name" in
        /*|..|../*|*/../*|*/..|*\\*|*//*|*'/./'*|./wp-content/mu-plugins/h11-lab-mail.php)
            echo 'Unsafe H11 archive path or lab transport overwrite' >&2; exit 1;;
    esac
done < /tmp/h11-entries.txt
tar -tvzf "$archive" > /tmp/h11-details.txt
awk 'substr($0,1,1) != "-" && substr($0,1,1) != "d" {bad=1} END {exit bad}' /tmp/h11-details.txt
grep -qx './wp-config.php' /tmp/h11-entries.txt
grep -qx './wp-content/plugins/woocommerce/woocommerce.php' /tmp/h11-entries.txt
