<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\token\Token;

/**
 * Swiper Dialog service.
 */
class SwiperDialog implements SwiperDialogInterface {

  use StringTranslationTrait;

  /**
   * Constructs this base class.
   */
  public function __construct(
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected AccountProxyInterface $currentUser,
    protected Token $token,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getViewModeOptions(FieldDefinitionInterface $field_definition): array {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $entity_bundle = $field_definition->getTargetBundle();
    return $this->entityDisplayRepository->getViewModeOptionsByBundle($entity_type, $entity_bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function processSettings(array $default_settings, array $options, array $referenced_options, array &$elements): void {
    $default_values = $default_settings + [
      'dialog_view_mode_options' => $options,
      'dialog_view_mode_referenced_options' => $referenced_options,
      'dialog_view_mode_access' => $this->currentUser->hasPermission('administer display modes'),
    ];
    $elements['#default_value'] += $default_values;
  }

  /**
   * {@inheritdoc}
   */
  public function processElements(FieldableEntityInterface $entity, array $dialog_options, array &$elements, ?string $field = NULL, int $field_item = 0): void {
    if (!empty($elements['#content'])) {
      foreach ($elements['#content'] as $delta => &$item) {
        $this->processLink($item, $entity, (string) $delta, $dialog_options, $field, $field_item);
      }
      if ($dialog_options['dialog_type'] == 'modal') {
        $elements['#attached']['library'][] = 'core/drupal.dialog.ajax';
      }
      else {
        $elements['#attached']['library'][] = 'core/drupal.dialog.off_canvas';
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(string $formatter, array $settings, array &$summary = []): void {

    $summary[] = $this->t('<hr /><strong>Dialog configuration</strong>');

    if ($formatter == 'entity') {
      $summary[] = $this->t('Target: @dialog_target', [
        '@dialog_target' => ucfirst(str_replace('_', ' ', $settings['dialog_target'])),
      ]);
    }
    $summary[] = $this->t('Target view mode: @dialog_target_view_mode', [
      '@dialog_target_view_mode' => ucfirst(str_replace('_', ' ', $settings['dialog_view_mode'])),
    ]);
    $summary[] = $this->t('Content: @dialog_view_item', [
      '@dialog_view_item' => ucfirst(str_replace('_', ' ', $settings['dialog_view_item'])),
    ]);
    $summary[] = $this->t('Type: @dialog_type', [
      '@dialog_type' => ucfirst(str_replace('_', ' ', $settings['dialog_type'])),
    ]);

    if ($settings['dialog_title']) {
      $summary[] = $this->t('Title: @dialog_title', [
        '@dialog_title' => $settings['dialog_title'],
      ]);
    }
    if ($settings['dialog_width']) {
      $summary[] = $this->t('Width: @dialog_width', [
        '@dialog_width' => $settings['dialog_width'],
      ]);
    }
    if ($settings['dialog_height']) {
      $summary[] = $this->t('Height: @dialog_height', [
        '@dialog_height' => $settings['dialog_height'],
      ]);
    }
    if ($settings['dialog_autoresize']) {
      $summary[] = $this->t('Auto resize: @dialog_autoresize', [
        '@dialog_autoresize' => $this->t('Yes'),
      ]);
    }

    $dialog_classes = [];
    foreach (SwiperDialogInterface::DIALOG_CLASSES as $ui_class => $add_classes) {
      if ($settings[$ui_class]) {
        $dialog_classes[] = $settings[$ui_class];
      }
    }
    if (!empty($dialog_classes)) {
      $summary[] = $this->t('CSS Classes: @dialog_classes', [
        '@dialog_classes' => implode(', ', $dialog_classes),
      ]);
    }
  }

  /**
   * Generate a Drupal dialog link.
   *
   * @param array $item
   *   Content item belonging to theme's render-able array.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached. Or referenced entity if opted so.
   * @param string $delta
   *   A current field item index.
   * @param array $dialog_options
   *   Dialog options array.
   * @param string|null $field
   *   Field machine name.
   * @param int $field_item
   *   If 1, formatter strives to show a single field item, based on delta.
   */
  protected function processLink(array &$item, FieldableEntityInterface $entity, string $delta, array $dialog_options, ?string $field = NULL, int $field_item = 0): void {
    $href = Url::fromRoute('swiper_formatter.dialog', [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'view_mode' => $dialog_options['dialog_view_mode'],
      'field' => $field,
      'delta' => $delta,
      'field_item' => $field_item,
    ])->toString();
    $dialog_classes = [];
    foreach (SwiperDialogInterface::DIALOG_CLASSES as $ui_class => $add_class) {
      $dialog_classes[$ui_class] = $dialog_options[$ui_class];
    }
    if ($dialog_options['dialog_title_hide']) {
      $dialog_classes['ui-dialog-titlebar'] .= ' titlebar-hidden';
      $dialog_classes['ui-dialog-titlebar-close'] .= ' titlebar-hidden';
    }
    $dialog_settings = [
      'width' => $dialog_options['dialog_width'],
      'height' => $dialog_options['dialog_height'],
      'classes' => $dialog_classes,
      'autoResize' => $dialog_options['dialog_autoresize'],
      // @todo Check if any use of some like this.
      /* 'autoOpen' => TRUE, */
      /* 'target' => 'swiper-something--', */
      /* 'drupalAutoButtons' => TRUE, */
    ];
    $attributes = [
      'href' => $href,
      'class' => ['use-ajax'],
      'data-dialog-type' => $dialog_options['dialog_type'],
      'data-index' => $delta,
    ];

    if ($title = $dialog_options['dialog_title']) {
      $token_data[$entity->getEntityTypeId()] = $entity;
      $dialog_settings['title'] = $this->token->replace($title, $token_data, ['clear' => TRUE]);
    }
    $attributes['data-dialog-options'] = Json::encode($dialog_settings);
    $item['#dialog_attributes'] = new Attribute($attributes);
  }

}
