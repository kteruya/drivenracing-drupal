#!/usr/bin/env bash
set -euo pipefail
cd "${1:-$PWD}"

git remote set-url origin https://github.com/kteruya/drivenracing-drupal.git
git remote -v

echo "[info] On push you will be prompted for GitHub username (kteruya) and a Personal Access Token (as password)."
git push -u origin "$(git rev-parse --abbrev-ref HEAD)"
if git rev-parse -q --verify v2025-11-08-core-11.2.7 >/dev/null; then
  git push origin v2025-11-08-core-11.2.7
fi
