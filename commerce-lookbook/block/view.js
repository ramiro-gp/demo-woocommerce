(function () {
    'use strict';
    document.querySelectorAll('.slb-lookbook').forEach(book => {
        const stage = book.querySelector('.slb-stage');
        const links = [...book.querySelectorAll('.slb-hotspot')];
        if (!stage || !links.length) return;
        let active = null;
        let frame = 0;
        const position = link => {
            const pill = link.querySelector('.slb-pill');
            const bounds = stage.getBoundingClientRect();
            const anchor = link.getBoundingClientRect();
            const left = Math.max(bounds.left + 4, 4);
            const right = Math.min(bounds.right - 4, document.documentElement.clientWidth - 4);
            pill.style.maxWidth = Math.max(1, Math.min(304, right - left)) + 'px';
            const width = pill.offsetWidth, height = pill.offsetHeight;
            const cx = anchor.left + 22, cy = anchor.top + 22;
            let x = cx + 14;
            if (x + width > right) x = cx - 14 - width;
            x = Math.max(left, Math.min(x, right - width));
            const top = Math.max(bounds.top + 4, 4);
            const bottom = Math.min(bounds.bottom - 4, innerHeight - 4);
            const y = Math.max(top, Math.min(cy - height / 2, bottom - height));
            pill.style.left = (x - anchor.left) + 'px';
            pill.style.top = (y - anchor.top) + 'px';
        };
        const close = () => {
            if (!active) return;
            active.classList.remove('is-active');
            active.setAttribute('aria-expanded', 'false');
            active = null;
        };
        const open = link => {
            if (active !== link) close();
            active = link;
            link.classList.add('is-active');
            position(link);
            link.setAttribute('aria-expanded', 'true');
        };
        const schedule = () => {
            if (!active || frame) return;
            frame = requestAnimationFrame(() => { frame = 0; if (active) position(active); });
        };
        links.forEach(link => {
            let pointer = '', wasOpen = false;
            link.setAttribute('aria-expanded', 'false');
            link.addEventListener('pointerdown', event => { pointer = event.pointerType; wasOpen = active === link; });
            link.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') open(link); });
            link.addEventListener('pointerleave', event => {
                if (event.pointerType === 'mouse' && document.activeElement !== link && active === link) close();
            });
            link.addEventListener('focus', () => open(link));
            link.addEventListener('blur', () => { if (active === link) close(); });
            link.addEventListener('click', event => {
                // detail=0 is keyboard/assistive activation: preserve normal link behavior.
                const touch = event.detail !== 0 && (pointer === 'touch' || pointer === 'pen' || (!pointer && matchMedia('(hover: none)').matches));
                if (touch && !wasOpen) { event.preventDefault(); open(link); }
                pointer = '';
            });
            link.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); close(); } });
        });
        document.addEventListener('pointerdown', event => { if (!book.contains(event.target)) close(); });
        // Tapping the photograph outside its points closes the active point.
        book.addEventListener('pointerdown', event => { if (!event.target.closest('.slb-hotspot')) close(); });
        if ('ResizeObserver' in window) new ResizeObserver(schedule).observe(stage);
        window.addEventListener('resize', schedule, { passive: true });
        window.addEventListener('scroll', schedule, { passive: true });
        book.classList.add('slb-enhanced');
    });
})();
