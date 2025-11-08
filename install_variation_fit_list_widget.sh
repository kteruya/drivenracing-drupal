#!/usr/bin/env bash
# install_variation_fit_list_widget.sh
set -euo pipefail

ROOT_OPT="--root=web"
if [ -x "./vendor/bin/drush" ]; then DR="./vendor/bin/drush $ROOT_OPT"; else DR="drush $ROOT_OPT"; fi

MOD=driven_variation_fit_widget
BASE="web/modules/custom/$MOD"
mkdir -p "$BASE/src/Plugin/Field/FieldWidget"

# --- .info.yml ---
cat >"$BASE/$MOD.info.yml" <<'YAML'
name: Driven Variation Fit Widget
type: module
description: A drag-and-drop list widget to manage selected motorcycles on product variations.
core_version_requirement: ^11
package: Custom
dependencies:
  - drupal:driven_motorcycles
  - drupal:commerce_product
YAML

# --- widget plugin ---
cat >"$BASE/src/Plugin/Field/FieldWidget/DrivenMotoListWidget.php" <<'PHP'
<?php

namespace Drupal\driven_variation_fit_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a DND list widget for entity_reference to driven_motorcycle.
 *
 * @FieldWidget(
 *   id = "driven_moto_list_widget",
 *   label = @Translation("Driven: Motorcycle list (drag, remove, add)"),
 *   field_types = { "entity_reference" }
 * )
 */
class DrivenMotoListWidget extends WidgetBase {

  protected EntityTypeManagerInterface $etm;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->etm = $container->get('entity_type.manager');
    return $instance;
  }

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Only allow this widget when the field targets driven_motorcycle.
    $target_type = $this->getFieldSetting('target_type');
    if ($target_type !== 'driven_motorcycle') {
      $element['#markup'] = $this->t('This widget requires target_type = driven_motorcycle.');
      return $element;
    }

    // Build an array of current target_ids in order.
    $current = [];
    foreach ($items as $item) {
      if (!empty($item->target_id)) {
        $current[] = (int) $item->target_id;
      }
    }

    $motoStorage = $this->etm->getStorage('driven_motorcycle');
    $loaded = $current ? $motoStorage->loadMultiple($current) : [];

    // Table of current selections.
    $element['current'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('Year'),
        $this->t('Make'),
        $this->t('Model'),
        $this->t('Keep'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No motorcycles selected yet. Use the picker below to add.'),
      '#attributes' => ['class' => ['driven-moto-list']],
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'driven-moto-weight',
      ]],
    ];

    $weight = 0;
    foreach ($current as $mid) {
      if (empty($loaded[$mid])) {
        continue;
      }
      $m = $loaded[$mid];

      $element['current'][$mid]['#attributes']['class'][] = 'draggable';

      // Hidden target id for this row.
      $element['current'][$mid]['target_id'] = [
        '#type' => 'hidden',
        '#value' => $mid,
      ];

      $element['current'][$mid]['label'] = [
        '#markup' => $m->label(),
      ];
      $element['current'][$mid]['year'] = [
        '#markup' => (string) ($m->get('year')->value ?? ''),
      ];
      $element['current'][$mid]['make'] = [
        '#markup' => (string) ($m->get('make')->value ?? ''),
      ];
      $element['current'][$mid]['model'] = [
        '#markup' => (string) ($m->get('model')->value ?? ''),
      ];

      // Keep checkbox (uncheck to remove on save).
      $element['current'][$mid]['keep'] = [
        '#type' => 'checkbox',
        '#default_value' => 1,
      ];

      // Weight for ordering (drag handle).
      $element['current'][$mid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $m->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['driven-moto-weight']],
      ];
      $weight++;
    }

    // Add picker (single) + button. No AJAX; submit or the Add button triggers whole form submit.
    $element['add'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Add motorcycle'),
      '#target_type' => 'driven_motorcycle',
      '#tags' => FALSE,
      '#description' => $this->t('Pick one motorcycle to add, then click "Add motorcycle" or Save.'),
      '#weight' => 100,
      // Provide default empty; value processed in massageFormValues().
    ];

    // A regular submit button that just triggers full form submit. We detect it in massageFormValues().
    $element['add_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add motorcycle'),
      '#name' => $this->getElementId($element, 'add_submit'),
      '#weight' => 101,
    ];

    return $element;
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Our widget stores a single element; $values is an array with one element.
    $v = $values[0] ?? [];
    $out = [];

    // 1) Read current rows: keep only checked ones, collect (target_id, weight).
    $rows = [];
    if (!empty($v['current']) && is_array($v['current'])) {
      foreach ($v['current'] as $mid => $row) {
        $tid = (int) ($row['target_id'] ?? 0);
        $keep = !empty($row['keep']);
        $w = isset($row['weight']) ? (int) $row['weight'] : 0;
        if ($tid && $keep) {
          $rows[] = ['target_id' => $tid, 'weight' => $w];
        }
      }
    }

    // Sort by weight.
    usort($rows, static function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    // 2) If the add button was pressed OR simply a normal save with a selected add value, append it (avoid dups).
    $trigger = $form_state->getTriggeringElement();
    $add_tid = 0;
    if (!empty($v['add'])) {
      // entity_autocomplete returns either target_id or textual "123 (Label)".
      if (is_array($v['add']) && !empty($v['add']['target_id'])) {
        $add_tid = (int) $v['add']['target_id'];
      }
      elseif (is_scalar($v['add']) && preg_match('/^\d+/', (string) $v['add'], $m)) {
        $add_tid = (int) $m[0];
      }
    }

    if ($add_tid) {
      $already = array_column($rows, 'target_id');
      if (!in_array($add_tid, $already, true)) {
        $rows[] = ['target_id' => $add_tid, 'weight' => count($rows)];
      }
    }

    // 3) Emit the final ordered list as entity reference values.
    foreach ($rows as $r) {
      $out[] = ['target_id' => (int) $r['target_id']];
    }

    return [$out];
  }

  /**
   * Build a unique but predictable name attribute for the button.
   */
  protected function getElementId(array $element, string $suffix): string {
    // Widgets can appear multiple times; use parents path.
    $parents = isset($element['#field_parents']) ? $element['#field_parents'] : [];
    $parents[] = $this->fieldDefinition->getName();
    $parents[] = $suffix;
    return implode('__', $parents);
  }

}
PHP

echo "→ Enabling module…"
$DR pm:enable driven_variation_fit_widget -y

echo "→ Switching variation form display to use the new widget…"
$DR php:eval '
use Drupal\Core\Entity\Entity\EntityFormDisplay;
$etype = "commerce_product_variation";
$field = "field_fits_motorcycles";
$bundles = array_keys(\Drupal::service("entity_type.bundle.info")->getBundleInfo($etype));
foreach ($bundles as $bundle) {
  $disp = EntityFormDisplay::load("$etype.$bundle.default")
    ?: EntityFormDisplay::create(["targetEntityType"=>$etype,"bundle"=>$bundle,"mode"=>"default","status"=>TRUE]);
  $disp->setComponent($field, [
    "type" => "driven_moto_list_widget",
    "weight" => 98,
    "settings" => [],
  ])->save();
  echo " ✓ $etype.$bundle → driven_moto_list_widget\n";
}
'

echo "→ Rebuilding caches…"
$DR cr -y >/dev/null

echo "✅ Installed. Open a variation edit form to use the new list (drag to reorder, uncheck to remove, use picker to add)."
