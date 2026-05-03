/**
 * new-products-carousel.js — Driven Racing NEW PRODUCTS carousel
 * Version: 1.0.0
 *
 * Changelog:
 * 2026-05-02 (v1.0.0) Initial — converts new products grid into 4-up
 *                     carousel. Groups 8 items into 2 slides of 4.
 *                     Matches D7 NEW PRODUCTS carousel behaviour.
 *                     Based on embrace-carousel.js pattern.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.drivenNewProductsCarousel = {
    attach: function (context) {
      var grids = once('new-products-carousel', '.new-products-grid .view-content', context);

      grids.forEach(function (viewContent) {
        var grid = viewContent.querySelector('.views-view-responsive-grid');
        if (!grid) return;

        var items = Array.from(grid.querySelectorAll('.views-view-responsive-grid__item'));
        if (items.length === 0) return;

        var itemsPerSlide = 4;
        var totalSlides = Math.ceil(items.length / itemsPerSlide);
        var currentSlide = 0;
        var autoTimer = null;
        var autoDelay = 6000;

        // Build carousel wrapper
        var carousel = document.createElement('div');
        carousel.className = 'new-products-carousel';

        var track = document.createElement('div');
        track.className = 'new-products-carousel__track';

        // Group items into slides
        for (var s = 0; s < totalSlides; s++) {
          var slide = document.createElement('div');
          slide.className = 'new-products-carousel__slide';
          var slideItems = items.slice(s * itemsPerSlide, (s + 1) * itemsPerSlide);
          slideItems.forEach(function (item) {
            slide.appendChild(item);
          });
          track.appendChild(slide);
        }

        carousel.appendChild(track);

        // Build dots
        var dotsContainer = document.createElement('div');
        dotsContainer.className = 'new-products-carousel__dots';
        var dots = [];

        for (var d = 0; d < totalSlides; d++) {
          (function (d) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.addEventListener('click', function () {
              goTo(d);
              startAuto();
            });
            dotsContainer.appendChild(dot);
            dots.push(dot);
          })(d);
        }

        carousel.appendChild(dotsContainer);

        // Replace grid with carousel
        viewContent.innerHTML = '';
        viewContent.appendChild(carousel);

        function updateDots() {
          dots.forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === currentSlide);
          });
        }

        function goTo(index) {
          if (index < 0) index = totalSlides - 1;
          if (index >= totalSlides) index = 0;
          currentSlide = index;
          track.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
          updateDots();
        }

        function startAuto() {
          stopAuto();
          autoTimer = setInterval(function () { goTo(currentSlide + 1); }, autoDelay);
        }

        function stopAuto() {
          if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
        }

        carousel.addEventListener('mouseenter', stopAuto);
        carousel.addEventListener('mouseleave', startAuto);

        goTo(0);
        startAuto();
      });
    }
  };

})(Drupal, once);
