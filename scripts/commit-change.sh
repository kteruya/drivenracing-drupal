#!/bin/bash
# commit-change.sh — Stage and commit custom theme/module changes
# Usage: ./scripts/commit-change.sh "commit message"

if [ -z "$1" ]; then
  echo "Usage: $0 'commit message'"
  exit 1
fi

cd ~/drupal
git add web/themes/custom/driven/ web/modules/custom/driven_tweaks/ web/modules/custom/driven_migrations/
git status --short -- web/themes/custom/driven/ web/modules/custom/driven_tweaks/ web/modules/custom/driven_migrations/
echo ""
git commit -m "$1"
echo ""
git log --oneline -3
