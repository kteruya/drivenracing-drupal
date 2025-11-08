#!/usr/bin/env bash
set -euo pipefail

########## SETTINGS ##########
MODPATH="web/modules/custom/drd_variation_fitment_panel"
FIELD="field_fits_motorcycles"
TARGET_TYPE="driven_motorcycle"
################################

echo "== Step 0: Sanity (must run from Drupal project root) =="
command -v drush >/dev/null || { echo "Drush not found in PATH"; exit 1; }

echo "== Step 1: Move aside any old/broken custom modules (optional cleanup) =="
for d in \
  "web/modules/custom/drd_fitment_widget" \
  "web/modules/custom/drd_fitment_fix"
do
  if [ -d "$d" ]; then
    mv "$d" "${d}.off.$(date +%s)"
    echo "  - moved $d aside"
  fi
done

echo "== Step 2: Remove stale/missing modules from core.extension (if present) =="
# (Common offender from earlier attempts)
drush php:ev "
  \$cfg=\Drupal::service('config.factory')->getEditable('core.extension');
  \$mods=\$cfg->get('module') ?: [];
  foreach (['drd_fitment_widget','drd_fitment_fix','driven_product_fit'] as \$n) {
    if (isset(\$mods[\$n])) { unset(\$mods[\$n]); echo \"  - removed \$n\\n\"; }
  }
  \$cfg->set('module', \$mods)->save();
"

echo "== Step 3: Ensure FieldStorageConfig exists and matches expectations =="
drush php:ev <<'PHP'
use Drupal\field\Entity\FieldStorageConfig;

$FIELD = 'field_fits_motorcycles';
$TARGET_TYPE = 'driven_motorcycle';

$fs = FieldStorageConfig::load("commerce_product_variation.$FIELD");
if (!$fs) {
  $fs = FieldStorageConfig::create([
    'entity_type' => 'commerce_product_variation',
    'field_name'  => $FIELD,
    'type'        => 'entity_reference',
    'cardinality' => -1,
    'settings'    => ['target_type' => $TARGET_TYPE],
  ]);
  $fs->save();
  echo "  - created FieldStorageConfig\n";
} else {
  $s = $fs->get('settings') ?: [];
  $s['target_type'] = $TARGET_TYPE;
  $fs->set('type', 'entity_reference');
  $fs->set('cardinality', -1);
  $fs->set('settings', $s);
  $fs->save();
  echo "  - updated existing FieldStorageConfig\n";
}

try {
  \Drupal::entityDefinitionUpdateManager()->updateFieldStorageDefinition($fs);
  echo "  - updated FieldStorage DB schema (if needed)\n";
} catch (\Throwable $e) {
  echo "  ! warning updating DB schema: ".$e->getMessage()."\n";
}
PHP

echo "== Step 4: Ensure FieldConfig exists on all variation bundles =="
drush php:ev <<'PHP'
use Drupal\field\Entity\FieldConfig;

$FIELD = 'field_fits_motorcycles';
$bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('commerce_product_variation'));
foreach ($bundles as $b) {
  $id = "commerce_product_variation.$b.$FIELD";
  $fc = FieldConfig::load($id);
  if (!$fc) {
    $fc = FieldConfig::create([
      'entity_type' => 'commerce_product_variation',
      'bundle'      => $b,
      'field_name'  => $FIELD,
      'label'       => 'Fits these motorcycles',
      'required'    => FALSE,
      'settings'    => [],
    ]);
    $fc->save();
    echo "  - created FieldConfig on bundle: $b\n";
  } else {
    echo "  - FieldConfig already present on bundle: $b\n";
  }
}
PHP

echo "== Step 5: Remove any existing widget/component from all variation form displays =="
drush php:ev <<'PHP'
$FIELD = 'field_fits_motorcycles';
$storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
$bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('commerce_product_variation'));
foreach ($bundles as $b) {
  $id = "commerce_product_variation.$b.default";
  if ($d = $storage->load($id)) {
    if ($d->getComponent($FIELD)) {
      $d->removeComponent($FIELD);
      $d->save();
      echo "  - removed widget $FIELD from $id\n";
    } else {
      echo "  - no widget for $FIELD on $id (already removed)\n";
    }
  } else {
    echo "  - form display $id not found\n";
  }
}
PHP

echo "== Step 6: Recreate the working panel module files =="
mkdir -p "$MODPATH"

cat > "$MODPATH/drd_variation_fitment_panel.info.yml" <<'YML'
name: 'DRD Variation Fitment Panel'
type: module
description: 'Adds a clean fitment panel to Commerce Product Variation forms and hides the raw field widget.'
package: Custom
core_version_requirement: ^10 || ^11
dependencies:
  - drupal:commerce_product
