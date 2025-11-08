#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$PWD}"
cd "$ROOT"

echo "[1/6] maintenance on"
drush sset system.maintenance_mode 1 || true
drush cr || true

echo "[2/6] db backup"
mkdir -p ../db-backups
drush sql-dump --result-file="../db-backups/pre-core-$(date +%F-%H%M).sql.gz" || true

echo "[3/6] ensure flexible constraints (^11.2)"
sed -i 's/"drupal\/core-recommended": *"11\.2\.[0-9]\+"/"drupal\/core-recommended": "^11.2"/' composer.json || true
sed -i 's/"drupal\/core-composer-scaffold": *"11\.2\.[0-9]\+"/"drupal\/core-composer-scaffold": "^11.2"/' composer.json || true
sed -i 's/"drupal\/core-project-message": *"11\.2\.[0-9]\+"/"drupal\/core-project-message": "^11.2"/' composer.json || true

echo "[4/6] update core packages"
composer update drupal/core-recommended drupal/core-composer-scaffold drupal/core-project-message --with-all-dependencies
composer drupal:scaffold || true

echo "[5/6] db updates & cache"
drush updatedb -y || true
drush cr || true
drush state:del update.last_check || true
drush cron || true
drush cr || true
drush ev "echo 'Runtime Drupal: ' . \Drupal::VERSION . PHP_EOL;" || true

echo "[6/6] maintenance off"
drush sset system.maintenance_mode 0 || true
drush cr || true
echo "[done]"
