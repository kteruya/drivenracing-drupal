<?php

namespace Drupal\driven_variation_fit_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a simple list widget for entity_reference to driven_motorcycle.
 *
 * @FieldWidget(
 *   id = "driven_moto_list_widget",
 *   label = @Translation("Driven: Motorcycle list (drag, remove, add)"),
 *   field_types = { "entity_reference" }
 * )
 */
class DrivenMotoListWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  protected EntityTypeManagerInterface $etm;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
    );
    $instance->etm = $container->get('entity_type.manager');
    return $instance;
  }

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Require target_type = driven_motorcycle.
    if ($this->getFieldSetting('target_type') !== 'driven_motorcycle') {
      $element['#markup'] = $this->t('This widget requires an entity reference to driven_motorcycle.');
      return $element;
    }

    // Current selected IDs in saved order.
    $current = [];
    foreach ($items as $item) {
      if (!empty($item->target_id)) {
        $current[] = (int) $item->target_id;
      }
    }

    $moto_storage = $this->etm->getStorage('driven_motorcycle');
    $loaded = $current ? $moto_storage->loadMultiple($current) : [];

    // “Product-page-style” = a simple list. We’ll keep Keep + drag handle for editing.
    $element['current'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Motorcycle'), // one combined text column, no links
        $this->t('Keep'),
        '',
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

      // Combined text: Year Make Model (NO LINKS)
      $year  = trim((string) ($m->get('year')->value ?? ''));
      $make  = trim((string) ($m->get('make')->value ?? ''));
      $model = trim((string) ($m->get('model')->value ?? ''));
      $parts = array_values(array_filter([$year, $make, $model], fn($x) => $x !== ''));
      $label = $parts ? implode(' ', $parts) : $m->label();

      $element['current'][$mid]['motorcycle'] = ['#markup' => $label];

      // Keep checkbox (uncheck to remove on save).
      $element['current'][$mid]['keep'] = [
        '#type' => 'checkbox',
        '#default_value' => 1,
        '#title_display' => 'invisible',
      ];

      // Drag weight (label hidden).
      $element['current'][$mid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['driven-moto-weight']],
      ];
      $weight++;
    }

    // Picker (single) + button. Processed in massageFormValues().
    $element['add'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Add motorcycle'),
      '#target_type' => 'driven_motorcycle',
      '#tags' => FALSE,
      '#description' => $this->t('Pick one motorcycle to add, then click "Add motorcycle" or Save.'),
      '#weight' => 100,
    ];
    $element['add_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add motorcycle'),
      '#name' => ($element['#field_parents'] ? implode('__', $element['#field_parents']) . '__' : '') . $this->fieldDefinition->getName() . '__add_submit',
      '#weight' => 101,
    ];

    return $element;
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $v = $values[0] ?? [];
    $out = [];

    // Collect rows to keep with weight.
    $rows = [];
    if (!empty($v['current']) && is_array($v['current'])) {
      foreach ($v['current'] as $mid => $row) {
        $tid  = (int) ($row['target_id'] ?? 0);
        $keep = !empty($row['keep']);
        $w    = isset($row['weight']) ? (int) $row['weight'] : 0;
        if ($tid && $keep) {
          $rows[] = ['target_id' => $tid, 'weight' => $w];
        }
      }
    }

    // Order by weight.
    usort($rows, static fn($a, $b) => $a['weight'] <=> $b['weight']);

    // Append “add” selection if present (no dups).
    $add_tid = 0;
    if (!empty($v['add'])) {
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

    foreach ($rows as $r) {
      $out[] = ['target_id' => (int) $r['target_id']];
    }
    return [$out];
  }

}
