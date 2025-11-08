<?php

namespace Drupal\drd_fitment_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Single composite widget for motorcycle fitment on variations.
 *
 * @FieldWidget(
 *   id = "drd_fitment_widget",
 *   label = @Translation("DRD Fitment (single list)"),
 *   field_types = { "entity_reference" },
 *   multiple_values = TRUE
 * )
 */
class DrdFitmentWidget extends WidgetBase {

  /**
   * Build once for all values (no per-delta duplication).
   */
  public public function formMultipleElements(\Drupal\Core\Field\FieldItemListInterface $items, array &$form, \Drupal\Core\Form\FormStateInterface $form_state): array {
    $element = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    // Current assignments.
    $assigned = [];
    foreach ($items->referencedEntities() as $e) {
      if ($e && !$e->isNew()) {
        $assigned[(string) $e->id()] = $e->label();
      }
    }

    // Persist current IDs so we can compute add/remove in massageFormValues().
    $element['drd']['existing'] = [
      '#type' => 'value',
      '#value' => array_keys($assigned),
    ];

    // Assigned table with "Remove" checkboxes.
    if ($assigned) {
      $rows = [];
      foreach ($assigned as $id => $label) {
        $rows[] = [
          'data' => [
            ['data' => ['#markup' => $this->t('@label (ID @id)', ['@label' => $label, '@id' => $id])]],
            ['data' => [
              '#type' => 'checkbox',
              '#title' => $this->t('Remove'),
              '#title_display' => 'invisible',
              '#return_value' => 1,
              // Store checkboxes under ...[drd][remove][<id>].
              '#parents' => [$this->fieldDefinition->getName(), 'drd', 'remove', (string) $id],
            ]],
          ],
        ];
      }

      $element['drd']['list'] = [
        '#type' => 'table',
        '#header' => [$this->t('Motorcycle'), $this->t('Actions')],
        '#rows' => $rows,
        '#empty' => $this->t('No motorcycles assigned.'),
      ];
    }
    else {
      $element['drd']['list_empty'] = [
        '#markup' => $this->t('No motorcycles assigned.'),
      ];
      // Keep structure consistent.
      $element['drd']['remove'] = ['#type' => 'value', '#value' => []];
    }

    // Add more via entity autocomplete (multi-select).
    $element['drd']['add'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Add motorcycles'),
      '#target_type' => 'driven_motorcycle',
      '#tags' => TRUE,
      // Keep this stable even if the field machine name changes.
      '#parents' => [$this->fieldDefinition->getName(), 'drd', 'add'],
      '#description' => $this->t('Type to search; press Enter after each to tag multiple.'),
    ];

    return $element;
  }

  /**
   * Normalize and save values for the field.
   *
   * We compute: new = (existing - removed) ∪ added
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Because multiple_values=TRUE, $values is a single, top-level element.
    $drd = $values['drd'] ?? [];

    $existing = array_map('strval', $drd['existing'] ?? []);
    $to_remove = array_map('strval', array_keys(array_filter($drd['remove'] ?? [])));

    // Normalize "add" value (tags autocomplete can return arrays of entities or IDs).
    $added = [];
    if (!empty($drd['add'])) {
      // When #tags=TRUE, value is an array of arrays with target_id OR just IDs.
      foreach ((array) $drd['add'] as $v) {
        if (is_array($v) && isset($v['target_id'])) {
          if ($v['target_id']) {
            $added[] = (string) $v['target_id'];
          }
        }
        elseif (is_scalar($v) && $v !== '') {
          $added[] = (string) $v;
        }
      }
    }

    // Compute final set.
    $current = array_diff($existing, $to_remove);
    $merged = array_values(array_unique(array_merge($current, $added)));

    // Return the format the Field API expects for entity_reference:
    // an array of item arrays: [ ['target_id'=>1], ['target_id'=>2], ... ]
    $out = [];
    foreach ($merged as $id) {
      // only keep numeric IDs
      if (ctype_digit($id)) {
        $out[] = ['target_id' => (int) $id];
      }
    }
    return $out;
  }

  /**
   * Not used because we build in formMultipleElements(); keep hidden if called.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Hide per-delta UIs to prevent duplication if core calls this.
    return [
      '#type' => 'hidden',
      '#value' => NULL,
      '#access' => FALSE,
    ];
  }

}
