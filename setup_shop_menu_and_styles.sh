#!/usr/bin/env bash
set -euo pipefail

DRUSH="./vendor/bin/drush"
WEB_ROOT="web"
MODULE_DIR="$WEB_ROOT/modules/custom/driven_tweaks"

echo "==> Ensuring custom module directory..."
mkdir -p "$MODULE_DIR/css"

echo "==> Writing driven_tweaks.info.yml"
cat > "$MODULE_DIR/driven_tweaks.info.yml" <<'YAML'
name: Driven Tweaks
type: module
description: Small sitewide tweaks (menu styles & fixes).
package: Custom
core_version_requirement: ^11
YAML

echo "==> Writing driven_tweaks.libraries.yml"
cat > "$MODULE_DIR/driven_tweaks.libraries.yml" <<'YAML'
menu_styles:
  css:
    theme:
      css/menu.css: {}
YAML

echo "==> Writing CSS styles (white bg, gray Oswald, uppercase)"
cat > "$MODULE_DIR/css/menu.css" <<'CSS'
/* Base font + casing */
.menu--main a,
.sf-menu a {
  font-family: "Oswald", sans-serif;
  text-transform: uppercase;
  font-size: 16px;
  padding: 5px 2px;
  color: #666;
  text-decoration: none;
}

/* Top bar background */
.region-primary-menu,
.sf-menu,
.menu--main {
  background: #fff;
}

/* Hover/active state */
.menu--main a:hover,
.sf-menu a:hover,
.menu--main .is-active > a,
.sf-menu .sfHover > a {
  color: #000;
}

/* Superfish dropdown panel */
.sf-menu ul {
  background: #fff;
  border: 1px solid #ddd;
}

/* Dropdown links */
.sf-menu ul li a {
  color: #666;
  padding: 6px 10px;
}

/* Larger top-level touch target */
.sf-menu > li > a {
  padding: 10px 12px;
}
CSS

echo "==> Writing driven_tweaks.module (attach library sitewide)"
cat > "$MODULE_DIR/driven_tweaks.module" <<'PHP'
<?php

/**
 * Implements hook_page_attachments().
 * Attach our menu CSS everywhere.
 */
function driven_tweaks_page_attachments(array &$attachments) {
  $attachments['#attached']['library'][] = 'driven_tweaks/menu_styles';
}
PHP

echo "==> Enabling module (if not already)..."
$DRUSH --root="$WEB_ROOT" en driven_tweaks -y || true

echo "==> Disable any Views-provided 'Shop' menu-link(s) at /shop"
$DRUSH --root="$WEB_ROOT" php:eval '
$cf = \Drupal::configFactory()->getEditable("system.menu.static_menu_link_overrides");
use Drupal\Core\Menu\MenuTreeParameters;
$tree = \Drupal::service("menu.link_tree")->load("main",(new MenuTreeParameters())->setMaxDepth(1));
$changed = 0;
foreach ($tree as $el) {
  $link = $el->link;
  if (strtolower($link->getTitle()) === "shop" && $link->getProvider() === "views") {
    $pid = $link->getPluginId(); // e.g. views_view:shop.page OR views_view:views.shop.page
    $cf->set("links.$pid.enabled", FALSE);
    $changed++;
    echo "Disabled static menu-link override: links.$pid.enabled = FALSE\n";
  }
}
if ($changed) { $cf->save(); } else { echo "No views-provided Shop links found.\n"; }
'

echo "==> Ensure a single content Shop -> /catalog (top-level, expanded)"
$DRUSH --root="$WEB_ROOT" php:eval '
$store=\Drupal::entityTypeManager()->getStorage("menu_link_content");
$existing=$store->loadByProperties(["menu_name"=>"main","title"=>"Shop"]);
$shop=$existing?reset($existing):$store->create(["title"=>"Shop","menu_name"=>"main","link"=>["uri"=>"internal:/catalog"]]);
$shop->set("link",["uri"=>"internal:/catalog"]);
$shop->set("enabled",1);
$shop->set("expanded",1);
$shop->set("parent","");
$shop->set("weight",-10);
$shop->save();
echo "Kept/created: Shop -> /catalog\n";
'

echo "==> (Safety) Move any top-level taxonomy-term links under Shop"
$DRUSH --root="$WEB_ROOT" php:eval '
$em=\Drupal::entityTypeManager();
$store=$em->getStorage("menu_link_content");
$shop=$store->loadByProperties(["menu_name"=>"main","link__uri"=>"internal:/catalog"]);
$shop=$shop?reset($shop):NULL;
if($shop){
  $parent=$shop->getPluginId();
  $q=\Drupal::entityQuery("menu_link_content")->condition("menu_name","main")->condition("enabled",1);
  $q->condition($q->orConditionGroup()->condition("parent","")->notExists("parent"));
  $ids=$q->accessCheck(FALSE)->execute();
  $moved=0;
  if($ids){
    foreach($store->loadMultiple($ids) as $item){
      $uri=$item->get("link")->first()?->getValue()["uri"] ?? "";
      if (strpos($uri,"internal:/taxonomy/term/")===0){
        $item->set("parent",$parent);
        if($item->get("weight")->isEmpty()){ $item->set("weight",0); }
        $item->save(); $moved++;
      }
    }
  }
  echo "Moved $moved items.\n";
}else{
  echo "Shop link not found; skipped move.\n";
}
'

echo "==> Rebuild caches"
$DRUSH --root="$WEB_ROOT" cr

echo "==> Top-level Main Menu now:"
$DRUSH --root="$WEB_ROOT" php:eval '
use Drupal\Core\Menu\MenuTreeParameters;
$tree=\Drupal::service("menu.link_tree")->load("main",(new MenuTreeParameters())->setMaxDepth(1));
foreach($tree as $el){
  echo $el->link->getTitle()."  [".$el->link->getUrlObject()->toString()."]  (".$el->link->getProvider().")\n";
}
'

echo "==> Done."
