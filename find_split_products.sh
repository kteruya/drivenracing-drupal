#!/usr/bin/env bash
set -euo pipefail

# --- DRUSH resolver (project-first) ---
DRUSH="${DRUSH:-}"
if [ -z "$DRUSH" ]; then
  if [ -x "./vendor/bin/drush" ]; then
    DRUSH="./vendor/bin/drush"
  elif command -v $DRUSH >/dev/null 2>&1; then
    DRUSH="$(command -v drush)"
  else
    echo "Error: Drush not found. Run composer install or add $DRUSH to PATH." >&2
    exit 1
  fi
fi
# --- end DRUSH resolver ---
ROOT="web"
ARGS=()
for arg in "$@"; do
  case "$arg" in
    --root=*) ROOT="${arg#*=}";;
    --images-field=*|--key=*|--bundle=*|--max=*) ARGS+=("$arg");;
    --csv|--show-ambiguous) ARGS+=("$arg");;
    --help|-h)
      echo "Usage: $0 [--root=web] [--images-field=field_images] [--key=title] [--bundle=default] [--max=200] [--csv] [--show-ambiguous]"
      exit 0;;
    *) echo "Unknown option: $arg" >&2; exit 1;;
  esac
done
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="$SELF_DIR/find_split_products.php"
$DRUSH --root="$ROOT" scr "$PHP_SCRIPT" -- "${ARGS[@]}"
