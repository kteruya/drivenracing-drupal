#!/usr/bin/env bash
set -euo pipefail

ROOT="web"
DRUSH="vendor/bin/drush -r $ROOT"
MODPATH="$ROOT/modules/custom/drd_variation_fitment_panel"
FIELD="field_fits_motorcycles"
TARGET="driven_motorcycle"

echo "== 0) Sanity check =="
[ -d "$ROOT" ] || { echo "Run from project root (must have ./web)."; exit 1; }
$DRUSH status >/dev/null

echo "== 1) (Re)write module files =="
mkdir -p "$MODPATH"

cat > "$MODPATH/drd_variation_fitment_panel.info.yml" <<'YML'
name: 'DRD Variation Fitment Panel'
type: module
description: 'Adds a clean panel on variation forms to view/add/remove fitment and hides the core widget.'
package: Custom
core_version_requirement: ^10 || ^11
dependencies:
  - drupal:commerce_product
YML

cat > "$MODPATH/drd_variation_fitment_panel.module" <<'PHP'
<?php

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

if (!defined('DRD_FITMENT_FIELD')) {
  define('DRD_FITMENT_FIELD', 'field_fits_motorcycles');
}

/**
 * Hide the original widget defensively anywhere it appears.
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

/**
 * Recursively hide a field widget anywhere in the form tree.
 */
