<?php

namespace Drupal\driven_motorcycles;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin list builder for Driven Motorcycle entities with pager/sort/search.
 */
class DrivenMotorcycleListBuilder extends EntityListBuilder {

  /** @var \Drupal\Core\Entity\EntityStorageInterface */
  protected $storage;

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->storage = $container->get('entity_type.manager')->getStorage($entity_type->id());
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds() {
    // Build the base query.
    $query = $this->getStorage()->getQuery()->accessCheck(TRUE);

    // Search filter via ?q= string (label/make/model).
    $input = \Drupal::request()->query->all();
    $search = isset($input['q']) ? trim((string) $input['q']) : '';
    if ($search !== '') {
      $or = $query->orConditionGroup()
        ->condition('title', "%$search%", 'LIKE')
        ->condition('make', "%$search%", 'LIKE')
        ->condition('model', "%$search%", 'LIKE');
      $query->condition($or);
    }

    // Sorting.
    $header = $this->buildHeader();
    $this->entityTypeId = $this->entityType->id();
    // Provide default sort if none chosen.
    if (empty($input['sort']) && empty($input['order'])) {
      $query->sort('year', 'DESC')->sort('make')->sort('model');
    }
    else {
      // Utilize table sort if present.
      $query = $this->getEntityQueryWithTableSort($query, $header);
    }

    // Pager: 50 per page.
    $query->pager(50);

    return $query->execute();
  }

  /**
   * Applies tablesort params (if present) to the entity query.
   */
  protected function getEntityQueryWithTableSort($query, array $header) {
    $request = \Drupal::request();
    $sort = $request->query->get('sort');
    $order = strtoupper($request->query->get('order') ?? 'ASC');
    $allowed = ['title', 'year', 'make', 'model'];
    if ($sort && in_array($sort, $allowed, TRUE)) {
      $query->sort($sort, $order === 'DESC' ? 'DESC' : 'ASC');
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Add a simple search form above the table.
    $build['filter_form'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['driven-moto-filter']],
      'form' => [
        '#type' => 'form',
        '#attributes' => ['method' => 'get'],
        'q' => [
          '#type' => 'search',
          '#title' => $this->t('Search'),
          '#title_display' => 'invisible',
          '#default_value' => \Drupal::request()->query->get('q') ?? '',
          '#attributes' => ['placeholder' => $this->t('Search label, make, model')],
        ],
        'actions' => [
          '#type' => 'actions',
          'submit' => [
            '#type' => 'submit',
            '#value' => $this->t('Filter'),
            '#attributes' => ['class' => ['button', 'button--primary']],
            '#submit' => [[get_class($this), 'submitFilterForm']],
          ],
          'reset' => [
            '#type' => 'submit',
            '#value' => $this->t('Reset'),
            '#submit' => [[get_class($this), 'resetFilterForm']],
          ],
        ],
      ],
    ];

    $build += parent::render();
    return $build;
  }

  public static function submitFilterForm(array &$form, FormStateInterface $form_state) {
    $q = (string) $form_state->getValue('q');
    $url = Url::fromRoute('<current>', [], ['query' => ['q' => $q]]);
    $form_state->setResponse(\Drupal::service('redirect.destination')->redirect($url->toString()));
  }

  public static function resetFilterForm(array &$form, FormStateInterface $form_state) {
    $url = Url::fromRoute('<current>');
    $form_state->setResponse(\Drupal::service('redirect.destination')->redirect($url->toString()));
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = [
      'data' => $this->t('Label'),
      'field' => 'title',
      'specifier' => 'title',
    ];
    $header['year']  = [
      'data' => $this->t('Year'),
      'field' => 'year',
      'specifier' => 'year',
    ];
    $header['make']  = [
      'data' => $this->t('Make'),
      'field' => 'make',
      'specifier' => 'make',
    ];
    $header['model'] = [
      'data' => $this->t('Model'),
      'field' => 'model',
      'specifier' => 'model',
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\driven_motorcycles\Entity\DrivenMotorcycle $entity */
    $row['title'] = Link::fromTextAndUrl($entity->label(), Url::fromRoute('entity.driven_motorcycle.edit_form', ['driven_motorcycle' => $entity->id()]));
    $row['year']  = $entity->get('year')->value;
    $row['make']  = $entity->get('make')->value;
    $row['model'] = $entity->get('model')->value;
    return $row + parent::buildRow($entity);
  }

}
