#!/bin/bash
set -e

# Port configuration for Render (Render passes $PORT, default is 80)
PORT_TO_USE="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT_TO_USE}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT_TO_USE}/g" /etc/apache2/sites-available/000-default.conf

echo "Apache configured to listen on port ${PORT_TO_USE}"

# Optional automatic DB initialization if database credentials are provided
if [ -n "$DB_HOST" ] || [ -n "$DATABASE_URL" ] || [ -n "$MYSQLHOST" ]; then
    echo "Checking database connection and running auto-init..."
    php /var/www/html/init_db_auto.php || true
fi

# Execute CMD
exec "$@"
