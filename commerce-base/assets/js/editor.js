(function (blocks, element, blockEditor, components, ServerSideRender) {
    'use strict';
    const names = { header: 'Header', footer: 'Footer', 'product-grid': 'Grilla de productos', 'category-links': 'Categorías del catálogo', 'search-tools': 'Búsqueda y navegación' };
    const create = element.createElement;
    Object.entries(names).forEach(([slug, title]) => {
        const attributes = {};
        if (slug === 'product-grid' || slug === 'category-links') attributes.limit = { type: 'number', default: 4 };
        if (slug === 'category-links') attributes.termIds = { type: 'array', default: [], items: { type: 'number' } };
        if (slug === 'search-tools') attributes.recovery = { type: 'boolean', default: false };
        blocks.registerBlockType('commerce-base/' + slug, {
            apiVersion: 3, title: 'Commerce Base · ' + title, category: 'design', icon: 'layout', attributes,
            supports: { html: false, inserter: !['header', 'footer'].includes(slug) },
            edit: ({ attributes, setAttributes }) => {
                const controls = [];
                if ('limit' in attributes) controls.push(create(components.RangeControl, { key: 'limit', label: 'Cantidad máxima', value: attributes.limit, min: 1, max: slug === 'category-links' ? 8 : 24, onChange: limit => setAttributes({ limit }) }));
                if (slug === 'category-links') controls.push(create(components.TextControl, { key: 'terms', label: 'IDs de categorías (opcional)', help: 'Separados por coma. Vacío: categorías con productos. Los IDs están en Productos → Categorías.', value: attributes.termIds.join(', '), onChange: value => setAttributes({ termIds: value.split(',').map(Number).filter(id => Number.isInteger(id) && id > 0) }) }));
                if (slug === 'search-tools') controls.push(create(components.ToggleControl, { key: 'recovery', label: 'Incluir enlace al inicio', checked: attributes.recovery, onChange: recovery => setAttributes({ recovery }) }));
                return create(element.Fragment, null,
                    controls.length ? create(blockEditor.InspectorControls, null, create(components.PanelBody, { title: 'Contenido' }, ...controls)) : null,
                    create('div', blockEditor.useBlockProps(), create(ServerSideRender, { block: 'commerce-base/' + slug, attributes }))
                );
            },
            save: () => null
        });
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender);
