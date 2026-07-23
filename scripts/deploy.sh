#!/usr/bin/env bash

set -Eeuo pipefail

# ------------------------------------------------------------------
# Injected from GitHub Actions
# ------------------------------------------------------------------

: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${REPO:?REPO is required}"

RELEASES="$DEPLOY_PATH/releases"
SHARED="$DEPLOY_PATH/shared"
CURRENT="$DEPLOY_PATH/current"

STAMP="$(date +%Y%m%d%H%M%S)"
NEW="$RELEASES/$STAMP"

KEEP=5

PREV="$(readlink -f "$CURRENT" || true)"

rollback() {
    echo ">>> Deployment failed."

    if [ -n "$PREV" ]; then
        echo ">>> Rolling back to $PREV"
        ln -sfn "$PREV" "$CURRENT"
    fi

    sudo systemctl reload php8.4-fpm || true

    php "$CURRENT/artisan" up || true

    php "$CURRENT/artisan" horizon:terminate || true

    exit 1
}

trap rollback ERR

mkdir -p "$RELEASES"

echo ">>> Cloning release $STAMP"

git clone \
    --depth 1 \
    "$REPO" \
    "$NEW"

cd "$NEW"

echo ">>> Linking shared resources"

rm -rf storage

ln -s "$SHARED/storage" storage
ln -s "$SHARED/.env" .env

echo ">>> Installing Composer dependencies"

composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

echo ">>> Setting permissions"

chown -R usir:www-data storage bootstrap/cache

chmod -R ug+rwx storage bootstrap/cache

if [ -L "$CURRENT" ]; then
    echo ">>> Maintenance mode"

    php "$CURRENT/artisan" down \
        --render="errors::503" \
        --retry=15 || true
fi

echo ">>> Running database migrations"

php artisan migrate --force

echo ">>> Optimizing Laravel"

php artisan config:cache

php artisan route:cache

php artisan event:cache

php artisan view:cache

echo ">>> Switching release"

ln -sfn "$NEW" "$CURRENT"

echo ">>> Reloading PHP"

sudo systemctl reload php8.4-fpm

echo ">>> Restarting Horizon"

php "$CURRENT/artisan" horizon:terminate

echo ">>> Bringing application online"

php "$CURRENT/artisan" up

echo ">>> Cleaning old releases"

cd "$RELEASES"

ls -1dt */ | tail -n +$((KEEP + 1)) | xargs -r rm -rf

echo ">>> Deployment completed successfully."
