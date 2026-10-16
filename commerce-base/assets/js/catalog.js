/* Accessible presentation enhancements; WooCommerce owns all commerce state. */
(function () {
    'use strict';
    var scheduled = false;
    function enhance() {
        scheduled = false;
        document.querySelectorAll('.cb-filters').forEach(function (filters) {
            var open = filters.classList.contains('is-overlay-opened');
            var mobile = window.matchMedia('(max-width: 782px)').matches;
            var trigger = filters.querySelector('.wc-block-product-filters__open-overlay');
            var dialog = filters.querySelector('.wc-block-product-filters__overlay-dialog');
            var overlay = filters.querySelector('.wc-block-product-filters__overlay');
            if (!trigger || !dialog || !overlay) return;
            dialog.id = 'cb-filter-dialog';
            trigger.setAttribute('aria-controls', dialog.id);
            trigger.setAttribute('aria-expanded', String(open));
            var count = filters.querySelectorAll('.wc-block-product-filter-removable-chips__remove').length;
            var indicator = trigger.querySelector('.cb-filter-count');
            if (!indicator) { indicator = document.createElement('span'); indicator.className = 'cb-filter-count'; trigger.appendChild(indicator); }
            var countText = count ? '(' + count + (count === 1 ? ' activo)' : ' activos)') : '';
            if (indicator.textContent !== countText) indicator.textContent = countText;
            var heading = filters.querySelector('.cb-filter-heading');
            if (heading) {
                var headingCount = heading.querySelector('.cb-sidebar-filter-count');
                if (!headingCount) { headingCount = document.createElement('span'); headingCount.className = 'cb-sidebar-filter-count'; heading.appendChild(headingCount); }
                if (headingCount.textContent !== countText) headingCount.textContent = countText;
            }
            overlay.inert = mobile && !open;
            if (mobile && open) dialog.setAttribute('aria-modal', 'true');
            else dialog.removeAttribute('aria-modal');
            if (filters.dataset.cbOpened !== String(open)) {
                filters.dataset.cbOpened = String(open);
                if (mobile && open) dialog.querySelector('button')?.focus();
                else if (mobile && filters.dataset.cbWasOpen === 'true') trigger.focus();
                filters.dataset.cbWasOpen = String(open);
            }
        });
        document.querySelectorAll('.flex-control-thumbs img').forEach(function (img, index) {
            img.setAttribute('role', 'button');
            img.tabIndex = 0;
            img.setAttribute('aria-label', 'Ver fotografía ' + (index + 1));
            img.setAttribute('aria-pressed', String(img.classList.contains('flex-active')));
            if (img.dataset.cbThumb) return;
            img.dataset.cbThumb = 'true';
            img.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); img.click(); }
            });
        });
    }
    function schedule() { if (!scheduled) { scheduled = true; window.requestAnimationFrame(enhance); } }
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab' || !window.matchMedia('(max-width: 782px)').matches) return;
        var dialog = document.querySelector('.cb-filters.is-overlay-opened .wc-block-product-filters__overlay-dialog');
        if (!dialog) return;
        var focusable = Array.from(dialog.querySelectorAll('button, input, select, a[href], [tabindex="0"]')).filter(function (node) { return !node.disabled && node.getClientRects().length && !node.closest('[hidden]'); });
        var first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) { event.preventDefault(); first?.focus(); }
    });
    // Keep the native, validated form submission. Its server notice is success/error feedback.
    document.addEventListener('submit', function (event) {
        if (!event.target.matches('form.cart')) return;
        var button = event.target.querySelector('.single_add_to_cart_button');
        if (button && !button.classList.contains('disabled')) {
            event.target.setAttribute('aria-busy', 'true');
            button.textContent = 'Agregando…';
        }
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form.cart[aria-busy]').forEach(function (form) { form.removeAttribute('aria-busy'); var button = form.querySelector('.single_add_to_cart_button'); if (button) button.textContent = 'Añadir al carrito'; });
    });
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    window.addEventListener('resize', schedule);
    enhance();
}());
