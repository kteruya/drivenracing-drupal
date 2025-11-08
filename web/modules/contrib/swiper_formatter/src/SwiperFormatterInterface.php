<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Nk tools swiper entity type.
 */
interface SwiperFormatterInterface extends ConfigEntityInterface {

  /**
   * The machine name of the default swiper entity/template.
   *
   * @var string
   */
  public const DEFAULT_TEMPLATE = 'default';

  /**
   * Default Swiper's modules.
   *
   * @var array
   */
  public const SWIPER_MODULES = [
    'navigation',
    'pagination',
    'scrollbar',
    'autoplay',
    'lazy',
    'grid',
  ];

  /**
   * Properties that can be assigned to a breakpoint template.
   *
   * @var array
   */
  public const BREAKPOINT_OPTIONS = [
    'slidesPerView',
    'spaceBetween',
    'navigation',
    'pagination',
  ];

  /**
   * Gets the swiper options array.
   *
   * @return array
   *   An array containing the raw swiper options.
   */
  public function getSwiperOptions();

  /**
   * Sets the swiper options.
   *
   * @param array $options
   *   An array containing the raw swiper options.
   *
   * @return $this
   *   The class instance this method is called on.
   */
  public function setSwiperOptions(array $options);

  /**
   * Returns an array with all the properties that are Swiper.js options.
   *
   * @param bool $check_breakpoint
   *   When true, check on breakpoint templates.
   *
   * @return array
   *   An array with swiper options, keyed by entity id.
   */
  public static function getSwipers(bool $check_breakpoint = FALSE): array;

  /**
   * Sets swiper_options property to deliver later to Swiper in js, as options.
   *
   * @param array $swiper_options
   *   Swiper template options to set to entity.
   *
   * @return self
   *   Object instance implementing this interface.
   */
  public function setSwiper(array $swiper_options = []): self;

}
