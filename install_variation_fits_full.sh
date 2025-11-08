#!/usr/bin/env bash
# install_variation_fits_full.sh — robust docroot + drush detection
set -euo pipefail

# --- Find Drupal docroot ---
if [ -d "web/core" ]; then
  DRUPAL_ROOT="web"
elif [ -d "core" ]; then
  DRUPAL_ROOT="."
else
  echo "❌ Could not find Drupal docroot (no web/core or core). Run from your project root."
  exit 1
fi

# --- Pick a working Drush (prefer project-local) ---
if [ -x "./vendor/bin/drush" ]; then
  DRUSH_BASE="./vendor/bin/drush"
elif [ -x "vendor/bin/drush" ]; then
  DRUSH_BASE="vendor/bin/drush"
else
  DRUSH_BASE="drush"
fi
DRUSH="$DRUSH_BASE --root=${DRUPAL_ROOT}"

# --- Sanity check: can we bootstrap the site? ---
echo "→ Checking Drush bootstrap…"
set +e
$DRUSH status --fields=bootstrap >/dev/null 2>&1
BOOT_OK=$?
set -e
if [ "$BOOT_OK" -ne 0 ]; then
  echo "❌ Drush could not bootstrap your site with: $DRUSH_BASE --root=${DRUPAL_ROOT}"
  echo "   Try installing/using project-local Drush: composer require drush/drush"
  echo "   Or run this script from your project root with: bash $(basename "$0")"
  exit 1
fi

echo "✔ Drush OK: $DRUSH_BASE (root=$DRUPAL_ROOT)"

echo "→ Ensuring required modules…"
$DRUSH pm:enable commerce_product -y 2>/dev/null || true
$DRUSH pm:enable driven_motorcycles -y 2>/dev/null || true

echo "→ Creating variation-only fields (idempotent)…"
$DRUSH php:eval '
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

$etype = "commerce_product_variation";
$bundles = array_keys(\Drupal::service("entity_type.bundle.info")->getBundleInfo($etype));

if (!FieldStorageConfig::load("$etype.field_fits_motorcycles")) {
  FieldStorageConfig::create([
    "field_name"  => "field_fits_motorcycles",
    "entity_type" => $etype,
    "type"        => "entity_reference",
    "cardinality" => -1,
    "settings"    => ["target_type" => "driven_motorcycle"],
    "translatable"=> FALSE,
  ])->save();
  echo "Created storage: $etype.field_fits_motorcycles\n";
}

if (!FieldStorageConfig::load("$etype.field_fits_all_motorcycles")) {
  FieldStorageConfig::create([
    "field_name"  => "field_fits_all_motorcycles",
    "entity_type" => $etype,
    "type"        => "boolean",
    "cardinality" => 1,
    "settings"    => ["on_label" => "Yes", "off_label" => "No"],
    "translatable"=> FALSE,
  ])->save();
  echo "Created storage: $etype.field_fits_all_motorcycles\n";
}

