<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Service;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Dialog type field formatters interface.
 */
interface SwiperDialogInterface {

  /**
   * Default Dialog route field parameter.
   *
   * @var string
   */
  public const DEFAULT_FIELD = 'none';

  /**
   * Default settings for Dialog type formatters.
   *
   * @var array
   */
  public const DIALOG_SETTINGS = [
    'dialog_target' => 'referenced_entity',
    'dialog_view_mode' => 'default',
    'dialog_view_item' => 'entity',
    'dialog_type' => 'modal',
    'dialog_width' => 1100,
    'dialog_height' => 680,
    'dialog_title' => '',
    'dialog_title_hide' => FALSE,
    'dialog_autoresize' => TRUE,
  ];

  /**
   * A list of UI dialog classes, matching appropriate elements.
   *
   * @var array
   */
  public const DIALOG_CLASSES = [
    'ui-dialog' => 'swiper-dialog',
    'ui-dialog-titlebar' => 'swiper-dialog-titlebar',
    'ui-dialog-title' => 'swiper-dialog-title',
    'ui-dialog-titlebar-close' => 'swiper-dialog-titlebar-close',
    'ui-dialog-content' => 'swiper-dialog-content',
  ];

  /**
   * Get enabled View modes for the given entity.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition class.
   *
   * @return array
   *   View modes in a key-label array.
   */
  public function getViewModeOptions(FieldDefinitionInterface $field_definition): array;

  /**
   * Process/prepare elements for Swiper Dialog formatters settings.
   *
   * @param array $default_settings
   *   Formatter settings array.
   * @param array $options
   *   View modes for actual entity as #options property.
   * @param array $referenced_options
   *   View modes for referenced entity as #options property.
   * @param array $elements
   *   Swiper settings elements.
   */
  public function processSettings(array $default_settings, array $options, array $referenced_options, array &$elements): void;

  /**
   * Process elements before sending to view builder.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached. Or referenced entity if opted so.
   * @param array $dialog_options
   *   Dialog options array.
   * @param array $elements
   *   Parent's formatter render array.
   * @param string|null $field
   *   Field machine name.
   * @param int $field_item
   *   If 1, formatter strives to show a single field item, based on delta.
   */
  public function processElements(FieldableEntityInterface $entity, array $dialog_options, array &$elements, ?string $field = NULL, int $field_item = 0): void;

  /**
   * Additional settings summary, specific for dialog formatters.
   *
   * @param string $formatter
   *   The type of formatter, usually entity or image, or text.
   * @param array $settings
   *   Formatter settings array.
   * @param array $summary
   *   Existing field formatter summary array.
   */
  public function getSummary(string $formatter, array $settings, array &$summary = []): void;

}
