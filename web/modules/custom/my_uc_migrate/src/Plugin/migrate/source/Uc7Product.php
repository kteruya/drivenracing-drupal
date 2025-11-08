<?php

namespace Drupal\my_uc_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Database;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Ubercart products (D7) → source rows for variations/products.
 *
 * @MigrateSource(
 *   id = "uc7_product"
 * )
 */
class Uc7Product extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query(): SelectInterface {
    // Always use the migrate connection declared in the YAML: key: migrate
    $key = $this->configuration['key'] ?? 'migrate';
    $connection = Database::getConnection('default', $key);

    $query = $connection->select('uc_products', 'up');
    $query->fields('up', [
      'nid', 'model', 'list_price', 'cost', 'sell_price',
      'weight', 'weight_units', 'length', 'width', 'height', 'length_units',
      'pkg_qty', 'default_qty', 'ordering', 'shippable',
    ]);
    $query->innerJoin('node', 'n', 'n.nid = up.nid');
    $query->fields('n', ['title', 'status', 'uid', 'created', 'changed']);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'nid' => $this->t('Node ID'),
      'title' => $this->t('Title'),
      'model' => $this->t('SKU'),
      'sell_price' => $this->t('Sell price'),
      'list_price' => $this->t('List price'),
      'cost' => $this->t('Cost'),
      'weight' => $this->t('Weight'),
      'weight_units' => $this->t('Weight units'),
      'length' => $this->t('Length'),
      'width' => $this->t('Width'),
      'height' => $this->t('Height'),
      'length_units' => $this->t('Length units'),
      'status' => $this->t('Published'),
      'uid' => $this->t('Author'),
      'created' => $this->t('Created time'),
      'changed' => $this->t('Updated time'),
      'shippable' => $this->t('Is shippable'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return ['nid' => ['type' => 'integer', 'alias' => 'up']];
  }
}
