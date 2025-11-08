<?php

declare(strict_types=1);

namespace Drupal\swiper_formatter\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\Element;
use Drupal\swiper_formatter\SwiperFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Swiper entity form.
 *
 * @property \Drupal\swiper_formatter\Entity\SwiperFormatter $entity
 */
class SwiperFormatterForm extends EntityForm {

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  /**
   * Swiper formatter entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  public EntityStorageInterface $swiperStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->typedConfigManager = $container->get('config.typed');
    try {
      $instance->swiperStorage = $container->get('entity_type.manager')
        ->getStorage('swiper_formatter');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $instance->messenger()->addError($e->getMessage());
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEntity(): void {
    parent::prepareEntity();
    $entity = $this->entity;
    if ($entity->isNew()) {
      /** @var \Drupal\swiper_formatter\SwiperFormatterInterface $defaultSwiper */
      $defaultSwiper = $this->swiperStorage->load('default');
      $entity->setSwiperOptions($defaultSwiper->getSwiperOptions());
      $entity->set('status', $defaultSwiper->get('status'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $entity = $this->entity;
    $default_values = $entity->getSwiperOptions();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#description' => $this->t('Label for the swiper.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
      '#description' => $this->t('Description for this Swiper template.'),
      '#states' => [
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->get('breakpoint') ? TRUE : $entity->get('status'),
      '#description' => $this->t("Whether Swiper initially enabled. When Swiper is disabled, it will hide all navigation elements and won't respond to any events and interactions."),
      '#states' => [
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Swiper library source'),
      '#description' => $this->t('Under some conditions, usage of some themes and/or other libraries, some issues may occur. Set to local library to try to resolve, but please first make sure to have mandatory for <em>Local</em> <a target="_blank" href="https://cdn.jsdelivr.net/npm/swiper@11.1.10/swiper-bundle.js">swiper-bundle.js</a> and <a target="_blank" href="https://cdn.jsdelivr.net/npm/swiper@11.1.10/swiper-bundle.css">swiper-bundle.css</a> and/or mandatory for <em>Local minified</em> <a target="_blank" href="https://cdn.jsdelivr.net/npm/swiper@11.1.10/swiper-bundle.min.js">swiper-bundle.min.js</a> and <a target="_blank" href="https://cdn.jsdelivr.net/npm/swiper@11.1.10/swiper-bundle.min.css">swiper-bundle.min.css</a> <strong>downloaded and placed in <strong>/libraries/swiper/</strong> folder.</strong>'),
      '#options' => [
        'package' => $this->t('Package'),
        'remote' => $this->t('Remote (cdn)'),
        'local' => $this->t('Local'),
        'local_minified' => $this->t('Local minified'),
      ],
      '#default_value' => $default_values['source'],
      '#states' => [
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options'] = [
      '#type' => 'container',
      '#title' => $this->t('Swiper options'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['breakpoints'] = [
      '#type' => 'details',
      '#title' => $this->t('Breakpoints'),
      '#description' => $this->t('Reference other Swiper templates as breakpoints, applying breakpoint properties as set there. Only supported options for breakpoints will work. See <a href="https://swiperjs.com/swiper-api#param-breakpoints">Swiper API breakpoints</a>'),
      '#tree' => TRUE,
      '#open' => !$entity->get('breakpoint'),
      '#id' => 'swiper-breakpoints-wrapper',
    ];

    $form['swiper_options']['breakpoints']['breakpoint'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This template is a Breakpoint template'),
      '#default_value' => $entity->get('breakpoint'),
      '#description' => $this->t("Enable to exclude this template from the list of formatters on the various places site-wide. Do not forget to reference it on the main template's form.<br/><strong>Warning:</strong> This will disable and then upon form submit null the options which are un-applicable as breakpoint's property."),
      '#ajax' => [
        'callback' => [get_class($this), 'formAjaxCallback'],
        'wrapper' => $form['id'],
      ],
    ];

    $form['swiper_options']['breakpoints']['breakpointsBase'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Breakpoints base'),
      '#default_value' => $default_values['breakpointsBase'],
      '#description' => $this->t('Base for breakpoints (beta). Can be window or container. <a target="blank_" href="https://swiperjs.com/swiper-api#param-breakpointsBase">See here</a>.'),
      '#size' => '20',
    ];

    $form['swiper_options']['breakpoints'] += $this->generateElements($form_state, 'swiper-breakpoints-wrapper', 'breakpoints', 'breakpoints', $default_values);

    $form['swiper_options']['wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Wrapper options'),
      '#description' => $this->t('For the extensive list of options for this class see <a href="https://swiperjs.com/swiper-api#parameters">Swiper.js API</a>'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $form['swiper_options']['wrapper']['autoHeight'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set Auto height'),
      '#default_value' => $default_values['autoHeight'],
      '#description' => $this->t('This is <strong>recommended</strong> for Vertical direction as well as for lazy loading (see below).'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['wrapper']['width'] = [
      '#title' => $this->t('Fixed width'),
      '#type' => 'number',
      '#description' => $this->t('Integer value in pixels - recommended for vertical swipers.'),
      '#default_value' => $default_values['width'],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['wrapper']['height'] = [
      '#title' => $this->t('Fixed height'),
      '#type' => 'number',
      '#description' => $this->t('Integer value in pixels - recommended for vertical swipers.'),
      '#default_value' => $default_values['height'],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['wrapper']['observer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Observer'),
      '#default_value' => $default_values['observer'],
      '#description' => $this->t('Enable Mutation Observer on Swiper and its elements. In this case Swiper will be updated (reinitialised) each time if you change its style (like hide/show) or modify its child elements (like adding/removing slides). May be handy for programmatic slides transition i.e. form other trigger element on the page for instance.'),
    ];

    $form['swiper_options']['wrapper']['updateOnWindowResize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update on window resize'),
      '#default_value' => $default_values['updateOnWindowResize'],
      '#description' => $this->t('Swiper will recalculate slides position on window resize (orientationchange).'),
    ];

    $form['swiper_options']['wrapper']['resizeObserver'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use ResizeObserver'),
      '#default_value' => $default_values['resizeObserver'],
      '#description' => $this->t('When enabled it will use ResizeObserver (if supported by browser) on swiper container to detect container resize (instead of watching for window resize).'),
    ];

    $form['swiper_options']['slides'] = [
      '#type' => 'details',
      '#title' => $this->t('Slides options'),
      '#description' => $this->t('For the extensive list of options for this class see <a href="https://swiperjs.com/swiper-api#parameters">Swiper.js API</a>'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $form['swiper_options']['slides']['direction'] = [
      '#type' => 'radios',
      '#title' => $this->t('Direction'),
      '#description' => $this->t('Select sliding direction.'),
      '#options' => [
        'horizontal' => $this->t('Horizontal'),
        'vertical' => $this->t('Vertical'),
      ],
      '#default_value' => $default_values['direction'],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['effect'] = [
      '#type' => 'radios',
      '#title' => $this->t('Swipe effect'),
      '#default_value' => $default_values['effect'],
      '#description' => $this->t('Choose one of a few Swiper effects. See <a target="_blank" href="https://swiperjs.com/swiper-api#param-effect">here</a>.<br /><em>Creative</em> effect seems unstable at the moment, therefore disabled.'),
      '#process' => [
        ['\Drupal\Core\Render\Element\Radios', 'processRadios'],
        [get_class($this), 'processEffect'],
      ],

      '#options' => [
        'slide' => $this->t('Slide'),
        'fade' => $this->t('Fade'),
        'cube' => $this->t('Cube'),
        'coverflow' => $this->t('Coverflow'),
        'flip' => $this->t('Flip'),
        'creative' => $this->t('Creative'),
        'cards' => $this->t('Cards'),
      ],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['loop'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Loop'),
      '#default_value' => $default_values['loop'],
      '#description' => $this->t('Enable continuous loop mode. See some restrictions <a href="https://swiperjs.com/swiper-api#param-loop" target="_blank">here</a>.'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['rewind'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rewind'),
      '#default_value' => $default_values['rewind'],
      '#description' => $this->t('Enable rewind, click on next nav button on last slide loads first slide, click on prev button on first slide rewinds to the last one. <strong>Should not be used together with loop mode.</strong>'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['centeredSlides'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Centered slides'),
      '#default_value' => $default_values['centeredSlides'],
      '#description' => $this->t('Active slide will be centered, not always on the left side.'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['speed'] = [
      '#title' => $this->t('Transition speed'),
      '#type' => 'number',
      '#description' => $this->t('Duration of transition between slides (in ms).'),
      '#default_value' => $default_values['speed'],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['slidesPerView'] = [
      '#title' => $this->t('Number of slides per view'),
      '#type' => 'number',
      '#step' => '0.1',
      '#description' => $this->t("Decimal value - slides visible at the same time on slider's container. Enter 0 to set the value to 'auto'."),
      '#default_value' => $default_values['slidesPerView'],
    ];

    $form['swiper_options']['slides']['spaceBetween'] = [
      '#title' => $this->t('Space between slides'),
      '#type' => 'number',
      '#description' => $this->t("Integer value - Distance between slides in px. Useful to use with slidesPerView > 1."),
      '#default_value' => $default_values['spaceBetween'],
    ];

    $form['swiper_options']['slides']['slidesPerGroup'] = [
      '#title' => $this->t('Number of slides per group'),
      '#type' => 'number',
      '#description' => $this->t("Integer value - Set numbers of slides to define and enable group sliding. Useful to use with slidesPerView > 1."),
      '#default_value' => $default_values['slidesPerGroup'],
    ];

    $form['swiper_options']['slides']['loopedSlides'] = [
      '#title' => $this->t('Number of looped slides'),
      '#type' => 'number',
      '#description' => $this->t('Integer value - Number of slides looped at once. Probably must be set for the above config to work.'),
      '#default_value' => $default_values['loopedSlides'],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['noSwipingSelector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No swiping selectors'),
      '#maxlength' => 255,
      '#default_value' => $default_values['noSwipingSelector'],
      '#description' => $this->t('A comma separated list of css selectors for which swiping behaviour is disabled, when those are in focus; i.e. <em>.no-swipe, button, input</em>'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['mousewheel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mousewheel'),
      '#default_value' => $default_values['mousewheel'] ?? FALSE,
      '#description' => $this->t('Enable navigation through slides using mouse wheel.'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['grabCursor'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Grab cursor type'),
      '#default_value' => $default_values['grabCursor'],
      '#description' => $this->t('This is basically CSS <em>cursor: grab</em>, may be useful on desktops. Does not work with CSS mode.'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['slides']['cssMode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('CSS mode'),
      '#default_value' => $default_values['cssMode'],
      '#description' => $this->t('When enabled it will use modern CSS Scroll Snap API. It doesn\'t support all of Swiper\'s features, but potentially should bring a much better performance in simple configurations. Please make sure to check <a href="https://swiperjs.com/swiper-api#param-cssMode" target="_blank">here</a>'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['grid'] = [
      '#type' => 'details',
      '#title' => $this->t('Grid settings'),
      '#open' => TRUE,
      '#description' => $this->t('Swiper Grid feature, see <a target="_blank" href="https://swiperjs.com/swiper-api#param-grid">Swiper.js | Grid</a>.'),
    ];

    $form['swiper_options']['grid']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable grid'),
      '#default_value' => $default_values['grid']['enabled'],
    ];

    $form['swiper_options']['grid']['rows'] = [
      '#type' => 'number',
      '#title' => $this->t('Rows'),
      '#default_value' => $default_values['grid']['rows'],
      '#min' => 0,
      '#description' => $this->t('Set numbers of slides per column, for multirow layout.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[grid][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['grid']['fill'] = [
      '#type' => 'select',
      '#title' => $this->t('Fill'),
      '#options' => [
        'row' => $this->t('Row'),
        'column' => $this->t('Column'),
      ],
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $default_values['grid']['fill'],
      '#description' => $this->t('Defines how slides should fill rows, by column or by row.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[grid][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['navigation'] = [
      '#type' => 'details',
      '#title' => $this->t('Navigation settings'),
      '#open' => TRUE,
      '#description' => $this->t('Swiper Navigation module, see <a target"_blank" href="https://swiperjs.com/swiper-api#navigation">Swiper.js | Navigation</a>.'),
    ];

    $form['swiper_options']['navigation']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable navigation'),
      '#default_value' => $default_values['navigation']['enabled'],
      '#description' => $this->t("Show Swiper's prev/next buttons."),
    ];

    $form['swiper_options']['navigation']['hideOnClick'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide on click'),
      '#default_value' => $default_values['navigation']['hideOnClick'],
      '#description' => $this->t("Toggle navigation buttons visibility after click on Swiper's container."),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[navigation][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['autoplay'] = [
      '#type' => 'details',
      '#title' => $this->t('Autoplay settings'),
      '#description' => $this->t('Swiper Autoplay module, see <a target="_blank" href="https://swiperjs.com/swiper-api#autoplay">Swiper.js | Autoplay</a>.'),
      '#open' => TRUE,
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['autoplay']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable autoplay'),
      '#default_value' => $default_values['autoplay']['enabled'],
    ];

    $form['swiper_options']['autoplay']['delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay in ms'),
      '#default_value' => $default_values['autoplay']['delay'],
      '#description' => $this->t('Set amount of milliseconds after which Swiper will automatically swipe to the next slide.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[autoplay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['autoplay']['disableOnInteraction'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable on interaction'),
      '#default_value' => $default_values['autoplay']['disableOnInteraction'],
      '#description' => $this->t('Enable/disable autoplay on user interaction.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[autoplay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['autoplay']['pauseOnMouseEnter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause on mouse enter'),
      '#default_value' => $default_values['autoplay']['pauseOnMouseEnter'],
      '#description' => $this->t('Pause autoplay on mouse enter.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[autoplay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['autoplay']['reverseDirection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse direction'),
      '#default_value' => $default_values['autoplay']['reverseDirection'],
      '#description' => $this->t('Enables autoplay in reverse direction.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[autoplay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['autoplay']['stopOnLastSlide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stop on last slide'),
      '#default_value' => $default_values['autoplay']['stopOnLastSlide'],
      '#description' => $this->t('Stop autoplay when last slide is reached.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[autoplay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['autoplay']['waitForTransition'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wait for transition'),
      '#default_value' => $default_values['autoplay']['waitForTransition'],
      '#description' => $this->t('Waits for transition to continue autoplay.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[autoplay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['pagination'] = [
      '#type' => 'details',
      '#title' => $this->t('Pagination'),
      '#open' => TRUE,
      '#description' => $this->t('Swiper Navigation module, see <a target="_blank" href="https://swiperjs.com/swiper-api#pagination">Swiper.js | Pagination</a>.'),
    ];

    $form['swiper_options']['pagination']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pagination'),
      '#default_value' => $default_values['pagination']['enabled'],
      '#description' => $this->t('Enable this for more options.'),
    ];

    $form['swiper_options']['pagination']['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Pagination type'),
      '#options' => [
        'bullets' => $this->t('Bullets'),
        'progressbar' => $this->t('Progressbar'),
        'fraction' => $this->t('Fraction'),
        'custom<' => $this->t('Custom'),
      ],
      '#default_value' => $default_values['pagination']['type'],
      '#description' => $this->t('Setting to "Custom" obviously requires implementation of <em>renderCustom()</em> callback somewhere in your code. See more about it <a target="_blank" href="https://swiperjs.com/swiper-api#pagination">here</a>.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[pagination][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['pagination']['bullets'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[pagination][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['pagination']['bullets']['dynamicBullets'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Dynamic bullets'),
      '#default_value' => $default_values['pagination']['dynamicBullets'],
      '#description' => $this->t('May be handy and "fancy" with a bigger number of bullets/slides.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[pagination][type]"]' => ['value' => 'bullets'],
        ],
      ],
    ];

    $form['swiper_options']['pagination']['bullets']['clickable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bullets clickable'),
      '#default_value' => $default_values['pagination']['clickable'],
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[pagination][type]"]' => ['value' => 'bullets'],
        ],
      ],
    ];

    $form['swiper_options']['scrollbar'] = [
      '#type' => 'details',
      '#title' => $this->t('Scrollbar'),
      '#open' => TRUE,
      '#description' => $this->t('Swiper Scrollbar module, see <a target="_blank" href="https://swiperjs.com/swiper-api#scrollbar">Swiper.js | Scrollbar</a>.'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['scrollbar']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable scrollbar'),
      '#default_value' => $default_values['scrollbar']['enabled'] ?? FALSE,
    ];

    $scrollbar_enabled_state = [
      'visible' => [
        ':input[name="swiper_options[scrollbar][enabled]"]' => ['checked' => TRUE],
      ],
    ];

    $form['swiper_options']['scrollbar']['draggable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Draggable'),
      '#default_value' => $default_values['scrollbar']['draggable'] ?? FALSE,
      '#states' => $scrollbar_enabled_state,
    ];

    $form['swiper_options']['scrollbar']['dragSize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drag size'),
      '#maxlength' => 255,
      '#default_value' => $default_values['scrollbar']['dragSize'] ?? 'auto',
      '#description' => $this->t('Size of scrollbar draggable element in px or "auto"'),
      '#states' => $scrollbar_enabled_state,
    ];

    $form['swiper_options']['scrollbar']['hide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide scrollbar automatically after user interaction'),
      '#default_value' => $default_values['scrollbar']['hide'] ?? TRUE,
      '#states' => $scrollbar_enabled_state,
    ];

    $form['swiper_options']['scrollbar']['snapOnRelease'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Snap slider position to slides on release'),
      '#default_value' => $default_values['scrollbar']['snapOnRelease'] ?? FALSE,
      '#states' => $scrollbar_enabled_state,
    ];

    $form['swiper_options']['lazy'] = [
      '#type' => 'details',
      '#title' => $this->t('Lazy loading settings'),
      '#open' => TRUE,
      '#description' => $this->t('Swiper Lazy Loading module, see <a target="_blank" href="https://swiperjs.com/swiper-api#lazy-loading">Swiper.js | Lazy Loading</a>.'),
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['swiper_options']['lazy']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable lazy loading'),
      '#default_value' => $default_values['lazy']['enabled'],
      '#description' => $this->t('For images only. Includes Swiper pre-loader animation.'),
    ];

    $form['swiper_options']['lazy']['checkInView'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check in view'),
      '#default_value' => $default_values['lazy']['checkInView'],
      '#description' => $this->t('Enables to check is the Swiper in view before lazy loading images on initial slides.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[lazy][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['lazy']['loadOnTransitionStart'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load on transition start'),
      '#default_value' => $default_values['lazy']['loadOnTransitionStart'],
      '#description' => $this->t('By default, Swiper will load lazy images after transition to this slide, so you may enable this parameter if you need it to start loading of new image in the beginning of transition.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[lazy][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['lazy']['loadPrevNext'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load prev/next'),
      '#default_value' => $default_values['lazy']['loadPrevNext'],
      '#description' => $this->t('Enable lazy loading for the closest slides images (for previous and next slide images).'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[lazy][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['lazy']['loadPrevNextAmount'] = [
      '#type' => 'number',
      '#title' => $this->t('Load prev/next amount'),
      '#default_value' => $default_values['lazy']['loadPrevNextAmount'],
      '#description' => $this->t("Amount of next/prev slides to preload lazy images in. Can't be less than Slides per view."),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[lazy][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['swiper_options']['lazy']['scrollingElement'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scrolling element'),
      '#default_value' => $default_values['lazy']['scrollingElement'],
      '#description' => $this->t('Element to check scrolling on for checkInView. Defaults to window.'),
      '#states' => [
        'visible' => [
          ':input[name="swiper_options[lazy][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    if (!empty($actions['delete']['#access'])) {
      // Remove the delete button if the current entity is the default one.
      $actions['delete']['#access'] = $this->entity->id() != 'default';
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $breakpoints = $form_state->getValue(['breakpoints', 'breakpoints']);
    foreach ($breakpoints as $key => $breakpoint) {
      if (is_numeric($key)) {
        $incomplete_template = !empty($breakpoint['breakpoint']) && empty($breakpoint['swiper_template']);
        $incomplete_breakpoint = empty($breakpoint['breakpoint']) && !empty($breakpoint['swiper_template']);
        if ($incomplete_template || $incomplete_breakpoint) {
          $empty = $this->t('@item cannot be empty.', [
            '@item' => $incomplete_breakpoint ? 'Breakpoint' : 'Swiper template',
          ])->render();
          $name = $incomplete_breakpoint ? 'breakpoint' : 'swiper_template';
          $form_state->setErrorByName('breakpoints][breakpoints][' . $key . '][' . $name, $empty);
        }
        else {
          $has_at = strpos($breakpoint['breakpoint'], '@') > -1 && strpos($breakpoint['breakpoint'], '@') < 1;
          $has_dot = str_contains($breakpoint['breakpoint'], '.');
          $invalid_breakpoint = (!is_numeric($breakpoint['breakpoint']) && (!$has_at || !$has_dot)) || (is_numeric($breakpoint['breakpoint']) && $has_dot && !$has_at);
          if (!empty($breakpoint['breakpoint']) && $invalid_breakpoint) {
            $invalid_value = $this->t('Breakpoint must be integer, or float that starts with "@" character for percentage values.')->render();
            $form_state->setErrorByName('breakpoints][breakpoints][' . $key . '][breakpoint', $invalid_value);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $swiper_options = [];
    foreach ($form_state->getValues() as $key => $values) {
      if (is_array($values)) {
        foreach ($values as $option => $value) {

          // A special handling for to flag template as breakpoint type.
          $breakpoint_element = $form['swiper_options'][$key]['breakpoint'] ?? [];
          if (!empty($breakpoint_element)) {
            $this->elementsHandler($breakpoint_element, $value['breakpoint']);
            $this->entity->set('breakpoint', $value['breakpoint']);
          }

          // A special handling for breakpointsBase value.
          $breakpoint_base_element = $form['swiper_options'][$key]['breakpointsBase'] ?? [];
          if (!empty($breakpoint_base_element)) {
            $this->elementsHandler($breakpoint_base_element, $value['breakpointsBase']);
            // This has to have default value.
            $swiper_options['breakpointsBase'] = $value['breakpointsBase'] ?? 'window';
          }

          if (in_array($option, SwiperFormatterInterface::SWIPER_MODULES)) {

            // A special case for pagination type options, e.g. bullets.
            if ($option == 'pagination' && isset($value['bullets'])) {
              $element = $form[$key][$option]['bullets'] ?? [];
              $value['dynamicBullets'] = $value['bullets']['dynamicBullets'];
              $value['clickable'] = $value['bullets']['clickable'];
              unset($value['bullets']);
            }
            else {
              $element = $form[$key][$option] ?? [];
            }
            if (!empty($element) && !empty($value)) {
              $this->elementsHandler($element, $value);
            }
            $swiper_options[$option] = $value;
          }
          else {

            // A special handling for breakpoints table.
            if ($key == 'breakpoints' && is_array($value)) {

              $element = $form['swiper_options'][$key][$option] ?? [];
              $value = $this->saveSequence($value);

              // Process some special form elements, to
              // have the values fit to Swipers values types.
              if (!empty($element) && !empty($value)) {
                $this->elementsHandler($element, $value);
              }
              $swiper_options[$option] = $value;
            }
            else {

              foreach ($value as $sub_option => $sub_value) {

                // NULL empty strings, or similar.
                // Note that, for instance "width" property
                // makes issue when saved as '' and not null.
                if ($sub_value == '') {
                  $sub_value = NULL;
                }
                // Process some special form elements, in order to
                // have the values fit to Swipers values types.
                $element = $form[$key][$option][$sub_option];
                $element['#swiper_id'] = $form['id']['#value'];
                if (is_array($element) && !empty($sub_value)) {
                  $this->elementsHandler($element, $sub_value);
                }
                $swiper_options[$sub_option] = $sub_value;
              }
            }
          }
        }
      }
      else {
        if (!in_array($key, ['id', 'label', 'enabled', 'description'])) {
          $this->elementsHandler($form[$key], $values);
          $swiper_options[$key] = $values;
        }

        if ($key == 'enabled') {
          $this->entity->set('status', $values);
        }
      }

      $this->entity->setSwiper($swiper_options);
    }

    // Now save entity.
    $saved = $this->entity->save();
    if ($saved) {
      $this->messenger()->addStatus($this->t('Swiper %label saved.', [
        '%label' => $this->entity->label(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Error: Swiper %label was not saved.', [
        '%label' => $this->entity->label(),
      ]));
    }

    // Go back to a page with a collection of Swiper entities.
    $form_state->setRedirect('entity.swiper_formatter.collection');
    return $saved;
  }

  /**
   * These are multiple fields (e.g., table), sequences such as Breakpoints.
   *
   * @param array $values
   *   An array with form state values of "breakpoints" table element.
   *
   * @return array
   *   An array with processed and prepared values, for saving into config.
   */
  protected function saveSequence(array $values): array {

    $value = [];
    $is_sequence = array_filter($values, function ($v, $k) {
      return is_numeric($k) && !empty($v);
    }, ARRAY_FILTER_USE_BOTH);

    // These are multiple fields (e.g. table),
    // sequences such as Breakpoints.
    if (!empty($is_sequence)) {

      foreach ($is_sequence as $index => $sub_value) {
        $keys = [];
        foreach ($sub_value as $k => $v) {
          $keys[] = $k;
          $has_value = $k == 'weight' ? is_numeric($v) : !empty($v);
          if ($has_value) {
            $value[$index][$k] = $v;
          }
        }
        // NULL properties, when table items removed.
        if (empty($value) && !empty($keys)) {
          $value[0] = [];
          foreach ($keys as $sub_key) {
            $value[0][$sub_key] = $sub_key == 'weight' ? 0 : NULL;
          }
        }
      }

      // Sort by weight.
      uasort($value, [SortArray::class, 'sortByWeightElement']);
      $value = array_values($value);
      return $value;
    }
    return $value;
  }

  /**
   * Process "effect" radios.
   *
   * @param array $element
   *   Form "effect" radios element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Form "effect" radios element.
   */
  public static function processEffect(array &$element, FormStateInterface $form_state): array {
    $element['creative']['#disabled'] = TRUE;
    return $element;
  }

  /**
   * Check whether if swiper configuration entity exists.
   *
   * @param string $id
   *   Swiper config entity ID property.
   *
   * @return bool
   *   True if swiper entity exists.
   */
  public function exist(string $id): bool {
    return (bool) $this->swiperStorage->load($id);
  }

  /**
   * Process some special form elements.
   *
   * Place some values up to fit to Swiper settings object structure.
   *
   * @param array $element
   *   Form element.
   * @param mixed $value
   *   Form element's value.
   */
  protected function elementsHandler(array $element, mixed &$value): void {
    if ($element['#type'] == 'checkbox') {
      if ($value === 0) {
        $value = FALSE;
      }
      if ($value === 1) {
        $value = TRUE;
      }
    }
    elseif ($element['#type'] == 'number') {
      if (!empty($value)) {
        // Get the config schema mapping to determine the element's data type.
        $swiper_id = $element['#swiper_id'];
        $config = $this->configFactory()->getEditable('swiper_formatter.swiper_formatter.' . $swiper_id);
        $typedConfigManager = $this->typedConfigManager->getDefinition($config->getName());
        $mapping = $typedConfigManager['mapping'];
        $keys = $element['#parents'];
        $type = $this->resolveType($mapping, $keys);

        if ($type === 'integer') {
          $value = (int) $value;
        }
        elseif ($type === 'float') {
          $value = (float) $value;
        }
      }
    }
    // NULL any other empty values.
    else {
      $value = empty($value) ? NULL : $value;
    }
  }

  /**
   * Recursively resolve the type for a given set of keys.
   *
   * @param array $mapping
   *   The type mapping array.
   * @param array $keys
   *   The keys to resolve.
   *
   * @return mixed
   *   The resolved type, or FALSE if not found.
   */
  protected function resolveType(array $mapping, array $keys): mixed {
    $current = array_shift($keys);

    if (isset($mapping[$current])) {
      if ($mapping[$current]['type'] === 'mapping' && is_array($mapping[$current]['mapping']) && $keys) {
        return $this->resolveType($mapping[$current]['mapping'], $keys);
      }

      return $mapping[$current]['type'] ?: FALSE;
    }

    return FALSE;
  }

  /**
   * Generate form elements.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $id
   *   Unique id for elements parent container.
   * @param string $key
   *   Unique key of a field being manipulated.
   * @param string $container
   *   A parent container.
   * @param array $default_values
   *   An array with default values.
   *
   * @return array
   *   Processed form element.
   */
  protected function generateElements(FormStateInterface $form_state, string $id, string $key, string $container, array $default_values): array {

    $parent_id = Html::getId($id);
    $params = [
      'id' => $parent_id,
      'key' => $key,
      'label' => $this->t('Breakpoints'),
      'group_class' => 'swiper-breakpoints-sort-weight',
    ];

    $elements = [
      '#parents' => [
        $container,
        $key,
      ],
      '#attributes' => [
        'class' => [
          'draggable',
        ],
      ],
    ];

    $elements['breakpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Breakpoint'),
      '#title_display' => 'invisible',
      '#default_value' => NULL,
      '#size' => '20',
      '#attributes' => [
        'class' => ['swiper-breakpoints-breakpoint'],
      ],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['swiper_template'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Swiper template'),
      '#title_display' => 'invisible',
      '#target_type' => 'swiper_formatter',
      '#default_value' => NULL,
      '#attributes' => [
        'class' => [],
      ],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#title_display' => 'invisible',
      '#default_value' => NULL,
      // Classify the weight element for #tabledrag.
      '#attributes' => [
        'class' => [
          $params['group_class'],
        ],
      ],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $complete_form_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;
    return $this->multipleItems($complete_form_state, $key, $elements, $params);
  }

  /**
   * Process multiple items form element.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $key
   *   Unique key of a field being manipulated.
   * @param array $element_child
   *   Element's data pre-defined.
   * @param array $params
   *   An array with additional params/variables.
   *
   * @return array
   *   Processed form element.
   */
  protected function multipleItems(FormStateInterface $form_state, string $key, array $element_child = [], array $params = []): array {

    $default_values = $this->entity->swiper_options;
    $config = !empty($default_values['breakpoints'][0]) && !empty($default_values['breakpoints'][0]['breakpoint']) ? $default_values['breakpoints'] : [
      [
        'breakpoint' => NULL,
        'swiper_template' => NULL,
        'weight' => 0,
      ],
    ];

    // Get current form state's values.
    $values = $form_state->getValues();

    $num_items = $this->multipleState($form_state, $key, $config);
    $default_values = $config;

    // Container for multiple items.
    $id = $params['id'] ?? Html::getId($key);
    $group_class = $params['group_class'] ?? $key . '-sort-weight';
    $parents = [];
    if (isset($element_child['#parents'])) {
      $parents = $element_child['#parents'];
    }
    $element = [
      '#parents' => $parents,
    ];

    $element[$key] = [
      '#type' => 'table',
      // '#caption' => $this->t('Items'),
      '#parents' => $parents,
      '#header' => [
        ['data' => $this->t('Breakpoint')],
        ['data' => $this->t('Swiper template')],
        ['data' => $this->t('Weight')],
      ],
      '#empty' => $this->t('There are no breakpoints.'),
      // TableDrag: Each array value is a list of callback arguments for
      // drupal_add_tabledrag(). The #id of the table is automatically
      // prepended; if there is none, an HTML ID is auto-generated.
      '#prefix' => '<div id="' . $id . '">',
      '#suffix' => '</div>',
      // '#tableselect' => TRUE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $group_class,
        ],
      ],
    ];

    $default_elements = $element_child;
    $label = 'Default';

    for ($delta = 1; $delta <= $num_items; $delta++) {

      $index = $delta - 1;
      $item = NULL;

      if (!empty($config[$key])) {
        $item = !empty($config[$key][$index]) ? $config[$key][$index] : NULL;
      }
      else {
        if (!empty($values[$key])) {
          $item = !empty($values[$key][$index]) ? $values[$key][$index] : NULL;
        }
      }

      // Child item.
      $label = $params['label'] ?? 'Default';
      if (isset($element_child['swiper_template']) && isset($element_child['breakpoint'])) {
        $label = $element_child['#title'] ?? $label;
        $element[$key][$index]['#attributes'] = $default_elements['#attributes'];
        $element[$key][$index]['#parents'] = $default_elements['#parents'];
        $element[$key][$index]['#parents'][] = (string) $index;

        foreach (Element::children($element_child) as $child_key) {
          $element[$key][$index][$child_key] = $default_elements[$child_key];

          if ($child_key == 'weight') {
            $element[$key][$index][$child_key]['#default_value'] = $index;
          }
          else {

            if ($key == 'breakpoints') {

              if ($child_key == 'swiper_template') {
                $swiper_template_id = $default_values[$index][$child_key];
                $breakpoints_value = $swiper_template_id ? $this->swiperStorage->load($swiper_template_id) : NULL;
              }
              else {
                $breakpoints_value = $default_values[$index][$child_key];
              }
              $element[$key][$index][$child_key]['#default_value'] = $breakpoints_value;
            }
            else {
              if (isset($default_values[$index][$child_key])) {
                $element[$key][$index][$child_key]['#default_value'] = $default_values[$index][$child_key];
              }
            }
          }
        }
      }
      // Fallback to default element type - textfield so far.
      else {
        $element[$key][$index] = [
          '#type' => 'textfield',
          '#title' => $this->t('@label label', ['@label' => $label]),
          '#default_value' => $item,
        ];
      }
    }

    // Render common add/remove ajax buttons.
    $params_data = [
      'id' => $id,
      'label' => $label,
    ];

    $this->multipleOps($element, $form_state, $key, $params_data, $num_items);
    return $element;
  }

  /**
   * Append add/remove buttons to multiple fields.
   *
   * @param array $element
   *   Referenced form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $key
   *   Unique key of a field being manipulated.
   * @param array $params
   *   An array with additional params/variables.
   * @param int $num_items
   *   Current number of field instances.
   */
  private function multipleOps(array &$element, FormStateInterface $form_state, string $key, array $params, int $num_items): void {

    $element['actions'] = [
      '#type' => 'actions',
    ];

    $element['actions']['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Breakpoint item'),
      '#limit_validation_errors' => [],
      '#name' => 'op-' . $key,
      '#submit' => [
        [get_class($this), 'addItemSubmit'],
      ],
      '#weight' => 20,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxCallback'],
        'wrapper' => $params['id'],
      ],
      '#attributes' => [
        'class' => [
          'button--small',
          'button--primary',
        ],
      ],
      '#states' => [
        'disabled' => [
          ':input[data-drupal-selector="edit-breakpoints-breakpoints-breakpoint"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['actions']['remove_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove Breakpoint item'),
      '#limit_validation_errors' => [],
      '#name' => 'op-' . $key,
      '#submit' => [
          [get_class($this), 'removeItemSubmit'],
      ],
      '#weight' => 20,
      '#disabled' => !$num_items,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxCallback'],
        'wrapper' => $params['id'],
      ],
      '#attributes' => [
        'class' => [
          'button--small',
          'button--danger',
        ],
      ],
    ];
  }

  /**
   * Add field's id or a complete custom field to config.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $key
   *   A form element's key.
   * @param array $default_values
   *   An array with current values for the field (in config).
   *
   * @return int
   *   A current number of field items within the form.
   */
  private function multipleState(FormStateInterface $form_state, string $key, array $default_values = []): int {

    // Gather the number of items in the form already.
    $num_items = $form_state->get('num_' . $key);
    // We have to ensure that there is at least one widget.
    if ($num_items === NULL) {
      if (!empty($default_values[0]['breakpoint']) && !empty($default_values[0]['swiper_template'])) {
        $num_items = count($default_values);
      }
      else {
        $num_items = 1;
      }
    }

    $form_state->set('num_' . $key, $num_items);
    return $num_items;
  }

  /**
   * Callback for all ajax actions.
   *
   * Returns parent container element for each group.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger['#array_parents'], 0, -2);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Add multiple element Submit callback.
   */
  public static function addItemSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $key = str_replace('op-', '', $trigger['#name']);
    $form_state->set('multiple_keys', $key);
    $num_items = $form_state->get('num_' . $key);
    $delta = $num_items + 1;
    $form_state->set('num_' . $key, $delta);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Remove multiple element Submit callback.
   */
  public static function removeItemSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $key = str_replace('op-', '', $trigger['#name']);
    $form_state->set('multiple_keys', $key);
    $num_items = $form_state->get('num_' . $key);
    $delta = $num_items - 1;
    $form_state->set('num_' . $key, $delta);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Form ajax callback (e.g. Breakpoint checkbox).
   */
  public static function formAjaxCallback(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

}
