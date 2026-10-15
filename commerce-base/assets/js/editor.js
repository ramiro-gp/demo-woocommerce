(function (blocks, element, blockEditor, ServerSideRender) {
    'use strict';
    const names = { header: 'Header', footer: 'Footer', 'product-grid': 'Grilla de productos' };
    Object.entries(names).forEach(([slug, title]) => blocks.registerBlockType('commerce-base/' + slug, {
        apiVersion: 3, title: 'Commerce Base · ' + title, category: 'design', icon: 'layout',
        attributes: slug === 'product-grid' ? { limit: { type: 'number', default: 4 } } : {},
        supports: { html: false, inserter: slug === 'product-grid' },
        edit: ({ attributes }) => element.createElement('div', blockEditor.useBlockProps(), element.createElement(ServerSideRender, { block: 'commerce-base/' + slug, attributes })),
        save: () => null
    }));
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender);
