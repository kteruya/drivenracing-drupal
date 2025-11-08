<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\swiper_formatter\Service\SwiperDialogInterface;
use Drupal\swiper_formatter\Service\SwiperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Swiper formatter dialog.
 *
 * @phpstan-consistent-constructor
 */
class Dialog extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(protected SwiperInterface $swiperBase) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('swiper_formatter.base')
    );
  }

  /**
   * Set to route and dialog title.
   *
   * @param string|null $title
   *   Any string or translatable markup delivered here for dialog title.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   Entity to which the field is attached.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string|null
   *   Value to set to route and dialog title.
   */
  public function title(?string $title = NULL, ?FieldableEntityInterface $entity = NULL): TranslatableMarkup|string|NULL {
    if (!$title) {
      return $entity?->label();
    }
    return $title;
  }

  /**
   * Builds the response.
   */
  public function dialog(string $entity_type, string $entity_id, string $view_mode = 'default', ?string $field = NULL, ?string $delta = NULL, int $field_item = 0): array {
    try {
      /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $entity_storage */
      $entity_storage = $this->entityTypeManager()->getStorage($entity_type);
      if ($entity = $entity_storage->load($entity_id)) {
        /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
        $this->title(NULL, $entity);
        return $this->dialogContent($entity, $view_mode, $field, $delta, $field_item);
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->getLogger('Swiper formatter')->error($e->getMessage());
    }
    return [];
  }

  /**
   * Prepare a Swiper dialog theme render array.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to which the field is attached.
   * @param string $view_mode
   *   Machine name of view mode for content to be rendered.
   * @param string|null $field
   *   Field machine name.
   * @param string|null $delta
   *   Field item index.
   * @param int $field_item
   *   When one only single field instance is rendered, per provided delta.
   *
   * @return array
   *   Swiper dialog theme array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function dialogContent(FieldableEntityInterface $entity, string $view_mode, ?string $field = NULL, ?string $delta = NULL, int $field_item = 0): array {

    // Collect cache tags and view modes.
    $cache_tags = [];
    $view_mode_storage = $this->entityTypeManager()->getStorage('entity_view_mode');
    if ($view_mode_entity = $view_mode_storage->load($entity->getEntityTypeId() . '.' . $view_mode)) {
      $cache_tags = array_merge($cache_tags, $view_mode_entity->getCacheTags());
    }
    $view_builder = $this->entityTypeManager()->getViewBuilder($entity->getEntityTypeId());
    $view_display = $this->swiperBase->getDisplay($entity, $view_mode);

    if ($field != SwiperDialogInterface::DEFAULT_FIELD && $entity->hasField($field)) {

      $default_settings = [];
      $type = $entity->get($field)->getFieldDefinition()->getType();
      if ($type == 'image') {
        $default_settings['image_style'] = NULL;
      }
      else {
        $default_settings['trim_length'] = 600;
      }
      $field_settings = $view_display->getComponent($field) ? $view_display->getComponent($field)['settings'] : $default_settings;
      if ($field_item) {
        $delta = !is_null($delta) ? $delta : 0;
        /** @var \Drupal\Core\Field\FieldItemInterface $item */
        $item = $entity->get($field)->get($delta);
        $content = $view_builder->viewFieldItem($item, $field_settings);
      }
      else {
        $content = $view_builder->viewField($entity->get($field), $view_mode);
      }
      if ($type == 'image') {
        $content['#image_style'] = $field_settings['image_style'];
      }
    }
    else {
      $content = $view_builder->view($entity, $view_mode);
    }

    $id = $this->swiperBase->elementId($entity, NULL, $view_mode, (string) $delta);
    return [
      '#theme' => 'swiper_dialog',
      '#id' => Html::getUniqueId($id),
      '#entity' => $entity,
      '#content' => $content,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
  }

}