foreach ($bundles as $bundle) {
  if (!FieldConfig::load("$etype.$bundle.field_fits_motorcycles")) {
    FieldConfig::create([
      "field_name"  => "field_fits_motorcycles",
      "entity_type" => $etype,
      "bundle"      => $bundle,
      "label"       => "Fits these motorcycles",
      "settings"    => [
        "handler" => "default:driven_motorcycle",
        "handler_settings" => ["auto_create" => FALSE],
      ],
    ])->save();
    echo "Attached field_fits_motorcycles to $etype.$bundle\n";
  }

  if (!FieldConfig::load("$etype.$bundle.field_fits_all_motorcycles")) {
    FieldConfig::create([
      "field_name"  => "field_fits_all_motorcycles",
      "entity_type" => $etype,
      "bundle"      => $bundle,
      "label"       => "Fits all motorcycles (NBS)",
    ])->save();
    echo "Attached field_fits_all_motorcycles to $etype.$bundle\n";
  }

  $fd = EntityFormDisplay::load("$etype.$bundle.default")
     ?: EntityFormDisplay::create(["targetEntityType"=>$etype,"bundle"=>$bundle,"mode"=>"default","status"=>TRUE]);
  $fd->setComponent("field_fits_all_motorcycles", ["type"=>"boolean_checkbox","weight"=>97]);
  $fd->setComponent("field_fits_motorcycles", [
    "type"=>"entity_reference_autocomplete_tags","weight"=>98,
    "settings"=>["match_operator"=>"CONTAINS","size"=>60,"placeholder"=>""],
  ]);
  $fd->save();

  $vd = EntityViewDisplay::load("$etype.$bundle.default")
     ?: EntityViewDisplay::create(["targetEntityType"=>$etype,"bundle"=>$bundle,"mode"=>"default","status"=>TRUE]);
  $vd->setComponent("field_fits_all_motorcycles", ["type"=>"boolean","label"=>"above","weight"=>97]);
  $vd->setComponent("field_fits_motorcycles", ["type"=>"entity_reference_label","label"=>"above","weight"=>98,"settings"=>["link"=>TRUE]]);
  $vd->save();
}
echo "Wired variation form/view displays.\n";
'

echo "→ Ensure product edit uses variation DEFAULT form mode…"
$DRUSH php:eval '
use Drupal\Core\Entity\Entity\EntityFormDisplay;
$etype="commerce_product";
$bundles = array_keys(\Drupal::service("entity_type.bundle.info")->getBundleInfo($etype));
foreach ($bundles as $b) {
  $disp = EntityFormDisplay::load("$etype.$b.default");
  if (!$disp) continue;
  $c = $disp->getComponent("variations") ?: [];
  $c["type"] = "inline_entity_form_complex";
  $c["settings"]["form_mode"] = "default";
  $disp->setComponent("variations", $c)->save();
  echo "IEF form_mode=default for product bundle $b\n";
}
'

echo "→ Apply entity definition updates if needed…"
$DRUSH php:eval '
$u = \Drupal::service("entity.definition_update_manager");
if ($u->needsUpdates()) { $u->applyUpdates(); echo "Applied entity definition updates.\n"; }
else { echo "No entity definition updates needed.\n"; }
'

echo "→ Rebuild caches…"
$DRUSH cr -y

echo "→ Writing importer to /tmp/import_variation_fits_from_d7_mapping.php"
cat >/tmp/import_variation_fits_from_d7_mapping.php <<'PHP'
<?php
/**
 * D7 → D11 variation fits import using D7 mapping table.
 *
 * Usage (dry-run):
 *   D7_DB=driven_d7uc_1 DRY=1 LIMIT=1000 drush --root=web scr /tmp/import_variation_fits_from_d7_mapping.php
 * Real:
 *   D7_DB=driven_d7uc_1 drush --root=web scr /tmp/import_variation_fits_from_d7_mapping.php
 *
 * Optional env:
 *   SKU_BACKEND=ubercart|commerce|auto   (default auto)
 *   MOTO_ID_MODE=legacy_table|same       (default legacy_table)
 *   LEGACY_MOTO_TABLE=legacy_driven_motorcycles
 *   D7_FIELD_TABLE=field_data_field_fits_these_motorcycles
 *   D7_FIELD_COL_TID=field_fits_these_motorcycles_target_id
 *   NBS_TERM_NAME="NBS"                  (resolve tids by name in D7)
 *   NBS_TIDS="0,9999"                    (explicit tids treated as NBS)
 *   LIMIT=10000 OFFSET=0 DRY=1
 */
use Drupal\Core\Database\Database;

$d7db = getenv('D7_DB') ?: '';
if (!$d7db) { echo "ERROR: set D7_DB=\n"; return; }

