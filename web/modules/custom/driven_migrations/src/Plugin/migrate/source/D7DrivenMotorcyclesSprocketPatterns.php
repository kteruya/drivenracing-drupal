<?php

namespace Drupal\driven_migrations\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Source plugin for Driven Motorcycles sprocket patterns from D7.
 *
 * Reads id, front_sproket_pattern, rear_sproket_pattern from the D7 table.
 *
 * @MigrateSource(
 *   id = "d7_driven_motorcycles_sprocket_patterns"
 * )
 */
class D7DrivenMotorcyclesSprocketPatterns extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select($this->configuration['table'], 'dm')
      ->fields('dm', [
        $this->configuration['id_column'],  // "id"
        'front_sproket_pattern',
        'rear_sproket_pattern',
      ]);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Motorcycle ID'),
      'front_sproket_pattern' => $this->t('Front sprocket pattern (D7, misspelled)'),
      'rear_sproket_pattern' => $this->t('Rear sprocket pattern (D7, misspelled)'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      $this->configuration['id_column'] => [
        'type' => 'integer',
        'alias' => 'dm',
      ],
    ];
  }

}
