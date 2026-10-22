/* Presentation adapter for WooCommerce's native variation form. */
(function ($) {
    'use strict';
    $(function () {
        $('form.variations_form').each(function () {
            var $form = $(this), engine, groups = [], lastVariation;
            function sync() {
                var purchase = $form.find('.single_add_to_cart_button')[0];
                if (purchase) purchase.setAttribute('aria-disabled', String(purchase.classList.contains('disabled') || purchase.disabled));
                groups.forEach(function (group) {
                    var selected = group.select.value;
                    group.buttons.forEach(function (button) {
                        var option = Array.from(group.select.options).find(function (item) { return item.value === button.dataset.value; });
                        var unavailable = !option || option.disabled || group.select.disabled;
                        var exhausted = false;
                        if (!unavailable && engine && Array.isArray(engine.variationData)) {
                            // Reuse WooCommerce's matcher, including wildcard attributes.
                            var attributes = engine.getChosenAttributes().data;
                            attributes[group.select.name] = button.dataset.value;
                            var matches = engine.findMatchingVariations(engine.variationData, attributes);
                            exhausted = matches.length > 0 && matches.every(function (variation) { return !variation.is_in_stock; });
                            unavailable = matches.length === 0 || matches.every(function (variation) { return !variation.variation_is_active || !variation.is_purchasable; });
                        } else if (lastVariation && selected === button.dataset.value) {
                            exhausted = !lastVariation.is_in_stock;
                        }
                        button.disabled = unavailable || exhausted;
                        button.dataset.unavailable = exhausted ? 'stock' : (unavailable ? 'combination' : '');
                        button.setAttribute('aria-checked', String(selected === button.dataset.value));
                        button.querySelector('small').textContent = exhausted ? 'Agotado' : (unavailable ? 'No disponible' : '');
                    });
                    var available = group.buttons.filter(function (button) { return !button.disabled; });
                    var tabStop = available.find(function (button) { return button.dataset.value === selected; }) || available[0];
                    group.buttons.forEach(function (button) { button.tabIndex = button === tabStop ? 0 : -1; });
                });
            }
            function initialize(instance) {
                if (instance) engine = instance;
                if (groups.length) { sync(); return; }
                $form.find('.variations select[name^="attribute_"]').each(function (index) {
                    var select = this;
                    var label = $form.find('label').filter(function () { return this.htmlFor === select.id; }).first();
                    if (!label.length) return;
                    var group = document.createElement('div');
                    group.className = 'cb-variation-options';
                    group.setAttribute('role', 'radiogroup');
                    var labelId = label.attr('id') || (select.id + '-cb-label-' + index);
                    label.attr('id', labelId);
                    group.setAttribute('aria-labelledby', labelId);
                    var reference = document.createElement('select');
                    reference.innerHTML = $(select).data('attribute_html') || select.innerHTML;
                    var buttons = [];
                    Array.from(reference.options).forEach(function (option) {
                        if (!option.value) return;
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'cb-variation-option';
                        button.setAttribute('role', 'radio');
                        button.dataset.value = option.value;
                        var name = document.createElement('span');
                        name.textContent = option.text;
                        button.appendChild(name);
                        button.appendChild(document.createElement('small'));
                        button.addEventListener('click', function () {
                            if (button.disabled) return;
                            lastVariation = null;
                            $(select).val(button.dataset.value).trigger('change');
                            sync();
                        });
                        button.addEventListener('keydown', function (event) {
                            if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
                            event.preventDefault();
                            var enabled = buttons.filter(function (item) { return !item.disabled; });
                            var current = enabled.indexOf(button);
                            var next = event.key === 'Home' ? 0 : event.key === 'End' ? enabled.length - 1 : (current + (['ArrowRight', 'ArrowDown'].includes(event.key) ? 1 : -1) + enabled.length) % enabled.length;
                            if (enabled[next]) { enabled[next].focus(); enabled[next].click(); }
                        });
                        group.appendChild(button);
                        buttons.push(button);
                    });
                    if (!buttons.length) return;
                    select.insertAdjacentElement('afterend', group);
                    // Progressive enhancement: no JS means the original visible select.
                    select.hidden = true;
                    groups.push({ select: select, buttons: buttons });
                    label.on('click.cbOptions', function (event) {
                        event.preventDefault();
                        var target = buttons.find(function (button) { return button.tabIndex === 0; });
                        if (target) target.focus();
                    });
                });
                sync();
            }
            $form.on('wc_variation_form.cbOptions', function (event, instance) { initialize(instance); });
            $form.on('woocommerce_update_variation_values.cbOptions woocommerce_variation_has_changed.cbOptions reset_data.cbOptions', function () { sync(); });
            $form.on('found_variation.cbOptions', function (event, variation) { lastVariation = variation; sync(); });
            $form.on('reset_data.cbOptions', function () { lastVariation = null; sync(); });
            $form.on('change.cbOptions', '.variations select', sync);
            $form.on('show_variation.cbOptions hide_variation.cbOptions', sync);
            // Covers forms initialized before this adapter was downloaded.
            window.setTimeout(function () { initialize(); }, 150);
        });
    });
}(jQuery));
