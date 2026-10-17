#!/bin/sh
set -eu

port="${PORT:-80}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT debe ser un número entre 1 y 65535." >&2
        exit 64
        ;;
esac
if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    echo "PORT debe ser un número entre 1 y 65535." >&2
    exit 64
fi

if [ -n "${POSTGRES_URL:-}${POSTGRES_URL_NON_POOLING:-}" ]; then
    # 2 = esquema aplicado pero degradado (alguna tabla sin su llave primaria por datos
    # repetidos). Se avisa y la app arranca: tumbar el servicio no corrige esos datos.
    estado=0
    php /var/www/html/services/supabase-database/migrate.php || estado=$?
    if [ "$estado" -eq 2 ]; then
        echo "AVISO: migrate.php dejó el esquema degradado; revise supabase_primary_key_pendiente." >&2
    elif [ "$estado" -ne 0 ]; then
        exit "$estado"
    fi
fi

sed -i "s/^Listen .*/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
