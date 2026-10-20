(() => {
  const panel = document.querySelector('[data-wb-listing-views]');
  if (!panel) return;
  const markPending = () => {
    const notice = panel.querySelector('[data-wb-listing-view-pending]');
    if (notice) notice.hidden = false;
    panel.querySelectorAll('[data-wb-listing-view-save]').forEach(button => { button.disabled = true; });
  };
  document.querySelectorAll('[data-admin-listing-filters], [data-wb-listing-view-columns]').forEach(form => {
    form.addEventListener('input', markPending);
    form.addEventListener('change', markPending);
  });
})();
