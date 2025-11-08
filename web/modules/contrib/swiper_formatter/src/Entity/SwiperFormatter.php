<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

use Drupal\swiper_formatter\SwiperFormatterInterface;

/**
 * Defines the Swiper entity type.
 *
 * @ConfigEntityType(
 *   id = "swiper_formatter",
 *   label = @Translation("Swiper"),
 *   label_collection = @Translation("Swipers"),
 *   label_singular = @Translation("swiper"),
 *   label_plural = @Translation("swipers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count swiper",
 *     plural = "@count swipers",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\swiper_formatter\SwiperFormatterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\swiper_formatter\Form\SwiperFormatterForm",
 *       "edit" = "Drupal\swiper_formatter\Form\SwiperFormatterForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "swiper_formatter",
 *   admin_permission = "administer swiper_formatter",
 *   links = {
 *     "collection" = "/admin/structure/swiper-formatter",
 *     "add-form" = "/admin/structure/swiper-formatter/add",
 *     "edit-form" = "/admin/structure/swiper-formatter/{swiper_formatter}",
 *     "delete-form" = "/admin/structure/swiper-formatter/{swiper_formatter}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "status",
 *     "breakpoint",
 *     "swiper_options"
 *   }
 * )
 */
class SwiperFormatter extends ConfigEntityBase implements SwiperFormatterInterface {

  /**
   * The swiper ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * Swiper entity label.
   *
   * @var string
   */
  protected string $label;

  /**
   * Swiper entity description.
   *
   * @var string
   */
  protected string $description;

  /**
   * Flagged as breakpoint.
   *
   * @var bool
   */
  protected bool $breakpoint = FALSE;

  /**
   * Collect all the Swiper's properties into a single array.
   *
   * @var array
   */
  public array $swiper_options = [];

  /**
   * {@inheritdoc}
   */
  public function getSwiperOptions() {
    return $this->get('swiper_options');
  }

  /**
   * {@inheritdoc}
   */
  public function setSwiperOptions(array $options) {
    $this->set('swiper_options', $options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSwipers(bool $check_breakpoint = FALSE): array {
    $swiper_options = [];
    $swipers = static::loadMultiple();
    if (!empty($swipers)) {
      foreach ($swipers as $swiper_entity) {
        if (!$check_breakpoint || !$swiper_entity->get('breakpoint')) {
          $swiper_options[$swiper_entity->id()] = [
            'id' => $swiper_entity->id(),
            'label' => $swiper_entity->label(),
            'properties' => $swiper_entity->toArray(),
          ];
        }
      }
    }
    return $swiper_options;
  }

  /**
   * {@inheritdoc}
   */
  public function setSwiper(array $swiper_options = []): self {
    $this->swiper_options = $swiper_options;
    return $this;
  }

  /**
   * Prepare #options for swiper template options form field.
   *
   * @return array
   *   An array with all available templates, keyed by id.
   */
  public static function getSwiperTemplates(bool $check_breakpoint = FALSE): array {
    $templates = [];
    $swipers = static::getSwipers($check_breakpoint);
    if (!empty($swipers)) {
      foreach ($swipers as $id => $swiper) {
        $templates[$id] = $swiper['label'];
      }
    }
    return $templates;
  }

}
