/* Presentation subscribers only. All quantities, requests and errors belong to WooCommerce. */
(() => {
  const { cartStore } = window.wc.wcBlocksData;
  const { createElement: h, createRoot, Fragment } = window.wp.element;
  const { useSelect, select, subscribe } = window.wp.data;
  const root = document.querySelector('[data-cb-cart-count-root]');
  if (root) {
    function CartCount() {
      const count = useSelect(get => get(cartStore).getCartData().itemsCount, []);
      return h(Fragment, null,
        count > 0 ? h('span', { className: 'wc-block-mini-cart__badge', 'aria-hidden': true }, count) : null,
        h('span', { className: 'screen-reader-text', 'aria-live': 'polite', 'aria-atomic': true }, root.dataset.cbCountLabel.replace('%d', count || 0))
      );
    }
    createRoot(root).render(h(CartCount));
  }
  function reflectPending() {
    const cart = select(cartStore);
    document.querySelectorAll('.cb-card-purchase button[data-product_id]').forEach(button => {
      const pending = cart.getProductsPendingAdd().includes(Number(button.dataset.product_id));
      button.setAttribute('aria-busy', String(pending));
      // Keep keyboard focus on the control while Woo's request is pending.
      button.setAttribute('aria-disabled', String(pending));
    });
  }
  subscribe(reflectPending, cartStore);
  reflectPending();
  document.addEventListener('click', event => {
    const button = event.target.closest('.cb-card-purchase button[data-product_id]');
    if (button && select(cartStore).getProductsPendingAdd().includes(Number(button.dataset.product_id))) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);
})();
