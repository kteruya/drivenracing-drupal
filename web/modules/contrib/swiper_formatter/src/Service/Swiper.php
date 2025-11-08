<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Service;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\swiper_formatter\Entity\SwiperFormatter;
use Drupal\swiper_formatter\SwiperFormatterInterface;
use Drupal\token\Token;

/**
 * Swiper base service.
 */
class Swiper implements SwiperInterface {

  use StringTranslationTrait;

  /**
   * Swiper formatter entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  public EntityStorageInterface $swiperFormatter;

  /**
   * Image style storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  public EntityStorageInterface $imageStyleStorage;

  /**
   * Constructs this base class.
   */
  public function __construct(
    public EntityFieldManagerInterface $entityFieldManager,
    public EntityTypeManagerInterface $entityTypeManager,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected AccountInterface $currentUser,
    protected Token $token,
    protected RedirectDestinationInterface $destination,
    protected MessengerInterface $messenger,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    try {
      $this->swiperFormatter = $this->entityTypeManager->getStorage('swiper_formatter');
      $this->imageStyleStorage = $this->entityTypeManager->getStorage('image_style');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->loggerFactory->get('Swiper formatter')->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplay(FieldableEntityInterface $entity, string $view_mode = 'default'): EntityViewDisplayInterface {
    return $this->entityDisplayRepository->getViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $view_mode);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions(FieldDefinitionInterface $field_definition): array {
    return $this->entityFieldManager->getFieldDefinitions($field_definition->getTargetEntityTypeId(), $field_definition->getTargetBundle()) ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModeOptions(string $target_type, string $target_bundle): array {
    return $this->entityDisplayRepository->getViewModeOptionsByBundle($target_type, $target_bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getImageStyle(string $image_style): EntityInterface|NULL {
    return $this->imageStyleStorage->load($image_style) ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSwiper(string $swiper_id): EntityInterface|NULL {
    return $this->swiperFormatter->load($swiper_id) ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function validateTemplates(): void {
    $options = SwiperFormatter::getSwiperTemplates();
    if (empty($options)) {
      if ($this->currentUser->hasPermission('administer swiper')) {
        $warning = $this->t('There is no Swiper templates created yet, create one first <a target="_blank" href=":url">here</a>.', [
          ':url' => '/admin/config/content/swiper_formatter/add',
        ]);
      }
      else {
        $warning = $this->t('There is no Swiper templates created yet, requires "administer swiper" permission.');
      }
      $this->messenger->addWarning($warning);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processSettings(FieldDefinitionInterface $field_definition, array $settings): array {
    $element = [];
    $entity_type = $field_definition->getTargetEntityTypeId();
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $field_definition->getTargetBundle());
    $entity_fields = [];
    foreach ($fields as $field_name => $field) {
      $entity_fields[$field_name] = [
        'title' => $field->getLabel(),
        'storage' => $field->getFieldStorageDefinition(),
      ];
    }

    if (!empty($settings['template'])) {
      if ($swiper_entity = $this->swiperFormatter->load($settings['template'])) {
        /** @var \Drupal\swiper_formatter\SwiperFormatterInterface $swiper_entity */
        $settings += $swiper_entity->get('swiper_options');
      }
    }

    $element += [
      '#type' => 'swiper_formatter_settings',
      '#title' => $this->t('Swiper formatter settings'),
      '#default_value' => [
        'type' => $field_definition->getFieldStorageDefinition()->getType(),
        'name' => $field_definition->getFieldStorageDefinition()->getName(),
        'template' => $settings['template'],
        'settings' => $settings,
        'custom_link' => $settings['custom_link'] ?? NULL,
        'caption' => [
          'value' => $settings['caption'] ?? NULL,
          'entity_fields' => $entity_fields,
          'field_edit_url' => $settings['caption_field_edit_url'] ?? NULL,
        ],
        'entity_type' => $entity_type,
        'swiper_access' => $this->currentUser->hasPermission('administer swiper'),
        // Images specific.
        'title_field' => $field_definition->getSetting('title_field') ?? NULL,
        'alt_field' => $field_definition->getSetting('alt_field') ?? NULL,
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function processElements(FieldDefinitionInterface $field_definition, FieldableEntityInterface $entity, array $settings, array $output): array {
    $elements = [
      'settings' => $settings,
      'output' => $output,
    ];

    // Bail out if no elements to render.
    if (empty($output)) {
      return $elements;
    }

    $template = $settings['template'] ?? NULL;
    if (!$template) {
      $message = $this->t("<em>Swiper formatter is set for <strong>@field</strong></em> field but no Swiper template is set on field's display settings. Falling back to default view.", [
        '@field' => $field_definition->getLabel(),
      ]);
      $this->messenger->addWarning($message);
      return $elements;
    }

    $settings['field_type'] = $field_definition->getFieldStorageDefinition()->getType();
    $settings['field_name'] = $field_definition->getFieldStorageDefinition()->getName();
    if ($swiper_entity = $this->getSwiper($template)) {
      /** @var \Drupal\swiper_formatter\SwiperFormatterInterface $swiper_entity */
      $settings += $swiper_entity->get('swiper_options');
      $id = $this->elementId($entity, $field_definition);
      $settings['id'] = $id;
      $elements['settings'] = $settings;
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function renderSwiper(FieldableEntityInterface $entity, array $output, array $settings, array $theme_functions = []): array {
    // Do not proceed with an empty output.
    if (empty($output)) {
      return [];
    }

    $id = $settings['id'] ?? $this->elementId($entity, $entity->get($settings['field_name'])->getFieldDefinition());

    // Breakpoints check.
    $settings['has_breakpoint_navigation'] = FALSE;
    $settings['has_breakpoint_pagination'] = FALSE;
    $settings['has_breakpoint_slides_per_view'] = 1;
    $settings['has_breakpoint_slides_per_group'] = 1;

    if (!empty($settings['breakpoints'])) {
      $this->prepareBreakpoints($settings);
    }

    $content_attributes = [
      'class' => [
        'swiper-wrapper',
      ],
    ];
    $navigation_attributes = $this->prepareNavigation($id, $settings);
    $pagination_attributes = $this->preparePagination($id, $settings);
    $scrollbar_attributes = $this->prepareScrollbar($id, $settings);
    $this->prepareGrid($settings);

    // Handle automatic slides per view.
    $settings['slidesPerView'] = !empty($settings['slidesPerView']) ? $settings['slidesPerView'] : 'auto';

    // Render slides now.
    foreach ($output as &$item) {
      $item = $this->renderSwiperSlide($entity, $settings, $item);
    }

    // Allow other modules to alter settings.
    $this->moduleHandler->alter('swiper_formatter_settings', $id, $settings, $output);

    // Set settings to send to js.
    $drupal_settings['swiper_formatter']['swipers'][$id] = $settings;
    $library = [
      'swiper_formatter/' . $settings['source'],
      'swiper_formatter/swiper_formatter',
    ];
    $dialog = $settings['dialog_type'] ?? NULL;
    if ($dialog) {
      $library[] = 'swiper_formatter/dialog';
    }

    $swiper = [
      '#theme' => !empty($theme_functions) ? $theme_functions : 'swiper_formatter',
      '#id' => $id,
      '#object' => $entity,
      '#content' => $output,
      '#settings' => $settings,
      '#attributes' => [
        'id' => $id,
        'class' => [
          'swiper-container',
          Html::getClass($settings['template']),
        ],
      ],
      '#content_attributes' => new Attribute($content_attributes),
      '#navigation_attributes' => $navigation_attributes,
      '#pagination_attributes' => $pagination_attributes,
      '#scrollbar_attributes' => $scrollbar_attributes,
      '#attached' => [
        'drupalSettings' => $drupal_settings,
        'library' => $library,
      ],
    ];

    return $swiper;
  }

  /**
   * {@inheritdoc}
   */
  public function elementId(FieldableEntityInterface $entity, ?FieldDefinitionInterface $field_definition = NULL, ?string $view_mode = NULL, ?string $delta = NULL): string {
    $id = $entity->getEntityTypeId() . '-' . $entity->bundle() . '-' . $entity->id();
    if ($field_definition) {
      $id .= '-' . $field_definition->getName();
    }
    // Has no view mode nor delta param.
    if ($view_mode) {
      $id .= '-' . $view_mode;
    }
    if ($delta) {
      $id .= '-' . $delta;
    }
    return Html::getUniqueId($id);
  }

  /**
   * {@inheritdoc}
   */
  public function renderSwiperSlide(FieldableEntityInterface $entity, array $settings, array $item): array {
    $slide = [
      '#theme' => 'swiper_formatter_slide',
      '#slide' => $item,
      '#object' => $entity,
      '#settings' => $settings,
      '#attributes' => [
        'class' => [
          'swiper-slide',
          'swiper-slide-' . Html::cleanCssIdentifier($settings['field_type']),
        ],
      ],
    ];
    // Check on caption.
    if (!empty($item['#caption'])) {
      $caption_value = is_array($item['#caption']) && isset($item['#caption']['value']) ? $item['#caption']['value'] : $item['#caption'];
      $slide['#caption'] = [
        '#markup' => Markup::create($caption_value),
      ];
    }

    // Take care of some caching,
    // i.e., update if swiper template changed.
    $cache_tags = [
      'config:swiper_formatter.swiper_formatter.' . $settings['template'],
    ];

    if (isset($item['#cache'])) {
      $slide['#cache']['tags'] = Cache::mergeTags($item['#cache']['tags'], $cache_tags);
    }
    else {
      $slide['#cache']['tags'] = $cache_tags;
    }

    // Add an extra cache key to avoid conflict with
    // an original view mode cached version as a workaround to the current
    // implementation until the main logic is reworked.
    // Just in case it's not an entity, we add a check.
    $keys = $item['#cache']['keys'] ?? [];
    if (!empty($keys)) {
      $slide['#cache']['keys'] = $keys;
    }
    if (in_array('entity_view', $keys)) {
      $slide['#cache']['keys'][] = 'swiper-slide';
    }
    return $slide;
  }

  /**
   * {@inheritdoc}
   */
  public function getCaption(array &$item, ?string $caption_field = NULL, ?FieldableEntityInterface $entity = NULL, int $delta = 0): void {
    $item['#caption'] = match ($caption_field) {
      'title' => isset($item['#item']) && $item['#item']->title ? $item['#item']->title : NULL,
      'alt' => isset($item['#item']) && $item['#item']->alt ? $item['#item']->alt : NULL,
      default => $entity && $entity->hasField($caption_field) ? ($entity->get($caption_field)->get($delta) && $entity->get($caption_field)->get($delta)->getValue() ? $entity->get($caption_field)->get($delta)->getValue() : NULL) : NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function tokenValue(string $markup, FieldableEntityInterface $entity): string {
    $token_data[$entity->getEntityTypeId()] = $entity;
    return $this->token->replace($markup, $token_data, ['clear' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDestination(FieldDefinitionInterface $field_definition): array {

    $destination = substr($this->destination->get(), 0, (int) strpos($this->destination->get(), '?'));
    /* $entity_type = $field_definition->getTargetEntityTypeId(); */
    /* $route_name = 'entity.field_config.' . $entity_type . '_field_edit_form'; */
    /* $target_bundle = $field_definition->getTargetBundle() ?? NULL; */
    /* $field_name = $field_definition->getFieldStorageDefinition()->getName(); */
    /* $route_params = ['field_config' => $entity_type . '.' . $target_bundle . '.' . $field_name,];*/
    // A "famous bug" with paragraph vs. paragraphs entity type.
    /* $type = $entity_type == 'paragraph' ? 'paragraphs' : $entity_type; */
    /* $route_params[$type . '_type'] = $target_bundle; */
    /* $uri_options = ['fragment' => 'edit-settings-title-field','query' => ['destination' => $destination],]; */
    // Currently Views and Taxonomy fails, not having a parameter.
    /* $data = []; */
    /* if ($entity_type && $target_bundle) {$data['caption_field_edit_url'] = Url::fromRoute($route_name, $route_params, $uri_options)->toString();} */
    return [
      'destination' => $destination,
    ];
  }

  /**
   * Prepare navigation attributes, assign prev/next elements to swiper config.
   *
   * @param string $id
   *   Swiper unique id.
   * @param array $settings
   *   Referenced formatter settings array.
   *
   * @return \Drupal\Core\Template\Attribute[]
   *   Array with attributes for prev/next buttons.
   */
  protected function prepareNavigation(string $id, array &$settings) {
    // We need to do these two separately, to
    // preserve classes for default Swiper styling/CSS.
    $settings['navigation']['prevEl'] = '.' . $id . '-prev';
    $settings['navigation']['nextEl'] = '.' . $id . '-next';

    $prev_attributes = [
      'class' => [
        'swiper-button-prev',
        $id . '-prev',
      ],
    ];

    $next_attributes = [
      'class' => [
        'swiper-button-next',
        $id . '-next',
      ],
    ];

    return [
      'prev' => new Attribute($prev_attributes),
      'next' => new Attribute($next_attributes),
    ];
  }

  /**
   * Prepare pagination attributes and assign an element to the swiper config.
   *
   * @param string $id
   *   Swiper unique id.
   * @param array $settings
   *   Referenced formatter settings array.
   *
   * @return \Drupal\Core\Template\Attribute
   *   Array with attributes for prev/next buttons.
   */
  protected function preparePagination(string $id, array &$settings): Attribute {
    $settings['pagination']['el'] = '.pagination-' . $id;
    $pagination_attributes = [
      'class' => [
        'swiper-pagination',
        'pagination-' . $id,
      ],
    ];
    return new Attribute($pagination_attributes);
  }

  /**
   * Prepare pagination attributes and assign an element to the swiper config.
   *
   * @param string $id
   *   Swiper unique id.
   * @param array $settings
   *   Referenced formatter settings array.
   *
   * @return \Drupal\Core\Template\Attribute
   *   Array with attributes for prev/next buttons.
   */
  protected function prepareScrollbar(string $id, array &$settings): Attribute {
    $settings['scrollbar']['el'] = '.scrollbar-' . $id;
    $scrollbar_attributes = [
      'class' => [
        'swiper-scrollbar',
        'scrollbar-' . $id,
      ],
    ];
    return new Attribute($scrollbar_attributes);
  }

  /**
   * Prepare breakpoints for the swiper config.
   *
   * @param array $settings
   *   Referenced formatter settings array.
   */
  protected function prepareBreakpoints(array &$settings): void {
    $breakpoints = [];
    foreach ($settings['breakpoints'] as $breakpoint) {
      if (isset($breakpoint['swiper_template'])) {
        /** @var \Drupal\swiper_formatter\Entity\SwiperFormatter $breakpoint_template */
        $breakpoint_template = $this->getSwiper($breakpoint['swiper_template']);
        if ($breakpoint_template instanceof SwiperFormatterInterface && !empty($breakpoint['breakpoint'])) {
          $breakpoints[$breakpoint['breakpoint']] = $breakpoint_template->swiper_options;
        }
      }
    }
    if (!empty($breakpoints)) {
      $settings['breakpoints'] = [];
      $include = ['slidesPerView', 'slidesPerGroup', 'spaceBetween', 'navigation', 'pagination'];
      foreach ($breakpoints as $key => $breakpoint) {
        foreach ($breakpoint as $k => $b) {
          if (in_array($k, $include)) {
            $settings['breakpoints'][$key][$k] = $b;
            if ($k == 'navigation') {
              $settings['has_breakpoint_navigation'] = TRUE;
            }
            if ($k == 'pagination') {
              $settings['has_breakpoint_pagination'] = TRUE;
            }
            if ($k == 'slidesPerView') {
              $settings['has_breakpoint_slides_per_view'] = $b;
            }
            if ($k == 'slidesPerGroup') {
              $settings['has_breakpoint_slides_per_group'] = $b;
            }
            $settings['has_breakpoint_slides_per_group'] = FALSE;
          }
        }
      }
    }
    else {
      unset($settings['breakpoints']);
    }
  }

  /**
   * Prepares grid for the swiper config.
   *
   * @param array $settings
   *   Referenced formatter settings array.
   */
  protected function prepareGrid(array &$settings): void {
    if (isset($settings['grid'])) {
      if (empty($settings['grid']['enabled'])) {
        unset($settings['grid']);
      }
      else {
        unset($settings['grid']['enabled']);
      }
    }
  }

  /**
   * Check if entity is "routable", e.g., node vs. paragraph.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Content entity object.
   *
   * @return bool|string
   *   The path for entity link, or FALSE if it doesn't exist.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function isRouted(FieldableEntityInterface $entity): bool|string {
    $entity_type_definition = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
    return $entity_type_definition->getLinkTemplate('canonical');
  }

}
