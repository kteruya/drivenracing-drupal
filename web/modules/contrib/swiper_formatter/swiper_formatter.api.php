<?php

/**
 * @file
 * Hooks related to Swiper Formatter module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter a swiper formatter settings.
 *
 * @param string $id
 *   Swiper formatter id.
 * @param array $settings
 *   The swiper formatter settings.
 * @param array $output
 *   Content - to becomes slides - render-able array.
 *
 * @see Drupal\swiper_formatter\Service\Swiper::renderSwiper()
 */
function hook_swiper_formatter_settings_alter(string $id, array &$settings, array $output): void {
  // Alter swiper formatter settings array.
  $settings['slidesPerView'] = 'auto';

  // Or more advanced - alter or define Grid.
  if ($settings['template'] == 'my_template_id') {
    $number_of_slides = count($output);
    $columns = 2;
    $settings['grid'] = [
      'rows' => round($number_of_slides / $columns),
      'fill' => 'row',
    ];
    $settings['spaceBetween'] = 20;
    $settings['slidesPerView'] = $columns;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
