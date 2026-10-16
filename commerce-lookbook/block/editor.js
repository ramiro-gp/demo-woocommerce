(function (wp) {
    'use strict';
    const { createElement: el, Fragment, useState, useEffect } = wp.element;
    const { __ } = wp.i18n;
    const t = text => __(text, 'commerce-lookbook');
    const { useBlockProps, MediaUpload, MediaUploadCheck, InspectorControls } = wp.blockEditor;
    const { Button, PanelBody, RangeControl, TextControl, ComboboxControl, Notice, Spinner } = wp.components;
    const attributes = {
        imageId: { type: 'number', default: 0 }, imageUrl: { type: 'string', default: '' },
        imageAlt: { type: 'string', default: '' }, imageWidth: { type: 'number', default: 0 },
        imageHeight: { type: 'number', default: 0 }, hotspots: { type: 'array', default: [] }
    };
    function Edit({ attributes: a, setAttributes }) {
        const [selected, setSelected] = useState(0);
        const [products, setProducts] = useState({});
        const [missing, setMissing] = useState([]);
        const [results, setResults] = useState([]);
        const [search, setSearch] = useState('');
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');
        const spots = a.hotspots;
        const spot = spots[selected];
        const ids = [...new Set(spots.map(s => s.productId).filter(Boolean))].join(',');
        useEffect(() => {
            let cancelled = false;
            if (!ids) { setMissing([]); return; }
            const query = ids.split(',').map(id => 'include[]=' + encodeURIComponent(id)).join('&');
            wp.apiFetch({ path: '/commerce-lookbook/v1/products?' + query }).then(data => {
                if (cancelled) return;
                setProducts(prev => ({ ...prev, ...Object.fromEntries(data.products.map(p => [p.id, p])) }));
                setMissing(data.missing); setError('');
            }).catch(e => { if (!cancelled) setError(e.message || t('No se pudieron cargar los productos.')); });
            return () => { cancelled = true; };
        }, [ids]);
        useEffect(() => {
            let cancelled = false;
            setLoading(true);
            const timer = setTimeout(() => {
                wp.apiFetch({ path: '/commerce-lookbook/v1/products?search=' + encodeURIComponent(search) }).then(data => {
                    if (cancelled) return;
                    setResults(data.products);
                    setProducts(prev => ({ ...prev, ...Object.fromEntries(data.products.map(p => [p.id, p])) }));
                    setLoading(false); setError('');
                }).catch(e => { if (!cancelled) { setLoading(false); setError(e.message || t('No se pudo buscar productos.')); } });
            }, 250);
            return () => { cancelled = true; clearTimeout(timer); };
        }, [search]);
        const update = patch => setAttributes({ hotspots: spots.map((s, i) => i === selected ? { ...s, ...patch } : s) });
        const add = () => { setAttributes({ hotspots: [...spots, { productId: 0, x: 50, y: 50 }] }); setSelected(spots.length); };
        const remove = () => { setAttributes({ hotspots: spots.filter((s, i) => i !== selected) }); setSelected(Math.max(0, selected - 1)); };
        const move = delta => {
            const next = spots.slice(), target = selected + delta;
            [next[selected], next[target]] = [next[target], next[selected]];
            setAttributes({ hotspots: next }); setSelected(target);
        };
        const chooseImage = image => setAttributes({ imageId: image.id, imageUrl: image.url,
            imageAlt: image.alt || '', imageWidth: image.width || 0, imageHeight: image.height || 0 });
        const media = el(MediaUploadCheck, null, el(MediaUpload, {
            allowedTypes: ['image'], value: a.imageId, onSelect: chooseImage,
            render: ({ open }) => el(Button, { variant: 'secondary', onClick: open }, a.imageUrl ? t('Cambiar imagen') : t('Elegir imagen'))
        }));
        const options = results.map(p => ({ value: String(p.id), label: p.name }));
        if (spot?.productId && products[spot.productId] && !options.some(p => p.value === String(spot.productId))) {
            options.unshift({ value: String(spot.productId), label: products[spot.productId].name });
        }
        return el(Fragment, null,
            el(InspectorControls, null, el(PanelBody, { title: t('Imagen del lookbook') }, media,
                el(TextControl, { label: t('Texto alternativo'), help: t('Describí la fotografía. Los productos tienen sus propios enlaces accesibles.'), value: a.imageAlt, onChange: imageAlt => setAttributes({ imageAlt }) }))),
            el('div', useBlockProps({ className: 'slb-editor' }),
                !a.imageUrl ? el('div', { className: 'slb-editor-empty' }, el('strong', null, 'Shoppable Lookbook'), el('p', null, t('Elegí una imagen y asociá productos de WooCommerce.')), media) :
                    el('div', { className: 'slb-editor-stage' },
                        el('img', { src: a.imageUrl, alt: a.imageAlt }),
                        spots.map((s, i) => el('button', {
                            key: i, type: 'button', className: 'slb-editor-point' + (i === selected ? ' is-selected' : ''),
                            style: { left: s.x + '%', top: s.y + '%' }, onClick: () => setSelected(i),
                            'aria-label': t('Editar punto') + ' ' + (i + 1), 'aria-pressed': i === selected
                        }, i + 1))),
                el('div', { className: 'slb-editor-controls' },
                    a.imageUrl && el(Button, { variant: 'secondary', disabled: spots.length >= 12, onClick: add }, t('Agregar hotspot')),
                    spots.length > 0 && el('div', { className: 'slb-editor-tabs', role: 'group', 'aria-label': t('Hotspots') }, spots.map((s, i) => el(Button, {
                        key: i, variant: selected === i ? 'primary' : 'secondary', 'aria-pressed': selected === i, onClick: () => setSelected(i)
                    }, (i + 1) + ' · ' + (missing.includes(s.productId) ? t('Referencia no disponible') : products[s.productId]?.name || t('Seleccionar producto'))))),
                    error && el(Notice, { status: 'error', isDismissible: false }, error),
                    missing.length > 0 && el(Notice, { status: 'warning', isDismissible: false }, t('Hay productos eliminados, ocultos o sin publicar. Sus puntos no aparecen en la tienda. Seleccioná otro producto o eliminá el punto.')),
                    spot && el('div', { className: 'slb-editor-fields' },
                        el(ComboboxControl, { label: t('Producto WooCommerce'), help: t('Buscá por nombre. Se guarda sólo el ID del producto padre.'),
                            value: spot.productId ? String(spot.productId) : null, options,
                            onFilterValueChange: setSearch, onChange: value => update({ productId: Number(value) || 0 }) }),
                        loading && el(Spinner),
                        products[spot.productId] && !missing.includes(spot.productId) && el('div', { className: 'slb-editor-product' },
                            el('span', { dangerouslySetInnerHTML: { __html: products[spot.productId].priceHtml } }),
                            !products[spot.productId].inStock && el('strong', null, ' · ' + t('Agotado'))),
                        el(RangeControl, { label: t('Posición X (%)'), value: spot.x, min: 0, max: 100, step: 0.1, onChange: x => { if (Number.isFinite(x)) update({ x }); } }),
                        el(RangeControl, { label: t('Posición Y (%)'), value: spot.y, min: 0, max: 100, step: 0.1, onChange: y => { if (Number.isFinite(y)) update({ y }); } }),
                        el('div', { className: 'slb-editor-actions' },
                            el(Button, { variant: 'secondary', disabled: selected === 0, onClick: () => move(-1) }, t('Subir punto')),
                            el(Button, { variant: 'secondary', disabled: selected === spots.length - 1, onClick: () => move(1) }, t('Bajar punto')),
                            el(Button, { variant: 'tertiary', isDestructive: true, onClick: remove }, t('Eliminar hotspot')))),
                    el('p', { className: 'slb-editor-help' }, t('X/Y ubican el centro del punto sobre la imagen completa, sin recortes. El orden define la navegación con Tab. Usá 2–4 puntos separados y revisá la página en mobile.')))
            )
        );
    }
    wp.blocks.registerBlockType('commerce-lookbook/shoppable-lookbook', {
        apiVersion: 3, title: 'Shoppable Lookbook', category: 'media', icon: 'location-alt', attributes,
        supports: { html: false, align: ['wide', 'full'] }, edit: Edit,
        save: ({ attributes: a }) => !a.imageUrl ? null : el('div', wp.blockEditor.useBlockProps.save(),
            el('img', { src: a.imageUrl, alt: a.imageAlt, width: a.imageWidth || undefined, height: a.imageHeight || undefined }))
    });
})(window.wp);
