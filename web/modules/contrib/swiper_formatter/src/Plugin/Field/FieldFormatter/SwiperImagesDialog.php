<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\swiper_formatter\Service\SwiperDialogInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Swiper Dialog images' formatter.
 */
#[FieldFormatter(
  id: 'swiper_formatter_image_dialog',
  label: new TranslatableMarkup('Swiper images Dialog'),
  field_types: [
    'image',
  ]
)]
class SwiperImagesDialog extends SwiperImages {

  /**
   * Swiper formatter base service.
   *
   * @var \Drupal\swiper_formatter\Service\SwiperDialogInterface
   */
  protected SwiperDialogInterface $swiperDialog;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->swiperDialog = $container->get('swiper_formatter.dialog');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return parent::defaultSettings() + SwiperDialogInterface::DIALOG_SETTINGS + SwiperDialogInterface::DIALOG_CLASSES;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $options = $this->swiperDialog->getViewModeOptions($this->fieldDefinition);
    $this->swiperDialog->processSettings($this->getSettings(), $options, [], $elements);
    // We do not want referenced targets for images, it's not entity reference.
    $elements['#default_value']['dialog_target'] = NULL;
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->t('<strong>Swiper configuration</strong>');
    $parent = parent::settingsSummary();
    $summary = array_merge($summary, $parent);
    $this->swiperDialog->getSummary('image', $this->getSettings(), $summary);
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    $entity = $items->getEntity();
    $field_item = $this->getSetting('dialog_view_item') == 'field_item' ? 1 : 0;
    $this->swiperDialog->processElements($entity, $this->getSettings(), $elements, $this->fieldDefinition->getName(), $field_item);
    return $elements;
  }

}
