#!/usr/bin/env bash
set -euo pipefail

# ensure an ed25519 key exists (reuse if present)
if [ ! -f "$HOME/.ssh/id_ed25519" ]; then
  mkdir -p "$HOME/.ssh"; chmod 700 "$HOME/.ssh"
  ssh-keygen -t ed25519 -C "kteruya@digitalhousing.net" -f "$HOME/.ssh/id_ed25519" -N ""
  echo "[ok] generated SSH key at ~/.ssh/id_ed25519"
fi

# configure SSH for GitHub to force the right identity
mkdir -p "$HOME/.ssh"; chmod 700 "$HOME/.ssh"
if ! grep -q "Host github.com" "$HOME/.ssh/config" 2>/dev/null; then
  cat >> "$HOME/.ssh/config" <<CFG
Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/id_ed25519
  IdentitiesOnly yes
CFG
  chmod 600 "$HOME/.ssh/config"
  echo "[ok] wrote ~/.ssh/config entry for github.com"
fi

# load key into agent
eval "$(ssh-agent -s)" >/dev/null
ssh-add "$HOME/.ssh/id_ed25519" >/dev/null || true

# show the public key for you to add in GitHub → Settings → SSH and GPG keys
echo
echo "==== Add this SSH public key to GitHub (Settings → SSH and GPG keys) ===="
cat "$HOME/.ssh/id_ed25519.pub"
echo "=========================================================================="
echo
echo "[tip] After adding the key, test with:  ssh -T git@github.com"
