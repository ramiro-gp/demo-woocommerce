/* No storage, HTTP, submit or save. Native WooCommerce owns persistence. */
(function ($) {
    'use strict';
    $(function () {
        document.querySelectorAll('.commerce-core-address-copy').forEach(function (section) {
            const source = section.querySelector('.commerce-core-copy-source');
            const form = section.closest('form');
            const start = section.querySelector('.commerce-core-copy-start');
            if (!source || !form || !start) return;
            const values = JSON.parse(source.textContent);
            const confirm = section.querySelector('#commerce-core-copy-confirm');
            const apply = section.querySelector('.commerce-core-copy-apply');
            const cancel = section.querySelector('.commerce-core-copy-cancel');
            const status = section.querySelector('.commerce-core-copy-status');
            const fields = ['country', 'state', 'first_name', 'last_name', 'company', 'city', 'postcode', 'address_1', 'address_2', 'phone'];
            const input = name => form.elements.namedItem('shipping_' + name);
            function close() {
                confirm.hidden = true;
                start.setAttribute('aria-expanded', 'false');
            }
            function copy() {
                let complete = true;
                // WooCommerce replaces state inputs on country change. Always
                // reacquire the element after its native synchronous event.
                fields.forEach(function (name) {
                    if (!Object.prototype.hasOwnProperty.call(values, name)) return;
                    const element = input(name);
                    if (!element || element.disabled) return;
                    const value = values[name];
                    if (element.tagName === 'SELECT' && !Array.from(element.options).some(option => option.value === value)) {
                        complete = false;
                        return;
                    }
                    $(element).val(value).trigger('change');
                });
                close();
                status.textContent = complete ? status.dataset.success : status.dataset.error;
                const first = input('first_name');
                if (first && first.type !== 'hidden') first.focus();
            }
            start.hidden = false;
            start.addEventListener('click', function () {
                status.textContent = '';
                const existing = fields.some(name => !['country', 'state'].includes(name) && input(name) && input(name).value.trim() !== '');
                if (!existing) { copy(); return; }
                confirm.hidden = false;
                start.setAttribute('aria-expanded', 'true');
                apply.focus();
            });
            apply.addEventListener('click', copy);
            cancel.addEventListener('click', function () { close(); start.focus(); });
            confirm.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { event.preventDefault(); close(); start.focus(); }
            });
        });
    });
})(jQuery);
