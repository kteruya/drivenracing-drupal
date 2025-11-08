<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Common methods for a few possible Swiper formatters.
 */
trait SwiperFormatterTrait {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'template' => 'default',
      'caption' => NULL,
      'custom_link' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {

    // Add a warning message when there's no any swiper entity/template created.
    $this->swiperBase->validateTemplates();

    $elements = parent::settingsForm($form, $form_state);
    // Override View mode to limit to only enabled view modes, per entity.
    $target_type = $this->getFieldSetting('target_type');
    $target_bundles = $this->getFieldSetting('handler_settings')['target_bundles'] ?? [];
    $options = $elements['view_mode']['#options'] ?? [];
    if (!empty($target_bundles)) {
      $options = [];
      foreach ($target_bundles as $target_bundle) {
        $options += $this->swiperBase->getViewModeOptions($target_type, $target_bundle);
      }
    }
    $elements['view_mode']['#options'] = $options;
    $settings = $this->getSettings();
    $settings['template'] = $settings['template'] ?? SwiperFormatterInterface::DEFAULT_TEMPLATE;
    $destinations = $this->swiperBase->getDestination($this->fieldDefinition);
    $settings['destination'] = $destinations['destination'] ?? NULL;
    $settings['caption_field_edit_url'] = $destinations['caption_field_edit_url'] ?? NULL;
    return $elements + $this->swiperBase->processSettings($this->fieldDefinition, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {

    $parent = parent::settingsSummary();
    $summary = [];

    // Build options summary.
    if ($swiper_template = $this->getSetting('template')) {
      if ($swiper_entity = $this->swiperBase->getSwiper($swiper_template)) {
        $summary[] = $this->t('Swiper template: @swiper_template', [
          '@swiper_template' => $swiper_entity->label(),
        ]);
      }
    }

    if ($caption = $this->getSetting('caption')) {
      switch ($caption) {
        case 'title':
        case 'alt':
          $summary[] = $this->t('Caption: Image @caption field', ['@caption' => ucfirst($caption)]);
          break;

        default:
          $fields = $this->swiperBase->getFieldDefinitions($this->fieldDefinition);
          if (isset($fields[$caption])) {
            $summary[] = $this->t('Caption: @caption field', ['@caption' => $fields[$caption]->getLabel()]);
          }
          break;
      }
    }

    // Merge with parent settings summary.
    $summary = array_merge($summary, $parent);

    // Custom link for image.
    if ($this->getSetting('image_link') == 'custom') {
      $summary[] = $this->t('Custom link');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $type = $field_definition->getFieldStorageDefinition()->getType();
    // This formatter only applies to multivalued fields.
    $is_multiple = $field_definition->getFieldStorageDefinition()->isMultiple();
    if ($type == 'entity_reference_revisions' || $type == 'entity_reference') {
      return $is_multiple && parent::isApplicable($field_definition);
    }
    else {
      return $is_multiple;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = [];
    $option_id = $this->getSetting('template');
    // Add the options as dependency.
    if ($option_id) {
      $options = $this->swiperBase->getSwiper($option_id);
      $dependencies[$options->getConfigDependencyKey()][] = $options->getConfigDependencyName();
    }
    return parent::calculateDependencies() + $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);

    if ($this->optionsDependenciesDeleted($this, $dependencies)) {
      $changed = TRUE;
    }
    return $changed;
  }

  /**
   * If a dependency is going to be deleted, set the option set to default.
   *
   * @param \Drupal\Core\Field\FormatterBase $formatter
   *   The formatter has this trait.
   * @param array $dependencies_deleted
   *   An array of dependencies that will be deleted.
   *
   * @return bool
   *   If option set dependencies changed.
   */
  protected function optionsDependenciesDeleted(FormatterBase $formatter, array $dependencies_deleted): bool {
    $option_id = $formatter->getSetting('template');
    if ($option_id && ($options = $this->swiperBase->getSwiper($option_id))) {
      if (!empty($dependencies_deleted[$options->getConfigDependencyKey()]) && in_array($options->getConfigDependencyName(), $dependencies_deleted[$options->getConfigDependencyKey()])) {
        $formatter->setSetting('template', NULL);
        return TRUE;
      }
      return FALSE;
    }
    return FALSE;
  }

}
