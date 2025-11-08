#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$PWD}"
cd "$ROOT"

if [ ! -d myshop/web ]; then
  echo "[abort] myshop/ not found under $ROOT"
  exit 1
fi

TS=$(date +%F-%H%M)
ARCH="../archive"; mkdir -p "$ARCH"

echo "[1/5] myshop drupal version"
myshop/vendor/bin/drush --root=myshop/web ev "echo 'Drupal::VERSION='. \Drupal::VERSION . PHP_EOL;" || true
myshop/vendor/bin/drush --root=myshop/web status || true

echo "[2/5] db dump (if boots)"
if myshop/vendor/bin/drush --root=myshop/web status >/dev/null 2>&1; then
  myshop/vendor/bin/drush --root=myshop/web sql:dump --result-file="$ARCH/myshop-$TS.sql.gz" || echo "[warn] sql:dump skipped"
fi

echo "[3/5] tar myshop/"
tar -czf "$ARCH/myshop-$TS.tgz" myshop

echo "[4/5] move myshop out of tree"
mv myshop "$ARCH/myshop-$TS.dir"

echo "[5/5] update git ignore & commit (if repo)"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  grep -qx "myshop/" .gitignore || echo "myshop/" >> .gitignore
  git rm -r --cached myshop 2>/dev/null || true
  git add .gitignore
  git commit -m "Archive myshop/ to $ARCH/myshop-$TS.* and ignore legacy subtree" || true
fi

echo "[done] archived to $ARCH/myshop-$TS.tgz and moved to $ARCH/myshop-$TS.dir"
