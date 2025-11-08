#!/usr/bin/env bash
set -euo pipefail

# Adjust these if needed:
PROJECT_ROOT="$(pwd)"               # -> run this script from Drupal project root
MODULE_DIR="web/modules/custom/uc_migrate"
TARBALL_NAME="uc_migrate-v1.1.tar.gz"

echo "Creating uc_migrate module under: ${MODULE_DIR}"
# remove any old temporary content (BE CAREFUL)
rm -rf "${MODULE_DIR}"
mkdir -p "${MODULE_DIR}/config/install"
mkdir -p "${MODULE_DIR}/src/Plugin/migrate/source"

# 1) uc_migrate.info.yml
cat > "${MODULE_DIR}/uc_migrate.info.yml" <<'YAML'
name: 'Ubercart Migrate'
type: module
description: 'Custom migrations: Drupal 7 Ubercart → Drupal 11 Commerce (products).'
core_version_requirement: ^11
package: 'Migration'
dependencies:
  - drupal:migrate
  - drupal:migrate_plus
  - drupal:migrate_tools
YAML

# 2) uc_migrate.module (empty placeholder)
cat > "${MODULE_DIR}/uc_migrate.module" <<'PHP'
<?php
// uc_migrate.module
// Placeholder for the uc_migrate custom migration module.
PHP

# 3) config/install/migrate_plus.migration_group.uc_migrate.yml
cat > "${MODULE_DIR}/config/install/migrate_plus.migration_group.uc_migrate.yml" <<'YAML'
id: uc_migrate
label: 'uc_migrate (Ubercart custom migrations)'
description: 'Group for custom Ubercart to Commerce migrations'
source_type: 'Drupal 7 (Ubercart)'
shared_configuration:
  source:
    key: migrate
YAML

# 4) config/install/migrate_plus.migration.ucProducts.yml
cat > "${MODULE_DIR}/config/install/migrate_plus.migration.ucProducts.yml" <<'YAML'
id: ucProducts
label: 'Ubercart products (D7) to Commerce product (D11)'
migration_group: uc_migrate
source:
  plugin: uc_products_source
  key: migrate
process:
  # adjust destination field names to match your Commerce product fields
  title: title
  sku: model
  field_list_price: list_price
  field_cost: cost
  field_price: sell_price
  field_weight: weight
  field_weight_units: weight_units
  field_length: length
  field_width: width
  field_height: height
  field_length_units: length_units
  field_package_quantity: pkg_qty
  field_default_quantity: default_qty
  field_ordering: ordering
  field_shippable: shippable
destination:
  plugin: 'entity:commerce_product'
migration_dependencies: {}
YAML

# 5) src/Plugin/migrate/source/UbercartProducts.php
cat > "${MODULE_DIR}/src/Plugin/migrate/source/UbercartProducts.php" <<'PHP'
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
    // Query the uc_products table. 'p' alias for uc_products.
    $query = $this->select('uc_products', 'p')
      ->fields('p', [
        'nid',
        'model',
        'list_price',
        'cost',
        'sell_price',
        'weight',
        'weight_units',
        'length',
        'width',
        'height',
        'length_units',
        'pkg_qty',
        'default_qty',
        'ordering',
        'shippable',
      ]);

    // Join to node to include title/author if needed.
    $query->join('node', 'n', 'n.nid = p.nid')
      ->fields('n', ['title', 'uid', 'status', 'created', 'changed']);

    // Optionally uncomment to restrict to nodes of type 'product'
    // $query->condition('n.type', 'product');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'nid' => $this->t('Node ID'),
      'model' => $this->t('SKU/model'),
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
      'shippable' => $this->t('Shippable flag'),
      'title' => $this->t('Node title'),
      'uid' => $this->t('Author uid'),
      'status' => $this->t('Published status'),
      'created' => $this->t('Created timestamp'),
      'changed' => $this->t('Changed timestamp'),
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
    // Do any per-row adjustments here, e.g., normalize fields or attach files.
    return parent::prepareRow($row);
  }
}
PHP

# 6) Set reasonable file permissions
find "${MODULE_DIR}" -type d -exec chmod 755 {} \;
find "${MODULE_DIR}" -type f -exec chmod 644 {} \;

# 7) Create tarball of the module
echo "Creating tarball ${TARBALL_NAME}..."
tar -czf "${PROJECT_ROOT}/${TARBALL_NAME}" -C "${PROJECT_ROOT}/web/modules/custom" "uc_migrate"

# 8) Print summary
echo "Done."
echo "Module created at: ${MODULE_DIR}"
echo "Tarball created at: ${PROJECT_ROOT}/${TARBALL_NAME}"
echo
echo "Next steps (example):"
echo "1) Ensure your D7 connection is in settings.php under \$databases['migrate']['default']"
echo "2) Copy/extract tarball to another site: "
echo "   tar -xzf ${TARBALL_NAME} -C web/modules/custom/"
echo "3) Enable dependencies and module:"
echo "   drush en migrate migrate_plus migrate_tools -y"
echo "   drush en uc_migrate -y"
echo "4) Rebuild caches:"
echo "   drush cr"
echo "5) Check migrations:"
echo "   drush migrate:status --group=uc_migrate"
echo "6) Simulate and run:"
echo "   drush migrate:import ucProducts --simulate"
echo "   drush migrate:import ucProducts"
