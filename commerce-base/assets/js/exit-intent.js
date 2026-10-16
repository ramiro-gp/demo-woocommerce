/* Exit Intent v1: official store resolution; no manual requests or polling. */
(() => {
  'use strict';
  const dialog = document.querySelector('[data-cb-exit-intent]');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const desktop = matchMedia('(min-width: 960px) and (hover: hover) and (pointer: fine)');
  if (!desktop.matches) return;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const cartStore = window.wc?.wcBlocksData?.cartStore;
  if (!cartStore) return;
  // Mini-Cart loads lazily. Resolve the shared official store on eligible
  // desktop pages, before any exit event; never fetch in the exit listener.
  let cartReady = false;
  if (desktop.matches) window.wp.data.resolveSelect(cartStore).getCartData().then(() => { cartReady = true; }).catch(() => {});
  const key = 'commerce_exit_intent_v1';
  const started = performance.now();
  let interacted = false, memory = 0, previousFocus, closing = false;
  function recent() {
    let stamp = memory;
    for (const name of ['localStorage', 'sessionStorage']) {
      try { stamp = Math.max(stamp, Number(window[name].getItem(key)) || 0); } catch (_) { /* Storage can be blocked. */ }
    }
    return Date.now() - stamp < Number(dialog.dataset.cooldownHours) * 3600000;
  }
  function record() {
    memory = Date.now();
    try { localStorage.setItem(key, String(memory)); return; } catch (_) { /* Fall back to the tab session. */ }
    try { sessionStorage.setItem(key, String(memory)); } catch (_) { /* Memory still prevents repeated display. */ }
  }
  function busy() {
    if (document.documentElement.classList.contains('cb-menu-active')) return true;
    return [...document.querySelectorAll('dialog[open], [role="dialog"][aria-modal="true"], .wc-block-mini-cart__drawer[aria-hidden="false"], .pswp--open')]
      .some(node => node !== dialog && node.getClientRects().length && getComputedStyle(node).visibility !== 'hidden' && node.getAttribute('aria-hidden') !== 'true');
  }
  function close() {
    if (!dialog.open || closing) return;
    closing = true; dialog.classList.add('is-closing');
    setTimeout(() => {
      dialog.close(); dialog.classList.remove('is-closing'); closing = false;
      if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
    }, reduced.matches ? 0 : 220);
  }
  dialog.querySelectorAll('[data-exit-close]').forEach(button => button.addEventListener('click', close));
  dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
  dialog.addEventListener('keydown', event => {
    if (event.key !== 'Tab') return;
    const controls = [...dialog.querySelectorAll('a[href], button:not(:disabled)')];
    const first = controls[0], last = controls[controls.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.querySelector('h2'))) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  for (const type of ['pointerdown', 'keydown', 'scroll']) {
    document.addEventListener(type, event => { if (event.isTrusted) interacted = true; }, { passive: true, capture: true });
  }
  document.addEventListener('mouseout', event => {
    if (event.relatedTarget !== null || event.clientY > 0 || event.clientX <= 0 || event.clientX >= innerWidth || !desktop.matches || !interacted || document.visibilityState !== 'visible' || performance.now() - started < Number(dialog.dataset.minimumSeconds) * 1000 || dialog.open || recent() || busy()) return;
    if (!cartReady || !(Number(window.wp.data.select(cartStore).getCartData().itemsCount) > 0)) return;
    previousFocus = document.activeElement;
    dialog.showModal(); record(); dialog.querySelector('h2').focus({ preventScroll: true });
  });
})();
