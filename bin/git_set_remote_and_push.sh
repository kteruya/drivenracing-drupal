#!/usr/bin/env bash
set -euo pipefail
cd "${1:-$PWD}"

# ensure SSH ready
eval "$(ssh-agent -s)" >/dev/null
ssh-add "$HOME/.ssh/id_ed25519" >/dev/null || true

# set origin to SSH URL with your username
git remote set-url origin git@github.com:kteruya/drivenracing-drupal.git
git remote -v

# quick auth test (will greet you by your GitHub handle if key is added in GitHub)
ssh -T git@github.com || true

# push branch and optional tag
git push -u origin "$(git rev-parse --abbrev-ref HEAD)"
if git rev-parse -q --verify v2025-11-08-core-11.2.7 >/dev/null; then
  git push origin v2025-11-08-core-11.2.7
fi
