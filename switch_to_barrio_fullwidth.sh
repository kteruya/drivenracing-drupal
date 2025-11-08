#!/usr/bin/env bash
set -euo pipefail

ROOT_OPT="--root=web"
DR="./vendor/bin/drush $ROOT_OPT"

echo "== Install/enable Bootstrap Barrio =="
composer require drupal/bootstrap_barrio:^6 -W
$DR en bootstrap_barrio -y

echo "== Set front-end theme to Bootstrap Barrio =="
$DR cset system.theme default bootstrap_barrio -y

echo "== Make Barrio full-width (container-fluid everywhere) =="
# These keys exist in Barrio’s theme settings. If any prompt as new, accept.
$DR cset bootstrap_barrio.settings fluid_container 1 -y || true
$DR cset bootstrap_barrio.settings navbar_container 0 -y || true
$DR cset bootstrap_barrio.settings breadcrumb_container 0 -y || true
$DR cset bootstrap_barrio.settings content_container 0 -y || true
$DR cset bootstrap_barrio.settings footer_container 0 -y || true

echo "== Ensure driven_tweaks CSS still loads on every page =="
# (Your module is already enabled; we just re-save to be safe.)
$DR pm:list --type=module --status=enabled | grep -qi '^.*driven_tweaks' || $DR en driven_tweaks -y

echo "== Place Superfish Main menu block in Barrio header =="
# Barrio uses theme machine name 'bootstrap_barrio' and region 'primary_menu' (common).
# We create/overwrite a block instance bound to the Barrio theme.
php -d detect_unicode=0 -r '
use Drupal\block\Entity\Block;
$theme = "bootstrap_barrio";
$id = "superfish_main_menu_barrio";
$block = Block::load($id);
if (!$block) {
  $block = Block::create([
    "id"     => $id,
    "theme"  => $theme,
    "plugin" => "superfish:main",   // uses Main navigation menu
    "region" => "primary_menu",
    "weight" => -50,
    "status" => 1,
  ]);
}
$settings = $block->get("settings") ?? [];
$settings["label_display"] = FALSE;
$settings["sf-depth"] = 2;            // one dropdown level
$settings["sf-auto-arrows"] = 0;      // you asked to disable arrows
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

echo "== (Optional) Disable any Core Main Nav block in Barrio =="
php -d detect_unicode=0 -r '
use Drupal\block\Entity\Block;
$b = Block::load("main_navigation_block");
if ($b && $b->get("theme")==="bootstrap_barrio") { $b->set("status",0)->save(); echo "Disabled core main_navigation_block on Barrio.\n"; }
'

echo "== Rebuild caches =="
$DR cr

cat <<'NOTE'

All set!

- Front-end theme: Bootstrap Barrio
- Full-width containers enabled (container-fluid)
- Superfish main menu placed in header (no arrows)
- Your driven_tweaks CSS continues to apply

If you need to **rollback to Olivero**:
  ./vendor/bin/drush --root=web cset system.theme default olivero -y
  ./vendor/bin/drush --root=web cr

If the Superfish block isn’t visible, check:
  Structure → Block layout → Theme: Bootstrap Barrio → Region “Primary menu”
  (Block id: superfish_main_menu_barrio)

NOTE
