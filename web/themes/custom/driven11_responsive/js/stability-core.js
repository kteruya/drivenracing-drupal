(function (Drupal, once) {
  Drupal.behaviors.stabilityInit = {
    attach: function (context) {
      once('stability-init', 'body', context).forEach(function () {
        // e.g. jQuery('.hero-slider', context).slick();
        // e.g. jQuery('.lightbox', context).magnificPopup({ type: 'image' });
      });
    }
  };
})(Drupal, once);