YML

cat > "$MODPATH/drd_variation_fitment_panel.module" <<'PHP'
<?php

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;

if (!defined('DRD_FITMENT_FIELD')) {
  define('DRD_FITMENT_FIELD', 'field_fits_motorcycles');
}

/**
 * Hide the raw widget anywhere it appears and attach our clean panel
 * whenever the form edits a commerce_product_variation entity.
 */
function drd_variation_fitment_panel_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
  // Always try to hide the original field if it shows up anywhere.
  _drd_vf_hide_field_recursive($form, DRD_FITMENT_FIELD);

  // Attach panel only when this is a variation entity form (add/edit).
  $form_object = $form_state->getFormObject();
  if (!$form_object || !method_exists($form_object, 'getEntity')) {
    return;
  }
  $entity = $form_object->getEntity();
  if (!$entity || $entity->getEntityTypeId() !== 'commerce_product_variation') {
    return;
  }

  _drd_vf_attach_clean_panel($form, $form_state);
}

/**
 * Recursively hide a field widget anywhere in the form tree.
 */
function _drd_vf_hide_field_recursive(array &$element, string $field_name): void {
  foreach ($element as $key => &$child) {
    if (!is_array($child)) {
      continue;
    }
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
 * Build/attach the clean "Fits Motorcycles" panel.
 */
function _drd_vf_attach_clean_panel(array &$form, FormStateInterface $form_state): void {
  $form_object = $form_state->getFormObject();
  /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
  $variation = $form_object->getEntity();

  if (!$variation->hasField(DRD_FITMENT_FIELD)) {
    return;
  }

  $items = [];
  $options = [];
  if (!$variation->get(DRD_FITMENT_FIELD)->isEmpty()) {
    foreach ($variation->get(DRD_FITMENT_FIELD)->referencedEntities() as $e) {
      $items[] = $e->hasLinkTemplate('canonical')
        ? $e->toLink($e->label())->toRenderable()
        : ['#markup' => $e->label()];
      $options[$e->id()] = $e->label();
    }
  }

  $list = $items
    ? ['#theme' => 'item_list', '#items' => $items]
    : ['#markup' => t('No motorcycles are assigned yet.')];

  $form['drd_fitment'] = [
    '#type'   => 'details',
    '#title'  => t('Fits Motorcycles'),
    '#open'   => TRUE,
    '#weight' => 90,
    '#group'  => isset($form['advanced']) ? 'advanced' : NULL,
  ];

  $form['drd_fitment']['current'] = [
    '#type' => 'container',
    'list'  => $list,
  ];

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

  $form['#entity_builders'][] = 'drd_variation_fitment_panel_entity_builder';
}

/**
 * Persist add/remove changes on save.
 */
function drd_variation_fitment_panel_entity_builder($entity_type, EntityInterface $entity, array &$form, FormStateInterface $form_state): void {
  if ($entity_type !== 'commerce_product_variation' || !$entity->hasField(DRD_FITMENT_FIELD)) {
    return;
  }

  $current = [];
  if (!$entity->get(DRD_FITMENT_FIELD)->isEmpty()) {
    foreach ($entity->get(DRD_FITMENT_FIELD)->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $current[] = (int) $item['target_id'];
      }
    }
  }

  $vf = (array) $form_state->getValue('drd_fitment', []);

  // Removals.
  $remove = [];
  if (!empty($vf['remove_ids']) && is_array($vf['remove_ids'])) {
    $remove = array_map('intval', array_keys(array_filter($vf['remove_ids'])));
  }
  $new = array_values(array_diff($current, $remove));

  // Addition (single).
  $add_tid = NULL;
  if (!empty($vf['add_motorcycle'])) {
    if (is_array($vf['add_motorcycle']) && isset($vf['add_motorcycle']['target_id'])) {
      $add_tid = (int) $vf['add_motorcycle']['target_id'];
    } elseif (is_numeric($vf['add_motorcycle'])) {
      $add_tid = (int) $vf['add_motorcycle'];
    }
  }
  if ($add_tid && !in_array($add_tid, $new, TRUE)) {
    $new[] = $add_tid;
  }

  $entity->set(DRD_FITMENT_FIELD, array_map(fn($id) => ['target_id' => $id], $new));
}
PHP

echo "== Step 7: Enable our module & rebuild caches =="
drush en drd_variation_fitment_panel -y
drush cr

echo "== Done. Open a product variation add/edit form to verify =="
echo "   You should see: list of assigned motorcycles, removal checkboxes, and an autocomplete to add one."
