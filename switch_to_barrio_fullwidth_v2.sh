#!/usr/bin/env bash
set -euo pipefail

ROOT_OPT="--root=web"
DR="./vendor/bin/drush $ROOT_OPT"

echo "== Install/enable Bootstrap Barrio 5.x (D11-compatible) =="
composer require drupal/bootstrap_barrio:^5 -W
$DR en bootstrap_barrio -y

echo "== Set front-end theme to Bootstrap Barrio =="
$DR cset system.theme default bootstrap_barrio -y

echo "== Make Barrio full-width (container-fluid) =="
# These keys exist on Barrio 5.x. If they prompt as new, that's fine.
$DR cset bootstrap_barrio.settings fluid_container 1 -y || true
$DR cset bootstrap_barrio.settings navbar_container 0 -y || true
$DR cset bootstrap_barrio.settings breadcrumb_container 0 -y || true
$DR cset bootstrap_barrio.settings content_container 0 -y || true
$DR cset bootstrap_barrio.settings footer_container 0 -y || true

echo "== Ensure driven_tweaks stays enabled =="
$DR pm:list --type=module --status=enabled | grep -qi '^.*driven_tweaks' || $DR en driven_tweaks -y

echo "== Place a Superfish Main menu block in Barrio header =="
php -d detect_unicode=0 -r '
use Drupal\block\Entity\Block;
$theme = "bootstrap_barrio";
$id = "superfish_main_menu_barrio";
$block = Block::load($id);
if (!$block) {
  $block = Block::create([
    "id"     => $id,
    "theme"  => $theme,
    "plugin" => "superfish:main",   // uses the “Main navigation” menu
    "region" => "primary_menu",
    "weight" => -50,
    "status" => 1,
  ]);
}
$settings = $block->get("settings") ?? [];
$settings["label_display"] = FALSE;
$settings["sf-depth"] = 2;            // one dropdown level
$settings["sf-auto-arrows"] = 0;      // disable arrows (you asked for this)
$settings["sf-shadow"] = 0;
$settings["sf-delay"] = 300;
$settings["sf-speed"] = "fast";
$settings["sf-touch-friendly"] = 1;
$block->set("settings", $settings);
$block->setRegion("primary_menu");
$block->set("status", 1);
$block->save();
echo "Placed Superfish block ($id) in region=primary_menu for theme=$theme\n";
'

echo "== Disable core Main navigation block on Barrio (if present) =="
php -d detect_unicode=0 -r '
use Drupal\block\Entity\Block;
$b = Block::load("main_navigation_block");
if ($b && $b->get("theme")==="bootstrap_barrio") { $b->set("status",0)->save(); echo "Disabled core main_navigation_block on Barrio.\n"; }
'

echo "== Rebuild caches =="
$DR cr

cat <<'NOTE'

✅ Done:
- Theme: bootstrap_barrio (default)
- Full-width (container-fluid) enabled
- Superfish main menu placed in header (arrows off)
- driven_tweaks CSS still active

If you need to roll back to Olivero:
  ./vendor/bin/drush --root=web cset system.theme default olivero -y
  ./vendor/bin/drush --root=web cr

If the menu spacing/arrow indicator needs more nudging under Barrio,
we can add a tiny CSS snippet to driven_tweaks to target .block-superfish in Barrio.

NOTE
