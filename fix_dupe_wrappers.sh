#!/usr/bin/env bash
set -euo pipefail
patch_one() {
  local f="$1"
  [ -f "$f" ] || { echo "skip $f (missing)"; return; }

  # 1) Inject robust DRUSH resolver right after 'set -euo pipefail'
  awk '
    BEGIN{done=0}
    /set -euo pipefail/ && done==0 {
      print;
      print "";
      print "# --- DRUSH resolver (project-first) ---";
      print "DRUSH=\"${DRUSH:-}\"";
      print "if [ -z \"$DRUSH\" ]; then";
      print "  if [ -x \"./vendor/bin/drush\" ]; then";
      print "    DRUSH=\"./vendor/bin/drush\"";
      print "  elif command -v drush >/dev/null 2>&1; then";
      print "    DRUSH=\"$(command -v drush)\"";
      print "  else";
      print "    echo \"Error: Drush not found. Run composer install or add drush to PATH.\" >&2";
      print "    exit 1";
      print "  fi";
      print "fi";
      print "# --- end DRUSH resolver ---";
      done=1; next
    }
    { print }
  ' "$f" > "$f.tmp" && mv "$f.tmp" "$f"

  # 2) Ensure all drush calls use $DRUSH (handles multiple occurrences)
  sed -i 's/\bdrush\b[[:space:]]\+/\$DRUSH /g' "$f"

  # 3) Ensure script args are forwarded after a `--` separator to the PHP script
  #    (two common patterns covered)
  sed -i 's/scr "\$PHP_SCRIPT" "\${ARGS\[@\]\}"/scr "$PHP_SCRIPT" -- "${ARGS[@]}"/' "$f"
  sed -i 's/scr "\$PHP_SCRIPT" \\"\${ARGS\[@\]\}\\"/scr "$PHP_SCRIPT" -- "${ARGS[@]}"/' "$f"

  chmod +x "$f"
  echo "patched $f"
}

patch_one backup_db.sh
patch_one find_split_products.sh
patch_one merge_product_dupes.sh
patch_one rollback_merge_dupes.sh
