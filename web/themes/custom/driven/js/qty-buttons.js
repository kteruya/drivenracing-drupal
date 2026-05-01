/**
 * driven_tweaks.js
 * Version: 1.0.2 — 2026-04-26
 *
 * ±qty buttons for all Commerce add-to-cart forms on a page.
 * Works on both product detail page (single form) and catalog
 * page (multiple forms, one per product row).
 *
 * Change log:
 * - 2026-04-26 (v1.0.2) Switch querySelector → querySelectorAll so
 *                        every form on the catalog page gets buttons.
 * - 2026-04-26 (v1.0.1) Initial release, single form only.
 */
(function () {
  'use strict';

  function initQtyButtons() {
    var inputs = document.querySelectorAll(
      '#edit-quantity-wrapper input[type="number"], ' +
      '.js-form-item-quantity-0-value input[type="number"]'
    );

    inputs.forEach(function (input) {
      if (input.dataset.qtyInited) return;
      input.dataset.qtyInited = '1';

      var minus = document.createElement('button');
      minus.type = 'button';
      minus.className = 'cp-qty-btn cp-qty-btn-minus';
      minus.textContent = '\u2212';
      minus.setAttribute('aria-label', 'Decrease quantity');

      var plus = document.createElement('button');
      plus.type = 'button';
      plus.className = 'cp-qty-btn cp-qty-btn-plus';
      plus.textContent = '+';
      plus.setAttribute('aria-label', 'Increase quantity');

      input.parentNode.insertBefore(minus, input);
      input.parentNode.insertBefore(plus, input.nextSibling);

      minus.addEventListener('click', function () {
        var v = parseInt(input.value, 10) || 1;
        if (v > 1) { input.value = v - 1; }
      });

      plus.addEventListener('click', function () {
        var v = parseInt(input.value, 10) || 0;
        input.value = v + 1;
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initQtyButtons);
  } else {
    initQtyButtons();
  }

  /* Fire after Drupal Ajax rebuilds forms (variation swaps, BigPipe) */
  if (window.Drupal && window.Drupal.behaviors) {
    Drupal.behaviors.drivenQtyButtons = {
      attach: function (context) {
        initQtyButtons();
      }
    };
  }

}());

