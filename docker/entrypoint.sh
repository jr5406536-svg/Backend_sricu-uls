#!/bin/sh
set -eu
cd /var/www/html

mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ -n "${DATABASE_URL:-}" ]; then
    export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
    export DB_URL="$DATABASE_URL"
fi

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

if [ ! -f vendor/autoload.php ]; then
    php /usr/local/bin/composer install --no-interaction --prefer-dist --no-scripts --optimize-autoloader
fi

if [ -z "${APP_KEY:-}" ]; then
    if [ -f .env ]; then
        php artisan key:generate --force
    else
        export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
        echo "APP_KEY existe solo en este contenedor. Cópiala a las variables de Render para que no cambie al volver a desplegar."
    fi
fi

port="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

attempt=0
until php -r '
    $url = getenv("DB_URL") ?: getenv("DATABASE_URL");
    if ($url) {
        $parts = parse_url($url);
        $host = $parts["host"] ?? "127.0.0.1";
        $port = $parts["port"] ?? 5432;
        $database = ltrim($parts["path"] ?? "", "/");
        $user = isset($parts["user"]) ? rawurldecode($parts["user"]) : "";
        $password = isset($parts["pass"]) ? rawurldecode($parts["pass"]) : "";
    } else {
        $host = getenv("DB_HOST");
        $port = getenv("DB_PORT") ?: "5432";
        $database = getenv("DB_DATABASE");
        $user = getenv("DB_USERNAME");
        $password = getenv("DB_PASSWORD");
    }
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $ssl = getenv("DB_SSLMODE");
    if ($ssl) {
        $dsn .= ";sslmode={$ssl}";
    }
    new PDO($dsn, $user, $password);
' >/dev/null 2>&1
do
    attempt=$((attempt + 1))
    if [ "$attempt" -gt 30 ]; then
        echo "No se pudo conectar a PostgreSQL."
        exit 1
    fi
    sleep 2
done

php artisan migrate --force
php artisan db:seed --force
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