$tbl = getenv('D7_FIELD_TABLE') ?: 'field_data_field_fits_these_motorcycles';
$col_tid = getenv('D7_FIELD_COL_TID') ?: 'field_fits_these_motorcycles_target_id';
$legacy_table = getenv('LEGACY_MOTO_TABLE') ?: 'legacy_driven_motorcycles';
$limit  = getenv('LIMIT')  !== false ? (int) getenv('LIMIT')  : null;
$offset = getenv('OFFSET') !== false ? (int) getenv('OFFSET') : 0;
$dry = (bool) (getenv('DRY') ?: 0);

$sku_backend = strtolower(getenv('SKU_BACKEND') ?: 'auto');           // auto|commerce|ubercart
$moto_mode   = strtolower(getenv('MOTO_ID_MODE') ?: 'legacy_table');   // legacy_table|same

$nbs_term_name = getenv('NBS_TERM_NAME') ?: '';
$nbs_tids_csv  = getenv('NBS_TIDS') ?: '';
$nbs_tids = array_filter(array_map('intval', array_map('trim', explode(',', $nbs_tids_csv ?: ''))));

$db = Database::getConnection(); // local Drupal
$etm = \Drupal::entityTypeManager();

$varStore  = $etm->getStorage('commerce_product_variation');
$motoStore = $etm->getStorage('driven_motorcycle');

/** Ensure variation fields exist */
$fs1 = $etm->getStorage('field_storage_config')->load('commerce_product_variation.field_fits_motorcycles');
$fs2 = $etm->getStorage('field_storage_config')->load('commerce_product_variation.field_fits_all_motorcycles');
if (!$fs1 || !$fs2) { echo "ERROR: variation fields missing. Run install script first.\n"; return; }

