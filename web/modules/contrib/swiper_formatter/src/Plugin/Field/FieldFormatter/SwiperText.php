<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\swiper_formatter\Service\SwiperInterface;
use Drupal\swiper_formatter\SwiperFormatterTrait;
use Drupal\text\Plugin\Field\FieldFormatter\TextTrimmedFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Swiper markup' formatter.
 *
 * @phpstan-consistent-constructor
 */
#[FieldFormatter(
  id: 'swiper_formatter_text',
  label: new TranslatableMarkup('Swiper markup'),
  field_types: [
    'text',
    'text_long',
    'text_with_summary',
  ]
)]
class SwiperText extends TextTrimmedFormatter {

  use SwiperFormatterTrait;

  /**
   * Swiper formatter base service.
   *
   * @var \Drupal\swiper_formatter\Service\SwiperInterface
   */
  protected SwiperInterface $swiperBase;

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->swiperBase = $container->get('swiper_formatter.base');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return parent::defaultSettings() + SwiperInterface::DEFAULT_SETTINGS;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $output = parent::viewElements($items, $langcode);
    // No items/values for this field yet.
    if (!$items->count()) {
      return $output;
    }
    $entity = $items->getEntity();
    $data = $this->swiperBase->processElements($this->fieldDefinition, $entity, $this->getSettings(), $output);
    foreach ($data['output'] as $delta => &$item) {
      // Caption handling.
      $caption = $data['settings']['caption'] ?? NULL;
      $this->swiperBase->getCaption($item, $caption, $entity, $delta);
    }
    return $this->swiperBase->renderSwiper($entity, $data['output'], $data['settings']);
  }

}
