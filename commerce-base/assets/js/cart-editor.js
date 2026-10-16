/* Official Cart/Checkout extension. No cart state or frontend DOM changes. */
(() => {
    window.wc.blocksCheckout.registerCheckoutFilters('commerce-base', {
        additionalCartCheckoutInnerBlockTypes: (types, extensions, args) =>
            args?.block === 'woocommerce/filled-cart-block'
                ? [...new Set([...types, 'woocommerce/product-collection'])]
                : types
    });
})();
