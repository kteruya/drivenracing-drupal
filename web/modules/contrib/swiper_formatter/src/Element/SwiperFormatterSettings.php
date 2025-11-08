<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\swiper_formatter\Entity\SwiperFormatter;
use Drupal\swiper_formatter\Service\SwiperDialogInterface;

/**
 * Provides a form element with Swiper formatter options.
 *
 * Usage example:
 * @code
 * $form['swiper_formatter'] = [
 *   '#type' => 'swiper_formatter_settings',
 *   '#title' => t('Swiper formatter settings'),
 *   '#default_value' => (array) $values,
 * ];
 * @endcode
 *
 * @FormElement("swiper_formatter_settings")
 */
class SwiperFormatterSettings extends FormElementBase {

  /**
   * Field types that can be a source for slide caption.
   *
   * It does combine and include for field formatter settings form
   * as well as in Views' settings form (naming).
   *
   * @var array
   */
  public const CAPTION_TYPES = [
    'basic_string',
    'string',
    'string_long',
    'text',
    'text_default',
    'text_long',
    'text_with_summary',
  ];

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#input' => TRUE,
      '#theme_wrapper' => 'fieldset',
      '#process' => [
        [self::class, 'process'],
        [self::class, 'processCaption'],
        [self::class, 'processImage'],
        [self::class, 'processDialog'],
      ],
      '#prefix' => '<div id="swiper-dialog-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['data-disable-refocus' => 'true'],
    ];
  }

  /**
   * Process this element.
   *
   * @param array $element
   *   This element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param array $complete_form
   *   Possibly a complete form, including parent one.
   *
   * @return array
   *   This element, processed and with basic elements included.
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {

    $default_values = $element['#default_value'] ?? [];

    // Bailout, no default_value array set.
    if (empty($default_values)) {
      return $element;
    }

    $template = $default_values['template'] ?? NULL;
    $swiper_access = $default_values['swiper_access'] ?? FALSE;

    $type = $default_values['type'] ?? NULL;
    if ($type != 'views') {
      $element['swiper_divider'] = [
        '#markup' => (string) t('<p><strong><em><hr />Swiper configuration</em></strong></p>'),
        '#weight' => -7,
      ];
    }

    $element['template'] = [
      '#title' => t('Swiper template'),
      '#type' => 'select',
      '#default_value' => $template,
      '#required' => TRUE,
      '#options' => SwiperFormatter::getSwiperTemplates() ?: [],
      '#description' => t('Choose one of the available Swiper templates'),
      '#weight' => -6,
    ];

    $params = [];
    $manage_url = $template ? Url::fromRoute('entity.swiper_formatter.edit_form', [
      'swiper_formatter' => $template,
    ], $params) : Url::fromRoute('entity.swiper_formatter.collection', [], $params);

    $element['links'] = [
      '#theme' => 'links',
      '#links' => [
        [
          'title' => t('Create new option set'),
          'url' => Url::fromRoute('entity.swiper_formatter.add_form', [], $params),
        ],
        [
          'title' => t('Manage options'),
          'url' => $manage_url,
        ],
      ],
      '#access' => (bool) $swiper_access,
      '#weight' => -5,
    ];

    return $element;
  }

  /**
   * Render a caption element.
   *
   * @param array $element
   *   This element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param array $complete_form
   *   Possibly a complete form, including parent one.
   *
   * @return array
   *   This element, processed and with caption element included.
   */
  public static function processCaption(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $default_values = $element['#default_value'] ?? [];

    // Bailout, no default_value array set.
    if (empty($default_values)) {
      return $element;
    }

    // Field's name and type.
    $type = $default_values['type'] ?? NULL;
    $name = $default_values['name'] ?? NULL;

    // Bailout, no basic field data.
    if (!$type || !$name) {
      return $element;
    }

    $caption_options = [];
    $action_fields = [];
    $entity_fields = $default_values['caption']['entity_fields'] ?? [];
    if (!empty($entity_fields)) {
      static::defaultCaption($entity_fields, $name, $caption_options, $action_fields);
    }
    static::renderCaption($element, $type, $default_values, $caption_options);
    return $element;
  }

  /**
   * Render additional field for image formatters.
   *
   * @param array $element
   *   This element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param array $complete_form
   *   Possibly a complete form, including parent one.
   *
   * @return array
   *   This element, processed and with a caption element included.
   */
  public static function processImage(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $default_values = $element['#default_value'] ?? [];

    // Bailout, no default_value array set.
    if (empty($default_values)) {
      return $element;
    }

    // Field's type.
    $type = $default_values['type'] ?? NULL;

    // Bailout, this is not an image field.
    if ($type != 'image' && $type != 'views') {
      return $element;
    }

    // A custom link on slide.
    if (isset($element['image_link'])) {
      $element['image_link']['#options']['custom'] = t('Custom');
      $element['custom_link'] = [
        '#type' => 'textfield',
        '#title' => t('Custom link'),
        '#default_value' => $default_values['custom_link'],
        '#description' => t('Enter any custom link here. It will be the same for all slides, unless you override in the twig template, for instance placing part of the string here as some kind of tokens.'),
        '#states' => [
          'visible' => [
            '[data-drupal-selector="edit-fields-field-test-swiper-settings-edit-form-settings-image-link"]' => ['value' => 'custom'],
          ],
        ],
      ];

      // Provide some tokens for a custom link.
      static::tokenElement('token_custom_link', $default_values, $element);
    }

    // Take care of the caption.
    static::imageCaption($default_values, $element);
    return $element;
  }

  /**
   * Render additional field for image formatters.
   *
   * @param array $element
   *   This element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param array $complete_form
   *   Possibly a complete form, including parent one.
   *
   * @return array
   *   This element, processed and with a caption element included.
   */
  public static function processDialog(array &$element, FormStateInterface $form_state, array &$complete_form): array {

    $default_values = $element['#default_value'] ?? [];

    // Bailout, no default_value array set,
    // or this is not a dialog type formatter.
    if (empty($default_values) || !isset($default_values['dialog_view_mode'])) {
      return $element;
    }

    $element['dialog_divider'] = [
      '#markup' => t('<p><hr /><strong><em>Dialog configuration</em></strong></p>'),
    ];

    $dialog_target = NULL;
    $trigger = $form_state->getTriggeringElement();
    if (is_array($trigger)) {
      $dialog_target = $form_state->getValue($trigger['#parents']);
    }
    if (!$dialog_target) {
      $dialog_target = $default_values['dialog_target'] ?? NULL;
    }
    if ($dialog_target) {
      $element['dialog_target'] = [
        '#title' => t('Dialog Target'),
        '#type' => 'radios',
        '#default_value' => $default_values['dialog_target'] ?? NULL,
        '#options' => [
          'entity' => t('This entity'),
          'referenced_entity' => t('Referenced entity'),
        ],
        '#description' => t('Choose which entity to popup in Dialog.'),
        '#ajax' => [
          'callback' => [static::class, 'ajaxDialogTarget'],
          'wrapper' => 'swiper-dialog-wrapper',
        ],
      ];
    }
    $description_url = Url::fromRoute('entity.entity_view_mode.collection', [], [
      'attributes' => [
        'target' => '_blank',
      ],
    ]);
    $description_link = Link::fromTextAndUrl(t('Configure view modes'), $description_url);
    $dialog_view_mode_options = $dialog_target == 'entity' || is_null($dialog_target) ? $default_values['dialog_view_mode_options'] : $default_values['dialog_view_mode_referenced_options'];
    $element['dialog_view_mode'] = [
      '#title' => t('View mode for Dialog target'),
      '#type' => 'select',
      '#default_value' => $default_values['dialog_view_mode'],
      '#options' => $dialog_view_mode_options ?: [],
      '#description' => $description_link->toRenderable() + [
        '#access' => $default_values['dialog_view_mode_access'],
      ],
      '#value_callback' => [static::class, 'dialogViewMode'],
    ];
    $element['dialog_view_item'] = [
      '#title' => t('Dialog content'),
      '#type' => 'select',
      '#default_value' => $default_values['dialog_view_item'] ?? NULL,
      '#options' => [
        'entity' => t('Rendered entity'),
        'field' => t('Rendered field'),
        'field_item' => t('Rendered single field item'),
      ],
      '#description' => t('Popup the whole entity view (that includes having swiper in modal if set so on display mode) or just rendered field, or just a current slide single field item as per view mode set above.'),
    ];
    $element['dialog_type'] = [
      '#title' => t('Dialog type'),
      '#type' => 'select',
      '#default_value' => $default_values['dialog_type'] ?? NULL,
      '#options' => [
        'modal' => t('Modal'),
        'dialog' => t('Dialog'),
        'dialog.off_canvas' => t('Dialog Off canvas'),
        'dialog.off_canvas_top' => t('Dialog Off canvas top'),
        // @todo Check on these.
        // 'wide' => $this->t('Wide'),
        // 'extra_wide' => $this->t('Extra wide'),
      ],
      '#description' => t('Choose dialog type.'),
    ];

    $element['dialog_title'] = [
      '#type' => 'textfield',
      '#title' => t('Dialog title'),
      '#description' => t('If you want to completely hide the dialog title bar add css class for <code>ui-dialog-titlebar</code> element below, e.q. <code>visually-hidden</code>'),
      '#default_value' => $default_values['dialog_title'] ?? NULL,
      '#states' => [
        'visible' => [
          '[data-title-hide]' => ['checked' => FALSE],
        ],
      ],
    ];

    $element['dialog_title_hide'] = [
      '#type' => 'checkbox',
      '#title' => t('Hide title'),
      '#description' => t('This wil hide title bar no matter if there is title string or not. Probably a good option for photos browsing.'),
      '#default_value' => $default_values['dialog_title_hide'],
      '#attributes' => [
        'data-title-hide' => TRUE,
      ],
    ];

    // Provide some tokens for dialog title.
    static::tokenElement('token_title', $default_values, $element);

    $dimensions_description = 'For pixels enter only numeric value, otherwise use CSS property value like <code>85vw</code> or <code>100%</code> etc.';
    $dimensions_params = [
      '@dimensions_description' => Markup::create($dimensions_description),
    ];
    $element['dialog_width'] = [
      '#type' => 'textfield',
      '#title' => t('Dialog width'),
      '#description' => t('Add CSS value for dialog width. @dimensions_description', $dimensions_params),
      '#default_value' => $default_values['dialog_width'] ?? NULL,
    ];
    $element['dialog_height'] = [
      '#type' => 'textfield',
      '#title' => t('Dialog height'),
      '#description' => t('Add CSS value for dialog height. @dimensions_description', $dimensions_params),
      '#default_value' => $default_values['dialog_height'] ?? NULL,
    ];
    $element['dialog_autoresize'] = [
      '#type' => 'checkbox',
      '#title' => t('Auto resize'),
      '#description' => t('Disable to have dialog remain with fixed dimensions on window resize / orientation change.'),
      '#default_value' => $default_values['dialog_autoresize'] ?? NULL,
    ];

    foreach (SwiperDialogInterface::DIALOG_CLASSES as $ui_class => $add_classes) {
      $element[$ui_class] = [
        '#type' => 'textfield',
        '#title' => t('Add classes to <code>.@element</code> element', [
          '@element' => $ui_class,
        ]),
        '#description' => t('Separate multiple classes with spaces and do not include leading dot.'),
        '#default_value' => $default_values[$ui_class] ?? NULL,
      ];
    }
    return $element;
  }

  /**
   * Ajax callback for Dialog target entity radios.
   *
   * @param array $element
   *   This element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A current form state object.
   *
   * @return array
   *   This form.
   */
  public static function ajaxDialogTarget(array $element, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger['#array_parents'], 0, -2);
    return NestedArray::getValue($element, $parents);
  }

  /**
   * Get value for view mode, this changes with Dialog type selections.
   *
   * @param array $element
   *   This element.
   * @param null|bool $input
   *   If form is changed. Views returns null here.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A current form state object.
   *
   * @return string
   *   A default value for a dialog view mode element.
   */
  public static function dialogViewMode(array $element, NULL|bool $input, FormStateInterface $form_state): string {
    $trigger = $form_state->getTriggeringElement();
    if (is_array($trigger) && str_contains($trigger['#name'], '[dialog_target]')) {
      $dialog_view_mode_parents = array_slice($trigger['#parents'], 0, -1);
      $dialog_view_mode_parents[] = 'dialog_view_mode';
      $dialog_view_mode = $form_state->getValue($dialog_view_mode_parents) ?? '';
      if (!$dialog_view_mode) {
        $dialog_view_mode = $element['#default_value'];
      }
      if (!in_array($dialog_view_mode, $element['#options'])) {
        $dialog_view_mode = 'default';
      }
    }
    else {
      $dialog_view_mode = $element['#default_value'];
    }
    return $dialog_view_mode;
  }

  /**
   * Define caption element.
   *
   * @param array $element
   *   This element.
   * @param string $formatter_type
   *   A type of formatter, image, entity, views, etc.
   * @param array $default_values
   *   Element's default values array.
   * @param array $caption_options
   *   Caption field options.
   */
  protected static function renderCaption(array &$element, string $formatter_type, array $default_values, array $caption_options = []): void {
    if (!empty($caption_options) || $formatter_type == 'image') {
      $description = 'Set field to show up as a slide caption. Note that selected field must be multiple and follow deltas of slides field as well.';
      $description .= $formatter_type == 'views' ? " This field won't show in the render result (within slide) but only as a caption.<br />" : " In the case of a field other than image's alt and title, the caption will show even if the chosen field itself is disabled for this Display. This way, some fields can serve exclusively as a caption.<br />";
      $default_value = $default_values['caption']['value'] ?? NULL;
      $element['caption'] = [
        '#title' => t('Caption source field'),
        '#type' => 'select',
        '#options' => $caption_options,
        '#empty_option' => t('@none', ['@none' => '- None -']),
        '#default_value' => $default_value,
        '#weight' => -4,
        '#description' => t('@description', [
          '@description' => Markup::create($description),
        ]),
      ];
    }
  }

  /**
   * Define #options for a caption form element.
   *
   * @param array $entity_fields
   *   Associative array of fields, storage, and definition.
   * @param string $name
   *   Machine name of the caption field source.
   * @param array $caption_options
   *   Associative array ready for #options of a caption field source element.
   * @param array $action_fields
   *   An array of "actions", providing some extra links to user.
   */
  protected static function defaultCaption(array $entity_fields, string $name, array &$caption_options, array &$action_fields = []): void {
    foreach ($entity_fields as $field) {
      if (in_array($field['storage']->getType(), static::CAPTION_TYPES) && $field['storage']->getName() != $name) {
        /** @var \Drupal\field\Entity\FieldStorageConfig $field['storage'] */
        if ($field['storage']->getCardinality() != 1) {
          $caption_options[$field['storage']->getName()] = t('@label', ['@label' => $field['title']]);
        }
      }
    }
  }

  /**
   * Define image-specific #options for a caption form element.
   *
   * @param array $default_values
   *   Element's default values array.
   * @param array $element
   *   This element.
   */
  protected static function imageCaption(array $default_values, array &$element): void {

    $action_fields = [];
    if (!isset($element['caption'])) {
      $caption_options = [];
      $type = $default_values['type'] ?? NULL;
      static::renderCaption($element, $type, $default_values, $caption_options);
    }

    if (isset($default_values['title_field'])) {
      if (!$default_values['title_field']) {
        // User action required on the image title.
        $action_fields[] = 'title';
      }
      else {
        $element['caption']['#options']['title'] = t('Image Title field');
      }
    }
    if (isset($default_values['alt_field'])) {
      if (!$default_values['alt_field']) {
        // User action required on the image title.
        $action_fields[] = 'alt';
      }
      else {
        $element['caption']['#options']['alt'] = t('Image Alt field');
      }
    }
    $description = $element['caption']['#description'] ?? '';
    static::captionDescription($element, $default_values, (string) Markup::create($description), $action_fields);

  }

  /**
   * Generate some useful info and links regarding captions.
   *
   * @param array $element
   *   This element.
   * @param array $default_values
   *   Element's default values array.
   * @param string $caption_description
   *   Existing description text for caption field.
   * @param array $action_fields
   *   Actions fields, in case of image that represents alt and title.
   */
  protected static function captionDescription(array &$element, array $default_values, string $caption_description = '', array $action_fields = []): void {

    // If the image field doesn't have all the suitable caption sources,
    // inform the user about it.
    if (!empty($action_fields)) {
      $field_edit_url = $default_values['caption']['field_edit_url'] ?? NULL;
      $caption_links = '';
      if ($field_edit_url) {
        $caption_links .= t('You need to <a href="@url">enable the @action_field field</a> for this field to be able to use it as a caption.', [
          '@url' => $field_edit_url,
          '@action_field' => implode(' and/or ', $action_fields),
        ]);
      }
      else {
        // Just use plain text if we can't build the field edit link.
        $caption_links .= t('Enable the @action_field field.', [
          '@action_field' => implode(' and/or ', $action_fields),
        ]);
      }
      if (isset($element['caption'])) {
        $element['caption']['#description'] .= Markup::create($caption_links);
      }
    }
  }

  /**
   * Define Token element.
   *
   * @param string $key
   *   The key for form element.
   * @param array $default_values
   *   Element's default values array.
   * @param array $element
   *   This element.
   */
  protected static function tokenElement(string $key, array $default_values, array &$element): void {
    $entity_type = $default_values['entity_type'] ?? NULL;
    $referenced_entity_type = $default_values['referenced_entity_type'] ?? NULL;
    if ($entity_type) {
      $element[$key] = [
        '#theme' => 'token_tree_link',
        '#global_types' => TRUE,
        '#token_types' => [
          'entity' => $entity_type,
          'referenced_entity' => $referenced_entity_type,
        ],
      ];
    }
  }

}
