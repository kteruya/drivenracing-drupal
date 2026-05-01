/**
 * Project: Driven Racing – Drupal 11 tweaks
 * File: js/home-slider.js
 * Version: 2025-11-25 v1.3.0
 * Change log:
 * - 2025-11-25 10:05  Reworked behavior to use .view-driven-home-slider
 *                     and .view-content as the sliding track; JS now
 *                     injects its own arrows and dots.
 * - 2025-11-25 09:45  Adjusted to use .view-content inside legacy
 *                     .slider-track.
 * - 2025-11-23 20:40  Initial simple JS slider behavior.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.drivenHomeSlider = {
    attach: function (context) {
      // Work with the Views markup directly: Driven Home Slider view.
      var sliders = once('driven-home-slider', '.view.view-driven-home-slider', context);

      sliders.forEach(function (slider) {
        var track = slider.querySelector('.view-content');
        if (!track) {
          return;
        }

        var slides = track.querySelectorAll('.slide');
        if (slides.length === 0) {
          return;
        }

        var currentIndex = 0;
        var total = slides.length;
        var autoTimer = null;
        var autoDelay = 6000; // 6 seconds

        // Build arrow buttons container.
        var prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'slider-arrow prev';
        prevBtn.innerHTML = '&#10094;';

        var nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'slider-arrow next';
        nextBtn.innerHTML = '&#10095;';

        slider.appendChild(prevBtn);
        slider.appendChild(nextBtn);

        // Build dots container.
        var dotsContainer = document.createElement('div');
        dotsContainer.className = 'slider-dots';
        slider.appendChild(dotsContainer);

        var dots = [];

        for (var i = 0; i < total; i++) {
          (function (i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.addEventListener('click', function () {
              goTo(i);
              startAuto();
            });
            dotsContainer.appendChild(dot);
            dots.push(dot);
          })(i);
        }

        function updateDots() {
          dots.forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === currentIndex);
          });
        }

        function goTo(index) {
          if (index < 0) {
            index = total - 1;
          } else if (index >= total) {
            index = 0;
          }
          currentIndex = index;
          var offset = -index * 100;
          track.style.transform = 'translateX(' + offset + '%)';
          updateDots();
        }

        function next() {
          goTo(currentIndex + 1);
        }

        function prev() {
          goTo(currentIndex - 1);
        }

        function startAuto() {
          stopAuto();
          autoTimer = setInterval(next, autoDelay);
        }

        function stopAuto() {
          if (autoTimer) {
            clearInterval(autoTimer);
            autoTimer = null;
          }
        }

        // Wire arrows.
        prevBtn.addEventListener('click', function () {
          prev();
          startAuto();
        });

        nextBtn.addEventListener('click', function () {
          next();
          startAuto();
        });

        // Pause on hover.
        slider.addEventListener('mouseenter', stopAuto);
        slider.addEventListener('mouseleave', startAuto);

        // Initialize.
        goTo(0);
        startAuto();
      });
    }
  };

})(Drupal, once);
