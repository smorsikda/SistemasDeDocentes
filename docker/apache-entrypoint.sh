#!/bin/sh
set -eu

port="${PORT:-80}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT debe ser un número entre 1 y 65535." >&2
        exit 1
        ;;
esac

if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    echo "PORT debe ser un número entre 1 y 65535." >&2
    exit 1
fi

sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
