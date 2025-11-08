/**
 * @file
 * The build process always expects an index.js file.
 * Assign Swiper class to Drupal object in order to
 * secure presence in Drupal behaviors.
 *
 * @todo Divide into Swiper separate module's loading - advanced.
 */
import Swiper from 'swiper/bundle';
import 'swiper/css/bundle';
// A hack?
window.SwiperFormatter = Swiper;
