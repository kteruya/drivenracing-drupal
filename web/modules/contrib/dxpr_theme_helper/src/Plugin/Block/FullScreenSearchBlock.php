<?php

namespace Drupal\dxpr_theme_helper\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Full Screen search block.
 *
 * @Block(
 *   id = "full_screen_search",
 *   admin_label = @Translation("DXPR Theme Full Screen Search"),
 *   category = @Translation("Forms")
 * )
 */
class FullScreenSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new FullScreenSearchBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'search_provider' => 'core',
      'search_url' => '/search',
      'search_parameter' => 'search_api_fulltext',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $moduleHandler = $this->moduleHandler;
    $config = $this->getConfiguration();

    $search_api_available = $moduleHandler->moduleExists('search_api_block');

    $form['search_provider'] = [
      '#type' => 'radios',
      '#title' => 'Search provider',
      '#options' => [
        'core' => $this->t('Core Search'),
        'search_api' => $this->t('Search API Block') . (!$search_api_available ? ' (' . $this->t('Not installed') . ')' : ''),
      ],
      '#default_value' => $config['search_provider'] ?? 'core',
      '#disabled' => !$search_api_available ? ['search_api'] : [],
    ];

    $form['search_api_settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'search-api-settings'],
      '#states' => [
        'visible' => [
          ':input[name="settings[search_provider]"]' => ['value' => 'search_api'],
        ],
      ],
    ];

    $form['search_api_settings']['search_url'] = [
      '#type' => 'textfield',
      '#title' => 'Search URL path',
      '#description' => $this->t('Enter the URL path for your Search API View (e.g., search)'),
      '#default_value' => !empty($config['search_url']) ? $config['search_url'] : 'search',
      '#field_prefix' => '/',
      '#size' => 20,
    ];

    $form['search_api_settings']['search_parameter'] = [
      '#type' => 'textfield',
      '#title' => 'Search parameter name',
      '#description' => $this->t('Enter the parameter name used by your Search API View (e.g., search_api_fulltext, keys)'),
      '#default_value' => !empty($config['search_parameter']) ? $config['search_parameter'] : 'search_api_fulltext',
      '#size' => 25,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $search_provider = $form_state->getValue('search_provider');
    $search_url = $form_state->getValues()['search_api_settings']['search_url'];
    $search_parameter = $form_state->getValues()['search_api_settings']['search_parameter'];

    $this->setConfigurationValue('search_provider', $search_provider);
    $this->setConfigurationValue('search_url', $search_url);
    $this->setConfigurationValue('search_parameter', $search_parameter);
  }

  /**
   * Helper method to create the search submit button.
   *
   * @return array
   *   The search submit button render array.
   */
  private function createSearchButton() {
    return [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => ['btn', 'btn-lg', 'btn-primary', 'full-screen-search-submit'],
        'aria-label' => $this->t('Submit search'),
      ],
      '#weight' => 100,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $provider = $config['search_provider'] ?? 'core';
    $search_url = !empty($config['search_url']) ? $config['search_url'] : 'search';
    $search_parameter = !empty($config['search_parameter']) ? $config['search_parameter'] : 'search_api_fulltext';

    // If Search API provider is selected and available, render its search form.
    if ($provider === 'search_api' && $this->moduleHandler->moduleExists('search_api_block')) {
      try {
        $search_form = $this->formBuilder->getForm(
          'Drupal\\search_api_block\\Form\\SearchApiForm',
        // Dynamic URL.
          '/' . $search_url,
        // action_method.
          'get',
        // Dynamic parameter.
          $search_parameter,
        // input_placeholder.
          'Search...',
        // submit_value.
          'Search',
        // input_label.
          'Search',
        // input_label_visibility.
          'invisible',
        // pass_get_params.
          FALSE
        );

        // Apply same styling as Core Search form.
        if (isset($search_form[$search_parameter])) {
          $search_form[$search_parameter]['#prefix'] = '<div class="full-screen-search-form-input">';
          $search_form[$search_parameter]['#suffix'] = '</div>';
          $search_form[$search_parameter]['#title_display'] = 'before';
          $search_form[$search_parameter]['#title'] = $this->t('Search');
          $search_form[$search_parameter]['#attributes']['placeholder'] = $this->t('Type and press Enter to search');
          $search_form[$search_parameter]['#attributes']['autocomplete'] = 'off';
          $search_form[$search_parameter]['#attributes']['class'][] = 'search-query';
          $search_form[$search_parameter]['#attributes']['aria-label'] = $this->t('Search query');
          $search_form[$search_parameter]['#attributes']['aria-describedby'] = 'search-instructions';
          unset($search_form[$search_parameter]['#field_suffix']);
          $search_form[$search_parameter]['#input_group_button'] = FALSE;
        }

        // Remove original submit button and add custom Search button.
        unset($search_form['actions']);
        $search_form['search_submit'] = $this->createSearchButton();

        $search_form['#attributes']['class'][] = 'invisible';
        $search_form['#attributes']['class'][] = 'full-screen-search-form';
        $search_form['#attributes']['role'] = 'search';
        $search_form['#attributes']['aria-label'] = $this->t('Site search form');
      }
      catch (\Exception $e) {
        // Fallback to Core Search if Search API form fails.
        $search_form = $this->formBuilder->getForm('Drupal\search\Form\SearchBlockForm');
        $search_form['keys']['#prefix'] = '<div class="full-screen-search-form-input">';
        $search_form['keys']['#suffix'] = '</div>';
        $search_form['keys']['#title_display'] = 'before';
        $search_form['keys']['#title'] = $this->t('Search');
        $search_form['keys']['#attributes']['placeholder'] = $this->t('Type and press Enter to search');
        $search_form['keys']['#attributes']['autocomplete'] = 'off';
        $search_form['keys']['#attributes']['class'][] = 'search-query';
        $search_form['keys']['#attributes']['aria-label'] = $this->t('Search query');
        $search_form['keys']['#attributes']['aria-describedby'] = 'search-instructions';
        unset($search_form['keys']['#field_suffix']);
        $search_form['keys']['#input_group_button'] = FALSE;

        // Remove original submit button and add custom Search button.
        unset($search_form['actions']);
        $search_form['search_submit'] = $this->createSearchButton();

        $search_form['#attributes']['class'][] = 'invisible';
        $search_form['#attributes']['class'][] = 'full-screen-search-form';
        $search_form['#attributes']['role'] = 'search';
        $search_form['#attributes']['aria-label'] = $this->t('Site search form');
      }
    }
    // Fallback to Core Search.
    elseif ($this->moduleHandler->moduleExists('search')) {
      $search_form = $this->formBuilder->getForm('Drupal\search\Form\SearchBlockForm');
      $search_form['keys']['#prefix'] = '<div class="full-screen-search-form-input">';
      $search_form['keys']['#suffix'] = '</div>';
      $search_form['keys']['#title_display'] = 'before';
      $search_form['keys']['#title'] = $this->t('Type and Press "enter" to Search');
      $search_form['keys']['#attributes']['placeholder'] = FALSE;
      $search_form['keys']['#attributes']['autocomplete'] = 'off';
      $search_form['keys']['#attributes']['class'][] = 'search-query';
      $search_form['keys']['#attributes']['aria-label'] = $this->t('Search query');
      $search_form['keys']['#attributes']['aria-describedby'] = 'search-instructions';
      // Unset submit button, we search when pressing return.
      unset($search_form['keys']['#field_suffix']);
      // Remove .input-group wrapper.
      $search_form['keys']['#input_group_button'] = FALSE;

      // Remove original submit button and add custom Search button.
      unset($search_form['actions']);
      $search_form['search_submit'] = $this->createSearchButton();

      $search_form['#attributes']['class'][] = 'invisible';
      $search_form['#attributes']['class'][] = 'full-screen-search-form';
    }
    else {
      $this->messenger()->addError($this->t('Search module is not installed. Please install Search module to use the DXPR Theme Full Screen Search Block'));
      return [];
    }

    // Search screen toggle button.
    $content['full_screen_search_button'] = [
      '#type' => 'button',
      '#button_type' => 'button',
      '#id' => 'full_screen_search',
      '#value' => $this->t('Open search'),
      '#attributes' => [
        'class' => ['btn-link', 'full-screen-search-button', 'icon'],
        'aria-label' => $this->t('Open full screen search'),
        'aria-expanded' => 'false',
        'aria-controls' => 'full-screen-search-container',
        'title' => $this->t('Open search'),
      ],
    ];
    // Add screen reader instructions.
    $content['search_instructions'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Type your search query and press Enter to search'),
      '#attributes' => [
        'id' => 'search-instructions',
        'class' => ['visually-hidden'],
      ],
    ];

    // Add live region for search state announcements.
    $content['search_status'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '',
      '#attributes' => [
        'id' => 'search-status',
        'aria-live' => 'polite',
        'aria-atomic' => 'true',
        'class' => ['visually-hidden'],
      ],
    ];

    // Add container wrapper with proper ARIA attributes.
    $content['search_form'] = $search_form;
    $content['#attributes']['id'] = 'full-screen-search-container';
    $content['#attributes']['aria-hidden'] = 'true';

    return $content;
  }

}
