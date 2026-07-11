(function () {
  'use strict';

  document.querySelectorAll('[data-wb-focal-point]').forEach(function (field) {
    var button = field.querySelector('[data-wb-focal-image]');
    var marker = field.querySelector('[data-wb-focal-marker]');
    var inputX = field.querySelector('[data-wb-focal-x]');
    var inputY = field.querySelector('[data-wb-focal-y]');

    if (!button || !marker || !inputX || !inputY) return;

    button.addEventListener('click', function (event) {
      var bounds = button.getBoundingClientRect();
      var x = Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width));
      var y = Math.max(0, Math.min(1, (event.clientY - bounds.top) / bounds.height));

      inputX.value = x.toFixed(4);
      inputY.value = y.toFixed(4);
      marker.style.left = (x * 100) + '%';
      marker.style.top = (y * 100) + '%';
    });
  });
})();
