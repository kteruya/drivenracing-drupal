<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\swiper_formatter\Service\SwiperInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin to render each item in an ordered or unordered list.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "swiper_formatter",
 *   title = @Translation("Swiper formatter"),
 *   help = @Translation("Display the results in a Swiper."),
 *   theme = "swiper_formatter",
 *   display_types = {"normal"}
 * )
 * @phpstan-consistent-constructor
 */
class SwiperFormatterStyle extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountInterface $currentUser,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected SwiperInterface $swiperBase,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('swiper_formatter.base')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['template'] = ['default' => 'default'];
    $options['caption'] = ['default' => NULL];

    // Ensure a unique id attribute for each instance
    // of Swiper on the same page.
    // User can change this on settings, but we try to
    // make sure some unique id is auto assigned.
    $view_id = $this->view->id();
    $current_display = $this->view->current_display;
    $options['id'] = ['default' => Html::getUniqueId('swiper-' . $view_id . '-' . $current_display)];

    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['swiper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Swiper formatter settings'),
    ];

    if ($swiper_entity = $this->swiperBase->getSwiper($this->options['template'])) {
      /** @var \Drupal\swiper_formatter\SwiperFormatterInterface $swiper_entity */
      $this->options += $swiper_entity->get('swiper_options');
    }

    $form['swiper']['swiper_el'] = [
      '#type' => 'swiper_formatter_settings',
      '#title' => $this->t('Swiper formatter settings'),
      '#default_value' => [
        'type' => 'views',
        'name' => 'views',
        'template' => $this->options['template'],
        'settings' => $this->options,
        'custom_link' => $this->options['custom_link'] ?? NULL,
        'entity_type' => 'default',
        'swiper_access' => $this->currentUser->hasPermission('administer swiper'),
        'caption' => [
          'value' => $this->options['caption'] ?? NULL,
          'entity_fields' => [],
          'field_edit_url' => $this->options['caption_field_edit_url'] ?? NULL,
        ],
        // Images specific.
        'title_field' => NULL,
        'alt_field' => NULL,
      ],
    ];

    /* $this->view->initStyle(); */
    if ($this->usesFields()) {

      $fields = (array) $this->view->style_plugin->displayHandler->handlers['field'];
      $entity_fields = [];

      foreach ($fields as $field_name => $field) {
        // Double check on field storage definition existence.
        /** @var \Drupal\views\Plugin\views\field\EntityField $field */
        $field_storage_definitions = isset($field->definition['entity_type']) && !empty($field->definition['entity_type']) ? $this->entityFieldManager->getFieldStorageDefinitions($field->definition['entity_type']) : [];
        if (isset($field->definition['field_name']) && isset($field_storage_definitions[$field->definition['field_name']])) {

          if ($field->options['type'] == 'image' && (count($fields) == 1 || count($fields) == 2)) {
            $form['swiper']['swiper_el']['#default_value']['type'] = 'image';
            $alt_subfield = $field->definition['field_name'] . '_alt';
            $title_subfield = $field->definition['field_name'] . '_title';
            if (in_array($alt_subfield, $field->definition['additional fields'])) {
              $form['swiper']['swiper_el']['#default_value']['settings']['alt_field'] = TRUE;
            }
            if (in_array($title_subfield, $field->definition['additional fields'])) {
              $form['swiper']['swiper_el']['#default_value']['settings']['title_field'] = TRUE;
            }
          }

          $entity_fields[$field_name] = [
            'title' => $field->definition['title'],
            'storage' => $field_storage_definitions[$field->definition['field_name']],
          ];
        }
      }

      $form['swiper']['swiper_el']['#default_value']['caption'] = [
        'value' => $this->options['caption'],
        'entity_fields' => $entity_fields,
        'field_edit_url' => NULL,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function renderFields(array $result): void {
    parent::renderFields($result);
    // Remove field that was set to be a slide caption.
    if (!empty($this->options['caption']) && !empty($this->rendered_fields)) {
      foreach ($this->rendered_fields as &$rendered_field) {
        if (isset($rendered_field[$this->options['caption']])) {
          unset($rendered_field[$this->options['caption']]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {

    $output = [];
    $sets = parent::render();

    if (!isset($this->options['template']) || empty($this->options['template']) || empty($sets)) {
      return $output;
    }
    if ($swiper_entity = $this->swiperBase->getSwiper($this->options['template'])) {
      /** @var \Drupal\swiper_formatter\SwiperFormatterInterface $swiper_entity */

      $this->options += $swiper_entity->get('swiper_options');
      $this->options['field_type'] = 'views';
      $this->options['is_image'] = FALSE;

      if ($this->usesFields()) {
        $fields = (array) $this->view->style_plugin->displayHandler->handlers['field'];
        if (count($fields) == 1 || count($fields) == 2) {
          foreach ($fields as $field) {
            /** @var \Drupal\views\Plugin\views\field\EntityField $field */
            if (isset($field->options['type']) && $field->options['type'] == 'image') {
              if (isset($field->options['settings']['image_style']) && !empty($field->options['settings']['image_style'])) {
                $this->options['image_style'] = $field->options['settings']['image_style'];
              }
              $this->options['field_name'] = $field->definition['field_name'];
              $this->options['field_type'] = 'views_image';
              $this->options['is_image'] = $field->definition['field_name'];
            }
          }
        }
      }

      foreach ($sets as $index => &$set) {

        $captions = [];

        foreach ($set['#rows'] as $delta => &$row) {

          $i = 0;
          $entity = $this->getEntity($row);

          // Take care of the caption.
          if (is_object($entity) && isset($this->options['caption']) && !empty($this->options['caption'])) {
            $image_subfields = ['alt', 'title'];
            if ($this->options['is_image'] && in_array($this->options['caption'], $image_subfields)) {
              foreach ($entity->get($this->options['is_image'])->getValue() as $img_delta => $img_value) {
                $captions[$img_delta] = isset($entity->get($this->options['is_image'])[$img_delta]) ? ['value' => $entity->get($this->options['is_image'])[$img_delta]->{$this->options['caption']}] : [];
              }
              $row['#caption'] = $this->parseLinear($i, $delta, 'caption', $captions);
            }
            else {
              if ($entity->hasField($this->options['caption']) && !empty($entity->get($this->options['caption'])->getValue())) {
                $row['#caption'] = $this->parseLinear($i, $delta, 'caption', $entity->get($this->options['caption'])->getValue());
              }
            }
          }
          if (is_object($entity) && isset($this->options['field_name']) && $entity->hasField($this->options['field_name']) && !empty($entity->get($this->options['field_name'])->getValue())) {
            $row['#background'] = $this->parseLinear($i, $delta, 'background', $entity->get($this->options['field_name'])->getValue());
          }
        }
        // Render Swiper.
        $output[$index] = $this->swiperBase->renderSwiper($this->getEntity($set['#rows'][0]), $set['#rows'], $this->options, $this->themeFunctions());
      }
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::submitOptionsForm($form, $form_state);

    // Move swiper options to the parent array so that
    // values are saved properly.
    $swiper_options = $form_state->getValue([
      'style_options',
      'swiper',
      'swiper_el',
    ]);

    foreach ($swiper_options as $key => $value) {
      $form_state->setValue(['style_options', $key], $value);
    }

    $form_state->setValue(['style_options', 'swiper'], NULL);
  }

  /**
   * Find entity to which swiper field is attached within the Views row.
   *
   * @param array $row
   *   Views row array.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   Entity object.
   *
   * @throws \Exception
   *   Thrown when an entity cannot be found within the views row array.
   */
  protected function getEntity(array $row): FieldableEntityInterface {
    if (isset($row['#row']->_entity) && $row['#row']->_entity instanceof FieldableEntityInterface) {
      return $row['#row']->_entity;
    }
    elseif (isset($row['entity']['#theme']) && isset($row['entity']['#' . $row['entity']['#theme']]) && $row['entity']['#' . $row['entity']['#theme']] instanceof FieldableEntityInterface) {
      return $row['entity']['#' . $row['entity']['#theme']];
    }
    elseif (isset($row['#theme']) && isset($row['#' . $row['#theme']]) && $row['#' . $row['#theme']] instanceof FieldableEntityInterface) {
      return $row['#' . $row['#theme']];
    }
    elseif (isset($row['#entity_type']) && isset($row['#' . $row['#entity_type']]) && $row['#' . $row['#entity_type']] instanceof FieldableEntityInterface) {
      return $row['#' . $row['#entity_type']];
    }
    else {
      foreach ($row as $property) {
        if ($property instanceof FieldableEntityInterface) {
          return $property;
        }
      }
    }

    // An entity could not be found in this row.
    throw new \Exception('An entity could not be found within this views row.');
  }

  /**
   * Some Kung-fu magic here.
   *
   *  Try to reset deltas when
   *  "Multiple field settings > Display all values in the same row"
   *  in fields setting in a View is disabled.
   *
   * @param int $index
   *   Custom index.
   * @param int $delta
   *   Default index/delta returned from a view render.
   * @param string $type
   *   Property we are looking for and returning.
   * @param array $field_values
   *   An array of field values returned by its parent entity.
   *
   * @return null|string
   *   Either a raw caption string (to be rendered),
   *   or url of image field (for lazy loading feature).
   */
  protected function parseLinear(int &$index, int $delta, string $type, array $field_values = []): string|NULL {

    $values = NULL;

    if (count($field_values) > 0) {
      $count_values = count($field_values) - 1;
      if ($delta >= $count_values) {
        $index = $delta > 1 ? $delta - $count_values : $delta;
      }
      else {
        $index = $delta;
      }

      if ($type == 'caption') {
        $values = $field_values[$index]['value'] ?? NULL;
      }
      elseif ($type == 'background') {
        // Lazy load support.
        if ($this->options['is_image'] && $this->options['lazy']['enabled']) {
          $values = $this->lazyLoad($index, $field_values);
        }
      }
      $index++;
    }
    return $values;
  }

  /**
   * Get image URL to use Lazy loading feature in template.
   *
   * @param int $index
   *   Custom index.
   * @param array $field_values
   *   An array of field values returned by its parent entity.
   *
   * @return null|string
   *   A path or url of the image to set as data attribute,
   *   for Lazy loading Swiper feature. It respects selected image style.
   */
  protected function lazyLoad(int $index, array $field_values): string|NULL {
    $background = NULL;
    $image_target_id = $field_values[$index]['target_id'] ?? NULL;
    if ($image_target_id) {
      /** @var \Drupal\file\FileStorageInterface $file_storage */
      $file_storage = $this->entityTypeManager->getStorage('file');
      if ($file = $file_storage->load($image_target_id)) {
        /** @var \Drupal\file\FileInterface $file */
        if (isset($this->options['image_style']) && !empty($this->options['image_style'])) {
          /** @var \Drupal\image\ImageStyleStorageInterface $image_style_storage */
          $image_style_storage = $this->entityTypeManager->getStorage('image_style');
          /** @var \Drupal\image\ImageStyleInterface $image_style */
          $image_style = $image_style_storage->load($this->options['image_style']);
          $background = $image_style->buildUrl($file->getFileUri());
        }
        // No Image style set (bad :)
        else {
          $background = $file->createFileUrl();
        }

        // Important Swiper options for to combine with Lazy Loading module.
        $this->options['preloadImages'] = FALSE;
        $this->options['watchSlidesProgress'] = TRUE;
      }
    }
    return $background;
  }

}
