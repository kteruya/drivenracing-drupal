#!/usr/bin/env bash
set -euo pipefail

# --- SETTINGS ---
WEBROOT="web"
MODNAME="drd_variation_fitment_panel"
MODPATH="$WEBROOT/modules/custom/$MODNAME"
FITMENT_FIELD="field_fits_motorcycles"   # change if different
FITMENT_TARGET="driven_motorcycle"       # target entity type

# Resolve project root = directory of this script
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Use local Drush to avoid launcher preflight issues
DRUSH="./vendor/bin/drush"
if [[ ! -x "$DRUSH" ]]; then
  echo "ERROR: $DRUSH not found. Run from the Drupal project root (where composer.json is) and ensure 'composer install' was run."
  exit 1
fi

echo "== 0) Sanity =="
if [[ ! -d "$WEBROOT/core" ]]; then
  echo "ERROR: $WEBROOT/core not found. Adjust WEBROOT in this script if your web root isn't '$WEBROOT'."
  exit 1
fi

echo "== 1) Create module files =="
mkdir -p "$MODPATH"

cat > "$MODPATH/$MODNAME.info.yml" <<'YAML'
name: 'DRD Variation Fitment Panel'
type: module
description: 'Clean Fitment panel on Variation forms: list + add + remove motorcycles; hides the original widget.'
package: Custom
core_version_requirement: ^10 || ^11
dependencies:
  - drupal:commerce_product
YAML

cat > "$MODPATH/$MODNAME.module" <<'PHP'
<?php

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

if (!defined('DRD_FITMENT_FIELD')) {
  define('DRD_FITMENT_FIELD', 'field_fits_motorcycles');
}

/**
 * Hide the original widget anywhere it appears (including IEF).
 */
function drd_variation_fitment_panel_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
  _drd_vf_hide_field_recursive($form, DRD_FITMENT_FIELD);
}

/** Variation edit. */
function drd_variation_fitment_panel_form_commerce_product_variation_edit_form_alter(array &$form, FormStateInterface $form_state) {
  _drd_vf_attach_clean_panel($form, $form_state);
}

/** Variation add. */
function drd_variation_fitment_panel_form_commerce_product_variation_add_form_alter(array &$form, FormStateInterface $form_state) {
  _drd_vf_attach_clean_panel($form, $form_state);
}

function _drd_vf_hide_field_recursive(array &$element, string $field_name): void {
  foreach ($element as $key => &$child) {
    if (!is_array($child)) continue;
    if ($key === $field_name && isset($child['#type'])) {
      $child['#access'] = FALSE;
    }
    if (isset($child[$field_name]) && is_array($child[$field_name])) {
      $child[$field_name]['#access'] = FALSE;
    }
    _drd_vf_hide_field_recursive($child, $field_name);
  }
}

/**
 * Build the clean Fitment panel.
 */
function _drd_vf_attach_clean_panel(array &$form, FormStateInterface $form_state): void {
  $form_object = $form_state->getFormObject();
  if (!$form_object || !method_exists($form_object, 'getEntity')) return;

  /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
  $variation = $form_object->getEntity();
  if (!$variation || !$variation->hasField(DRD_FITMENT_FIELD)) return;

  $options = [];
  $items = [];
  if (!$variation->get(DRD_FITMENT_FIELD)->isEmpty()) {
    foreach ($variation->get(DRD_FITMENT_FIELD)->referencedEntities() as $e) {
      $options[$e->id()] = $e->label();
      $items[] = $e->hasLinkTemplate('canonical')
        ? $e->toLink($e->label())->toRenderable()
        : ['#markup' => $e->label()];
    }
  }

  $list = $items ? ['#theme' => 'item_list', '#items' => $items]
                 : ['#markup' => t('No motorcycles are assigned yet.')];

  $form['drd_fitment'] = [
    '#type'   => 'details',
    '#title'  => t('Fits Motorcycles'),
    '#open'   => TRUE,
    '#weight' => 90,
    '#group'  => isset($form['advanced']) ? 'advanced' : NULL,
    '#tree'   => TRUE, // keep values for entity_builder
  ];
  $form['drd_fitment']['current'] = ['#type' => 'container', 'list' => $list];

  if ($options) {
    $form['drd_fitment']['remove_ids'] = [
      '#type' => 'checkboxes',
      '#title' => t('Remove selected'),
      '#options' => $options,
      '#description' => t('Tick motorcycles to remove, then click Save.'),
    ];
  }

  $form['drd_fitment']['add_motorcycle'] = [
    '#type' => 'entity_autocomplete',
    '#title' => t('Add motorcycle'),
    '#target_type' => 'driven_motorcycle',
    '#description' => t('Pick one and click Save to add it.'),
    '#tags' => FALSE,
  ];

  // Persist changes on Save.
  $form['#entity_builders'][] = 'drd_variation_fitment_panel_entity_builder';
}

