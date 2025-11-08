#!/usr/bin/env bash
set -euo pipefail
git config --global user.name  "Keith Teruya"
git config --global user.email "kteruya@digitalhousing.net"
git config --global init.defaultBranch main
git config --global user.useConfigOnly true
echo "[ok] Git identity: $(git config --global user.name) <$(git config --global user.email)>"
