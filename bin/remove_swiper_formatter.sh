#!/usr/bin/env bash
set -euo pipefail
echo "[1/4] checking for config still referencing swiper"
REFS=$(drush ev '$s=\Drupal::service("config.storage"); $hits=[]; foreach($s->listAll() as $n){$d=$s->read($n); if (is_array($d) && str_contains(json_encode($d),"swiper")) $hits[]=$n;} echo implode(PHP_EOL,$hits);')
if [ -n "$REFS" ]; then
  echo "[block] update these configs to stop using Swiper first:"
  echo "$REFS"
  exit 1
fi
echo "[2/4] uninstall module"
drush pm:uninstall swiper_formatter -y || true
echo "[3/4] remove code via composer"
composer remove drupal/swiper_formatter -W || true
echo "[4/4] drush cr"
drush cr || true
echo "[done]"