function _drd_vf_hide_field_recursive(array &$element, string $field_name): void {
  foreach ($element as $key => &$child) {
    if (!is_array($child)) { continue; }
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
 * Build the clean panel.
 */
function _drd_vf_attach_clean_panel(array &$form, FormStateInterface $form_state): void {
  $form_object = $form_state->getFormObject();
  if (!$form_object || !method_exists($form_object, 'getEntity')) {
    return;
  }
  /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
  $variation = $form_object->getEntity();

  $form['drd_fitment'] = [
    '#type'   => 'details',
    '#title'  => t('Fits Motorcycles'),
    '#open'   => TRUE,
    '#weight' => 90,
  ];

  if (!$variation) {
    $form['drd_fitment']['note'] = ['#markup' => t('No variation loaded.')];
    return;
  }
  if (!$variation->hasField(DRD_FITMENT_FIELD)) {
    $form['drd_fitment']['note'] = ['#markup' => t('This variation does not have the @field field.', ['@field' => DRD_FITMENT_FIELD])];
    return;
  }

  // Debug breadcrumb so we can see that the panel attached at runtime.
  $vid = $variation->id() ?: '(new)';
  $form['drd_fitment']['_debug'] = [
    '#markup' => '<em>Panel attached for variation ID: ' . htmlspecialchars((string) $vid) . '</em>',
  ];

  // Current list (from the VARIATION field).
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

  $list = $items
    ? ['#theme' => 'item_list', '#items' => $items]
    : ['#markup' => t('No motorcycles are assigned yet.')];

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

  // Persist changes on Save.
  $form['#entity_builders'][] = 'drd_variation_fitment_panel_entity_builder';
}

/**
 * Persist add/remove to the variation entity on submit.
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

  // Addition (autocomplete may return scalar or array['target_id']).
  $add_tid = NULL;
  if (isset($vf['add_motorcycle'])) {
    if (is_array($vf['add_motorcycle']) && isset($vf['add_motorcycle']['target_id'])) {
      $add_tid = (int) $vf['add_motorcycle']['target_id'];
    } elseif (is_scalar($vf['add_motorcycle']) && strlen((string) $vf['add_motorcycle'])) {
      $add_tid = (int) $vf['add_motorcycle'];
    }
  }
  if ($add_tid && !in_array($add_tid, $new, TRUE)) {
    $new[] = $add_tid;
    \Drupal::messenger()->addStatus(t('Motorcycle added.'));
  }
  if ($remove) {
    \Drupal::messenger()->addStatus(t('Removed @count item(s).', ['@count' => count($remove)]));
  }

  $entity->set(DRD_FITMENT_FIELD, array_map(fn($id) => ['target_id' => $id], $new));
}
PHP

echo "== 2) Enable module (ok if already enabled) =="
$DRUSH en drd_variation_fitment_panel -y >/dev/null || true

echo "== 3) Ensure FieldStorage exists and DB schema is updated =="
$DRUSH php:ev "
use Drupal\field\Entity\FieldStorageConfig;
\$fs = FieldStorageConfig::load('commerce_product_variation.$FIELD');
if (!\$fs) {
  \$fs = FieldStorageConfig::create([
    'entity_type' => 'commerce_product_variation',
    'field_name'  => '$FIELD',
    'type'        => 'entity_reference',
    'cardinality' => -1,
    'settings'    => ['target_type' => '$TARGET'],
  ]);
  \$fs->save();
  echo \"Created FieldStorageConfig\n\";
} else {
  \$s = \$fs->get('settings') ?: [];
  \$s['target_type'] = '$TARGET';
  \$fs->set('type', 'entity_reference');
  \$fs->set('cardinality', -1);
  \$fs->set('settings', \$s);
  \$fs->save();
  echo \"Saved existing FieldStorageConfig\n\";
}
\Drupal::entityDefinitionUpdateManager()->updateFieldStorageDefinition(\$fs);
echo \"Updated FieldStorage (DB)\n\";
"

echo "== 4) Ensure FieldConfig exists on every variation bundle (attach field) =="
$DRUSH php:ev "
use Drupal\field\Entity\FieldConfig;
\$bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('commerce_product_variation'));
foreach (\$bundles as \$b) {
  \$id = \"commerce_product_variation.\$b.$FIELD\";
  \$fc = FieldConfig::load(\$id);
  if (!\$fc) {
    \$fc = FieldConfig::create([
      'field_name'  => '$FIELD',
      'entity_type' => 'commerce_product_variation',
      'bundle'      => \$b,
      'label'       => 'Fits these motorcycles',
      'required'    => FALSE,
      'settings'    => [],
    ]);
    \$fc->save();
    echo \"Created FieldConfig on bundle: \$b\n\";
  } else {
    echo \"FieldConfig already attached on bundle: \$b\n\";
  }
}
"

echo "== 5) Remove any core widget component so only our panel renders =="
$DRUSH php:ev "
\$storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
\$bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('commerce_product_variation'));
foreach (\$bundles as \$b) {
  \$id = \"commerce_product_variation.\$b.default\";
  if (\$d = \$storage->load(\$id)) {
    if (\$d->getComponent('$FIELD')) {
      \$d->removeComponent('$FIELD');
      \$d->save();
      echo \"Removed component $FIELD from \$id\n\";
    } else {
      echo \"No component for $FIELD on \$id (already removed)\n\";
    }
  }
}
"

echo "== 6) Rebuild caches =="
$DRUSH cr -y >/dev/null

echo "== 7) Quick diagnostics =="
echo "- Module enabled? ($($DRUSH pml | grep -q drd_variation_fitment_panel && echo yes || echo no))"
echo "- Variation bundles have field attached:"
$DRUSH php:ev "\$efm=\Drupal::service('entity_field.manager'); foreach(\Drupal::service('entity_type.bundle.info')->getBundleInfo('commerce_product_variation') as \$b=>\$_){ \$defs=\$efm->getFieldDefinitions('commerce_product_variation',\$b); echo ((isset(\$defs['$FIELD']))?'OK':'MISS').\"  \" . \$b . PHP_EOL; }"
echo "Done. Open any Product Variation edit form; you should see:"
echo " • A small italic debug line: “Panel attached for variation ID: …”"
echo " • The list of assigned motorcycles (or ‘No motorcycles are assigned yet.’)"
echo " • Remove checkboxes (if any) and an Add motorcycle autocomplete."
