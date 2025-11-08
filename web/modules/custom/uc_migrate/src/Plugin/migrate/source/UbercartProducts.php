<?php

namespace Drupal\uc_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin for Ubercart products from Drupal 7.
 *
 * @MigrateSource(
 *   id = "uc_products_source"
 * )
 */
class UbercartProducts extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('uc_products', 'p')
      ->fields('p', ['nid', 'model', 'list_price', 'cost', 'sell_price', 'weight', 'weight_units', 'length', 'width', 'height', 'length_units', 'pkg_qty', 'default_qty', 'ordering', 'shippable']);
    $query->leftJoin('node', 'n', 'p.nid = n.nid');
    $query->addField('n', 'title', 'title');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'nid' => $this->t('Node ID'),
      'model' => $this->t('SKU'),
      'list_price' => $this->t('List price'),
      'cost' => $this->t('Cost'),
      'sell_price' => $this->t('Sell price'),
      'weight' => $this->t('Weight'),
      'weight_units' => $this->t('Weight units'),
      'length' => $this->t('Length'),
      'width' => $this->t('Width'),
      'height' => $this->t('Height'),
      'length_units' => $this->t('Length units'),
      'pkg_qty' => $this->t('Package quantity'),
      'default_qty' => $this->t('Default quantity'),
      'ordering' => $this->t('Ordering'),
      'shippable' => $this->t('Shippable'),
      'title' => $this->t('Product title'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
        'alias' => 'p',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
  
  $nid = (int) $row->getSourceProperty('nid');

  // Pull the D7 image field items for this product node.
  $query = $this->select('field_data_uc_product_image', 'i')
    ->fields('i', [
      'delta',
      'uc_product_image_fid',
      'uc_product_image_alt',
      'uc_product_image_title',
    ])
    ->condition('i.entity_type', 'node')
    ->condition('i.entity_id', $nid)
    ->orderBy('i.delta');

  $items = [];
  foreach ($query->execute() as $rec) {
    $items[] = [
      'fid'   => (int) $rec['uc_product_image_fid'],
      'alt'   => $rec['uc_product_image_alt'],
      'title' => $rec['uc_product_image_title'],
    ];
  }

  // Expose to the migration as an array.
  $row->setSourceProperty('uc_product_image', $items);

    return parent::prepareRow($row);
  }

}
