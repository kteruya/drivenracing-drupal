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
ROOT="web"; OUTDIR=""
for a in "$@"; do
  case "$a" in
    --root=*) ROOT="${a#*=}";;
    --outdir=*) OUTDIR="${a#*=}";;
    --help|-h) echo "Usage: $0 [--root=web] [--outdir=web/db-backups]"; exit 0;;
    *) echo "Unknown option: $a" >&2; exit 1;;
  esac
done
: "${OUTDIR:=web/db-backups}"
mkdir -p "$OUTDIR"
TS=$(date +%F-%H%M)
# Let Drush add .gz (pass .sql)
$DRUSH --root="$ROOT" sql-dump --gzip --result-file="$OUTDIR/pre-merge-dupes-$TS.sql"
echo "Backup written to $OUTDIR/pre-merge-dupes-$TS.sql.gz"
