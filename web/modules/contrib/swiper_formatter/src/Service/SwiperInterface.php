<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Service;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Dialog type field formatters interface.
 */
interface SwiperInterface {

  /**
   * Default settings for all Swiper formatters.
   *
   * @var array
   */
  public const DEFAULT_SETTINGS = [
    'template' => 'default',
    'caption' => NULL,
    'destination' => NULL,
    'swiper_access' => FALSE,
  ];

  /**
   * Get field definitions for entity type and bundle.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   A field definition object.
   *
   * @return array
   *   Array with available fields and its properties.
   */
  public function getFieldDefinitions(FieldDefinitionInterface $field_definition): array;

  /**
   * Get entity view display class.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached. Or referenced entity if opted so.
   * @param string $view_mode
   *   Entity view mode machine name.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   Entity view display class.
   */
  public function getDisplay(FieldableEntityInterface $entity, string $view_mode = 'default'): EntityViewDisplayInterface;

  /**
   * Get enabled View modes for the given entity.
   *
   * @param string $target_type
   *   Target entity type id.
   * @param string $target_bundle
   *   Target entity bundle.
   *
   * @return array
   *   View modes in a key-label array.
   */
  public function getViewModeOptions(string $target_type, string $target_bundle): array;

  /**
   * Load image style entity.
   *
   * @param string $image_style
   *   Image style id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Image style entity object.
   */
  public function getImageStyle(string $image_style): EntityInterface|NULL;

  /**
   * Load swiper entity.
   *
   * @param string $swiper_id
   *   Swiper entity id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Swiper entity object.
   */
  public function getSwiper(string $swiper_id): EntityInterface|NULL;

  /**
   * Validate the existence of at least one swiper entity/template.
   */
  public function validateTemplates(): void;

  /**
   * Prepare common settings for elements.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   A field definition object.
   * @param array $settings
   *   Formatter settings array.
   *
   * @return array
   *   A set of elements for formatter settings form.
   */
  public function processSettings(FieldDefinitionInterface $field_definition, array $settings): array;

  /**
   * Prepare common settings for elements.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   A field definition object.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached. Or referenced entity if opted so.
   * @param array $settings
   *   Formatter settings array.
   * @param array $output
   *   Parent's formatter render array.
   *
   * @return array
   *   A set of elements for a formatter settings form.
   */
  public function processElements(FieldDefinitionInterface $field_definition, FieldableEntityInterface $entity, array $settings, array $output): array;

  /**
   * Generate unique ID for swiper, or the other element(s).
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached. Or referenced entity if opted so.
   * @param null|\Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   A field definition object.
   * @param string|null $view_mode
   *   Display (view) mode machine name.
   * @param string|null $delta
   *   Field its delta or any similar index like value.
   *
   * @return string
   *   Unique string to be used as attribute (id) or similar.
   */
  public function elementId(FieldableEntityInterface $entity, ? FieldDefinitionInterface $field_definition = NULL, ?string $view_mode = NULL, ?string $delta = NULL): string;

  /**
   * Define swiper's theme render-able array.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached. Or referenced entity if opted so.
   * @param array $output
   *   Parent's formatter render array.
   * @param array $settings
   *   Formatter settings array.
   * @param array $theme_functions
   *   Swiper theme hooks.
   *
   * @return array
   *   Swiper theme render-able array.
   */
  public function renderSwiper(FieldableEntityInterface $entity, array $output, array $settings, array $theme_functions = []): array;

  /**
   * Render swiper slide theme.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached. Or referenced entity if opted so.
   * @param array $settings
   *   Formatter settings array.
   * @param array $item
   *   Current item in a theme render array.
   *
   * @return array
   *   Swiper slide theme array.
   */
  public function renderSwiperSlide(FieldableEntityInterface $entity, array $settings, array $item): array;

  /**
   * Get value for a swiper caption.
   *
   * @param array $item
   *   Referenced render-able field item array.
   * @param string|null $caption_field
   *   The machine name of the caption field, if any.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   Entity to which the field is attached.
   * @param int $delta
   *   The current index of an item in a content array.
   */
  public function getCaption(array &$item, ?string $caption_field = NULL, ?FieldableEntityInterface $entity = NULL, int $delta = 0): void;

  /**
   * Render replacement value for a token.
   *
   * @param string $markup
   *   Token input string to be replaced. Or other string value.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached.
   *
   * @return string
   *   Token replacement value.
   */
  public function tokenValue(string $markup, FieldableEntityInterface $entity): string;

  /**
   * Get some back-links for operations on swiper settings.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   A field definition object.
   *
   * @return array
   *   Contains destination path and field_edit path.
   */
  public function getDestination(FieldDefinitionInterface $field_definition): array;

}
