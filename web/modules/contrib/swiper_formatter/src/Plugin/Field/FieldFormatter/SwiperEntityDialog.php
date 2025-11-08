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
  id: 'swiper_formatter_entity_dialog',
  label: new TranslatableMarkup('Swiper entity Dialog'),
  field_types: [
    'entity_reference',
  ]
)]
class SwiperEntityDialog extends SwiperEntity {

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
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    $entity_bundle = $this->fieldDefinition->getTargetBundle();
    $options = $entity_bundle ? $this->entityDisplayRepository->getViewModeOptionsByBundle($entity_type, $entity_bundle) : $this->entityDisplayRepository->getViewModeOptions($entity_type);
    $reference_options = $elements['view_mode']['#options'];
    $elements['view_mode']['#description'] = $this->t('Make sure the rendered markup does not contain links, otherwise the dialog will not work.');
    $this->swiperDialog->processSettings($this->getSettings(), $options, $reference_options, $elements);
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
    $this->swiperDialog->getSummary('entity', $this->getSettings(), $summary);
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    if ($this->getSetting('dialog_target') == 'referenced_entity') {
      $entity_type = $this->getFieldSetting('target_type');
      $entity = $elements['#content'][0]['#' . $entity_type] ?? $items->getEntity();
    }
    else {
      $entity = $items->getEntity();
    }
    $dialog_view = $this->getSetting('dialog_view_item');
    $field = $dialog_view != 'entity' ? $this->fieldDefinition->getName() : $this->swiperDialog::DEFAULT_FIELD;
    $field_item = $dialog_view == 'field_item' ? 1 : 0;
    $this->swiperDialog->processElements($entity, $this->getSettings(), $elements, $field, $field_item);
    return $elements;
  }

}