/** Resolve NBS tids by term name (if provided) */
if ($nbs_term_name) {
  try {
    $rows = $db->query("
      SELECT t.tid FROM `{$d7db}`.`taxonomy_term_data` t WHERE t.name = :n
    ", [':n' => $nbs_term_name])->fetchCol();
    if ($rows) {
      $nbs_tids = array_values(array_unique(array_merge($nbs_tids, array_map('intval', $rows))));
      echo "NBS term '{$nbs_term_name}' → tids: ".implode(',', $nbs_tids)."\n";
    } else {
      echo "WARN: NBS term '{$nbs_term_name}' not found in D7.\n";
    }
  } catch (\Throwable $e) {
    echo "WARN: could not resolve NBS term: ".$e->getMessage()."\n";
  }
}

/** Detect D7 backend for SKU lookup */
if ($sku_backend === 'auto') {
  $has_commerce = (bool) $db->query("
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = :db AND table_name = 'commerce_product'
  ", [':db' => $d7db])->fetchField();
  $has_uc = (bool) $db->query("
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = :db AND table_name = 'uc_products'
  ", [':db' => $d7db])->fetchField();
  $sku_backend = $has_commerce ? 'commerce' : ($has_uc ? 'ubercart' : 'unknown');
}
if ($sku_backend === 'unknown') { echo "ERROR: Neither commerce_product nor uc_products exists in $d7db.\n"; return; }
echo "Using D7 SKU backend: $sku_backend\n";

/** Build SELECT on D7 mapping table */
$sql = "SELECT f.entity_type, f.entity_id, f.delta, f.language, f.`$col_tid` AS target_id, ";
if ($sku_backend === 'commerce') {
  $sql .= "p.sku AS sku
           FROM `{$d7db}`.`{$tbl}` f
           LEFT JOIN `{$d7db}`.`commerce_product` p ON p.product_id = f.entity_id
           WHERE f.entity_type = :etype ";
  $args = [':etype' => 'commerce_product'];
} else {
  $sql .= "up.model AS sku
           FROM `{$d7db}`.`{$tbl}` f
           LEFT JOIN `{$d7db}`.`uc_products` up ON up.nid = f.entity_id
           WHERE 1=1 ";
  $args = [];
}
$sql .= "ORDER BY f.entity_id, f.delta ";
if ($limit !== null) { $sql .= "LIMIT ".((int)$limit)." OFFSET ".((int)$offset); }

$q = $db->query($sql, $args);

/** Helper: resolve D7 target_id to D11 driven_motorcycle id */
$resolveMoto = function(int $legacy_id) use ($db, $motoStore, $moto_mode, $legacy_table) : int {
  if ($legacy_id <= 0) return 0;
  if ($moto_mode === 'same') {
    $e = $motoStore->load($legacy_id);
    return $e ? $legacy_id : 0;
  }
  $legacy = $db->query("
    SELECT year, make, model FROM `{$legacy_table}` WHERE id = :id
  ", [':id' => $legacy_id])->fetchAssoc();
  if (!$legacy) return 0;
  $ids = $motoStore->getQuery()->accessCheck(FALSE)
    ->condition('year', (int)$legacy['year'])
    ->condition('make', $legacy['make'])
    ->condition('model', $legacy['model'])
    ->range(0,1)->execute();
  return $ids ? (int) reset($ids) : 0;
};

$processed=0; $linked=0; $updated=0; $skipped=0;
$missingSku=0; $missingVar=0; $missingMoto=0; $nbsSet=0; $emptyTarget=0;

while ($row = $q->fetchAssoc()) {
  $processed++;
  $sku = trim((string) ($row['sku'] ?? ''));
  $legacy_target = $row['target_id'];

  if ($legacy_target === null || $legacy_target === '') { $emptyTarget++; continue; }
  $legacy_target = (int) $legacy_target;

  if ($sku === '') { $missingSku++; continue; }

  // Resolve D11 variation by SKU
  $vids = \Drupal::entityQuery('commerce_product_variation')->accessCheck(FALSE)
    ->condition('sku', $sku)->range(0,1)->execute();
  if (!$vids) { $missingVar++; continue; }
  $variation = $varStore->load((int) reset($vids));

  // NBS?
  if ($legacy_target === 0 || in_array($legacy_target, $nbs_tids, true)) {
    if (!$dry) {
      $variation->set('field_fits_all_motorcycles', 1);
      $variation->set('field_fits_motorcycles', []); // clear specifics if NBS
      $variation->save();
    }
    $nbsSet++; $updated++;
    continue;
  }

  // Specific motorcycle
  $mid = $resolveMoto($legacy_target);
  if (!$mid) { $missingMoto++; continue; }

  $cur = array_column($variation->get('field_fits_motorcycles')->getValue(), 'target_id');
  if (!in_array($mid, $cur, true)) {
    $cur[] = $mid;
    if (!$dry) {
      // Add specific fit; unset NBS
      $variation->set('field_fits_all_motorcycles', 0);
      $variation->set('field_fits_motorcycles', array_map(fn($id)=>['target_id'=>(int)$id], $cur));
      $variation->save();
    }
    $linked++;
  } else {
    $skipped++;
  }
}

echo "=== D7 → D11 Variation Fits Import (mapping) ===\n";
echo "Processed: $processed\n";
echo "Linked(new refs): $linked  Updated(NBS): $updated  Skipped(dups): $skipped\n";
echo "Missing SKU: $missingSku  Missing variation: $missingVar  Missing moto: $missingMoto  Empty target: $emptyTarget  NBS set: $nbsSet\n";
echo $dry ? "NOTE: Dry-run (no writes)\n" : "";
PHP

echo
echo "✅ Setup complete."
echo "Importer written to: /tmp/import_variation_fits_from_d7_mapping.php"
echo
echo "Next steps:"
echo "  D7_DB=driven_d7uc_1 DRY=1 LIMIT=1000 \\"
echo "    $DRUSH scr /tmp/import_variation_fits_from_d7_mapping.php"
echo
echo "  D7_DB=driven_d7uc_1 \\"
echo "    $DRUSH scr /tmp/import_variation_fits_from_d7_mapping.php"
echo
