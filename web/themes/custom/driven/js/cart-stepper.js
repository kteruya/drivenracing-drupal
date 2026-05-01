/**
 * Project: Driven Racing – Drupal 11
 * File: web/modules/custom/driven_tweaks/js/cart-stepper.js
 *
 * Version: 2026-03-05 v1.0.2
 *
 * Change log:
 * - 2026-03-05  (v1.0.2)  Version bump. No functional change.
 * - 2026-03-05  (v1.0.1)  Auto-submit Update Cart when stepper buttons
 *                          are clicked. Finds the hidden edit-submit button
 *                          and triggers a click after a 300ms debounce so
 *                          rapid clicks only submit once. This replicates
 *                          the D7/Ubercart behavior where qty changes update
 *                          the cart total immediately without a separate
 *                          "Update Cart" button press.
 * - 2026-03-05  (v1.0.0)  Initial release. Injects - / qty / + stepper
 *                          buttons around .quantity-edit-input in the
 *                          commerce cart form via Drupal.behaviors.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.drivenCartStepper = {
    attach: function (context) {
      once('driven-cart-stepper', 'input.quantity-edit-input', context).forEach(function (input) {
        var wrapper = document.createElement('span');
        wrapper.className = 'qty-stepper';

        var minus = document.createElement('button');
        minus.type = 'button';
        minus.textContent = '-';
        minus.className = 'qty-btn qty-minus';
        minus.setAttribute('aria-label', 'Decrease quantity');

        var plus = document.createElement('button');
        plus.type = 'button';
        plus.textContent = '+';
        plus.className = 'qty-btn qty-plus';
        plus.setAttribute('aria-label', 'Increase quantity');

        // Debounced auto-submit of the hidden Update Cart button
        var submitTimer = null;
        function scheduleUpdate() {
          clearTimeout(submitTimer);
          submitTimer = setTimeout(function () {
            // Find the Update Cart submit button (hidden by CSS but still in DOM)
            var submitBtn = document.getElementById('edit-submit');
            if (submitBtn) {
              submitBtn.click();
            }
          }, 400);
        }

        minus.addEventListener('click', function () {
          var val = parseInt(input.value, 10) || 0;
          if (val > 0) {
            input.value = val - 1;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            scheduleUpdate();
          }
        });

        plus.addEventListener('click', function () {
          var val = parseInt(input.value, 10) || 0;
          input.value = val + 1;
          input.dispatchEvent(new Event('change', { bubbles: true }));
          scheduleUpdate();
        });

        // Wrap input and insert buttons
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(minus);
        wrapper.appendChild(input);
        wrapper.appendChild(plus);
      });
    }
  };

}(Drupal, once));
