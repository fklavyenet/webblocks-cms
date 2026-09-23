(function () {
  'use strict';

  var groupedTriggers = document.querySelectorAll('.wb-gallery-trigger[data-wb-gallery-group]');

  if (!groupedTriggers.length || !document.body) return;

  // WebBlocks UI intentionally discovers a viewer sequence inside the nearest
  // .wb-gallery. Standalone Image blocks can be separated by arbitrary article
  // composition, so the document body becomes their neutral shared scope.
  // Native Gallery blocks retain their closer .wb-gallery ancestor.
  document.body.classList.add('wb-gallery', 'wb-gallery-document-scope');
})();