/**
 * Apply removals/addition on entity save.
 */
function drd_variation_fitment_panel_entity_builder($entity_type, EntityInterface $entity, array &$form, FormStateInterface $form_state): void {
  if ($entity_type !== 'commerce_product_variation' || !$entity->hasField(DRD_FITMENT_FIELD)) return;

  $current = [];
  if (!$entity->get(DRD_FITMENT_FIELD)->isEmpty()) {
    foreach ($entity->get(DRD_FITMENT_FIELD)->getValue() as $item) {
      if (!empty($item['target_id'])) $current[] = (int) $item['target_id'];
    }
  }

  $vf = (array) $form_state->getValue('drd_fitment', []);

  // Removals.
  $remove = [];
  if (!empty($vf['remove_ids']) && is_array($vf['remove_ids'])) {
    $remove = array_map('intval', array_keys(array_filter($vf['remove_ids'])));
  }
  $new = array_values(array_diff($current, $remove));

  // Addition: accept array (autocomplete), scalar ID, or "123 (Label)".
  $add_tid = NULL;
  if (array_key_exists('add_motorcycle', $vf)) {
    $raw = $vf['add_motorcycle'];
    if (is_array($raw) && isset($raw['target_id'])) {
      $add_tid = (int) $raw['target_id'];
    } elseif (is_scalar($raw)) {
      $str = (string) $raw;
      if (ctype_digit($str)) {
        $add_tid = (int) $str;
      } elseif (preg_match('/^\s*(\d+)/', $str, $m)) {
        $add_tid = (int) $m[1];
      }
    }
  }
  if ($add_tid && !in_array($add_tid, $new, TRUE)) $new[] = $add_tid;

  $entity->set(DRD_FITMENT_FIELD, array_map(fn($id) => ['target_id' => $id], $new));
}
PHP

echo "== 2) Ensure field storage exists and is correct =="
"$DRUSH" php:ev "
use Drupal\field\Entity\FieldStorageConfig;
\$fs = FieldStorageConfig::load('commerce_product_variation.$FITMENT_FIELD');
if (!\$fs) {
  \$fs = FieldStorageConfig::create([
    'entity_type' => 'commerce_product_variation',
    'field_name'  => '$FITMENT_FIELD',
    'type'        => 'entity_reference',
    'cardinality' => -1,
    'settings'    => ['target_type' => '$FITMENT_TARGET'],
  ]);
  \$fs->save();
  echo \"Created FieldStorageConfig\\n\";
} else {
  \$s = \$fs->get('settings') ?: [];
  \$s['target_type'] = '$FITMENT_TARGET';
  \$fs->set('type', 'entity_reference');
  \$fs->set('cardinality', -1);
  \$fs->set('settings', \$s);
  \$fs->save();
  echo \"Saved existing FieldStorageConfig\\n\";
}
\\Drupal::entityDefinitionUpdateManager()->updateFieldStorageDefinition(\$fs);
echo \"Updated FieldStorage (DB)\\n\";
"

echo "== 3) Remove any existing core widget from variation form displays (avoid duplicates) =="
"$DRUSH" php:ev "
\$field = '$FITMENT_FIELD';
\$storage = \\Drupal::entityTypeManager()->getStorage('entity_form_display');
\$bundles = array_keys(\\Drupal::service('entity_type.bundle.info')->getBundleInfo('commerce_product_variation'));
foreach (\$bundles as \$b) {
  \$id = \"commerce_product_variation.\$b.default\";
  if (\$d = \$storage->load(\$id)) {
    if (\$d->getComponent(\$field)) {
      \$d->removeComponent(\$field);
      \$d->save();
      echo \"Removed component \$field from \$id\\n\";
    }
  }
}
"

echo "== 4) Enable module & clear caches =="
"$DRUSH" en "$MODNAME" -y || true
"$DRUSH" cr

echo "All set. Open a product variation edit form to:"
echo " - view current motorcycles,"
echo " - remove via checkboxes,"
echo " - add via autocomplete (saves correctly)."
