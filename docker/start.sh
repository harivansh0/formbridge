#!/bin/sh
set -e

[ -f vendor/autoload.php ] || composer install --no-interaction --no-progress

php bin/console doctrine:migrations:migrate --no-interaction

# Fixtures are a development dependency. They are loaded while there are no sessions yet, so an
# interrupted first start is completed by the next one.
if php bin/console list doctrine --raw | grep -q '^doctrine:fixtures:load' \
    && php bin/console dbal:run-sql 'SELECT COUNT(*) FROM workshop_session' | grep -Eq '^ +0 *$'; then
    php bin/console doctrine:fixtures:load --no-interaction
fi

echo "Application:  http://localhost:8000"
echo "MCP endpoint: http://localhost:8000/mcp"
exec php -S 0.0.0.0:8000 -t public
