/**
 * @file
 * Init instances of Swiper on any page.
 */

/*global Drupal, once, Swiper*/
/*eslint no-undef: "error"*/
(function (Drupal, once) {
  'use strict';
  // Define active index, needed for Swiper in Dialog.
  let activeIndex = 0;

  /**
   * Drupal.behaviors implementation for Swiper formatter.
   *
   * Register and initialize all Swiper instances on the page.
   */
  Drupal.behaviors.swiperFormatter = {
    attach: function (context, settings) {
      const self = this;
      const swiper_formatter_settings = settings.swiper_formatter || null;

      if (swiper_formatter_settings && typeof swiper_formatter_settings.swipers !== 'undefined') {
        const swiper = typeof Swiper !== 'undefined' ? Swiper : (window.SwiperFormatter ?? null);
        let swipers = {};
        once('swiperFormatterInit', '.swiper-container', context).forEach(
          function (swiperContainer) {
            if (swiperContainer.id) {
              const swiperSettings = swiper_formatter_settings.swipers[swiperContainer.id];
              if (typeof swiperSettings === 'object' && swiper) {
                if (swiperSettings.pagination.type === 'progressbar') {
                  swiperContainer.classList.add('progressbar');
                }

                // Initialize Swiper now.
                swipers[swiperContainer.id] = new swiper('#' + swiperContainer.id, swiperSettings);
                if (swipers[swiperContainer.id]) {
                  // A special care for dynamic and/or clickable bullets.
                  swipers[swiperContainer.id].on('breakpoint', (swiperEvent, breakpointParams) => {
                    self.breakpointPagination(swiperEvent, breakpointParams);
                  });

                  // This is a Swiper in the Dialog.
                  if (context.classList && context.classList.contains('swiper-formatter-dialog')) {
                    swipers[swiperContainer.id].slideTo(activeIndex);
                  }
                  // Initial Swiper on the page, log current slide index.
                  else {
                    swipers[swiperContainer.id].on('slideChange', (swiperMain) => {
                      activeIndex = swiperMain.activeIndex;
                    });
                    const slides = swiperContainer.querySelectorAll(
                      '.swiper-slide a[data-dialog-type]'
                    );
                    if (slides.length > 1) {
                      slides.forEach((slide, index) => {
                        slide.addEventListener('click', () => {
                          activeIndex = index;
                        });
                      });
                    }
                  }

                  // A custom links (anywhere on the page) that trigger swiper slides.
                  const triggers = context.querySelectorAll('.swiper-trigger');
                  if (triggers) {
                    self.registerTriggers(swipers[swiperContainer.id], Array.from(triggers));
                  }
                }

                // Add swipers site-wide via drupalSettings.
                settings.swipers = swipers;
              }
            }
          }
        );
      }
    },

    /**
     * Handle breakpoint pagination classes on window resize.
     *
     * @param {Object} swiperEvent
     *  Current Swiper "_beforeBreakpoint" event object.
     * @param {Object} breakpointParams
     *  An object containing properties of current set breakpoint.
     */
    breakpointPagination: function (swiperEvent, breakpointParams) {
      if (breakpointParams.pagination && breakpointParams.pagination.enabled) {
        const paginationWrapper = swiperEvent.pagination.el;
        if (paginationWrapper) {
          const hasBullets = paginationWrapper.classList.contains('swiper-pagination-bullets');
          const dynamicBullets =
            hasBullets && paginationWrapper.classList.contains('swiper-pagination-bullets-dynamic');
          const clickableBullets =
            hasBullets && paginationWrapper.classList.contains('swiper-pagination-clickable');
          if (breakpointParams.pagination.type !== 'bullets') {
            if (hasBullets) {
              paginationWrapper.classList.remove('swiper-pagination-bullets');
            }
            if (dynamicBullets) {
              paginationWrapper.classList.remove('swiper-pagination-bullets-dynamic');
            }
            if (clickableBullets) {
              paginationWrapper.classList.remove('swiper-pagination-clickable');
            }
            // Take care of style="width": calculated value for dynamic bullets.
            if (paginationWrapper.getAttribute('style')) {
              let styles = paginationWrapper.getAttribute('style').split(';');

              styles = styles.filter((style) => {
                return style.indexOf('width:') < 0;
              });

              if (styles.length && styles[0]) {
                paginationWrapper.setAttribute('style', styles.join(';'));
              } else {
                paginationWrapper.removeAttribute('style');
              }
            }
          } else {
            paginationWrapper.classList.add('swiper-pagination-bullets');
            if (breakpointParams.pagination.dynamicBullets) {
              paginationWrapper.classList.add('swiper-pagination-bullets-dynamic');
            }
            if (breakpointParams.pagination.clickable) {
              paginationWrapper.classList.add('swiper-pagination-clickable');
            }
          }
        }
      }
    },

    /**
     * Run sliding from anywhere, with some markup attributes defined.
     *
     * @param {Object} swiper
     *  Current Swiper object.
     * @param {Array} triggers
     *  Array with trigger elements/objects.
     * @code
     *  <ul>
     *    <li><a class="swiper-trigger" data-index="2" href="#">Go to slide 2</a></li>
     *    <li><a class="swiper-trigger is-active" data-index="4" href="#">Go to slide 4</a></li>
     *  </ul>
     * @endcode
     */
    registerTriggers: function (swiper, triggers) {
      triggers.forEach(function (trigger) {
        trigger.addEventListener('click', (e) => {
          const target = e.currentTarget || e.target;
          // Take care of siblings' active class.
          if (target.parentNode.siblings().length) {
            target.parentNode.siblings().forEach((i, sibling) => {
              sibling.querySelector('.swiper-trigger').classList.remove('active');
            });
          }
          target.classList.add('active');
          const index = target.getAttribute('data-index')
            ? parseInt(target.getAttribute('data-index')) - 1
            : 0;
          swiper.slideTo(index);
          return false;
        });
      });
    }
  };
})(Drupal, once);
