#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="${1:-$PWD}"
cd "$REPO_ROOT"

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "[info] Already a git repo at $(git rev-parse --show-toplevel)"
else
  git init
  git branch -m main || true
  git config user.name  "Keith Teruya"
  git config user.email "kteruya@digitalhousing.net"
  echo "[ok] initialized repo on branch main"
fi

# sensible ignores for Drupal 11 Composer projects
if [ ! -f .gitignore ]; then
cat > .gitignore <<'GI'
/vendor/
/composer.phar
/node_modules/
web/sites/*/files/
web/sites/*/private/
web/sites/*/translations/
web/sites/*/settings.php
web/sites/*/settings.local.php
web/sites/*/services.yml
web/sites/*/development.services.yml
!web/sites/*/example.settings.local.php
!web/sites/*/example.sites.php
web/themes/*/dist/
web/themes/*/build/
web/modules/custom/**/node_modules/
web/core/assets/vendor/
myshop/
.DS_Store
Thumbs.db
*.swp
*.swo
.idea/
.vscode/
GI
fi

# minimal .gitattributes
if [ ! -f .gitattributes ]; then
cat > .gitattributes <<'GA'
* text=auto eol=lf
/.gitignore export-ignore
/.gitattributes export-ignore
/.editorconfig export-ignore
/.github export-ignore
/tests export-ignore
GA
fi

mkdir -p web/sites/default/files
: > web/sites/default/files/.gitkeep

git add -A
git commit -m "Initial repo setup (.gitignore/.gitattributes, baseline)" || true

# set remote (does not push)
if git remote get-url origin >/dev/null 2>&1; then
  echo "[info] origin already set to: $(git remote get-url origin)"
else
  git remote add origin git@github.com:kteruya/drivenracing-drupal.git
  echo "[ok] origin set to git@github.com:kteruya/drivenracing-drupal.git"
  echo "[next] run:  ./bin/git_setup_github_ssh.sh  then:  ssh -T git@github.com  and finally:  git push -u origin main"
fi
