(function () {
	'use strict';

	function initialize() {
		var root = document.documentElement;
		var storageKey = 'commerce-base-color-mode';
		var darkScheme = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
		var reducedMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
		var themeSelects = document.querySelectorAll('[data-cb-theme-select]');
		var mode = validMode(root.dataset.colorMode);

		function validMode(value) {
			return value === 'light' || value === 'dark' ? value : 'system';
		}

		function applyTheme(value, persist) {
			mode = validMode(value);
			var theme = mode === 'system' ? (darkScheme && darkScheme.matches ? 'dark' : 'light') : mode;
			root.dataset.colorMode = mode;
			root.dataset.theme = theme;
			root.style.colorScheme = theme;
			themeSelects.forEach(function (select) { select.value = mode; });
			if (persist) {
				try { window.localStorage.setItem(storageKey, mode); } catch (error) { /* Keep the session preference. */ }
			}
			root.dispatchEvent(new CustomEvent('commerce-base:themechange', { detail: { mode: mode, theme: theme } }));
		}

		themeSelects.forEach(function (select) {
			select.addEventListener('change', function () { applyTheme(select.value, true); });
		});
		if (darkScheme) {
			var systemChanged = function () { if (mode === 'system') applyTheme(mode, false); };
			if (darkScheme.addEventListener) darkScheme.addEventListener('change', systemChanged);
			else if (darkScheme.addListener) darkScheme.addListener(systemChanged);
		}
		window.addEventListener('storage', function (event) {
			if (event.key === storageKey || event.key === null) applyTheme(event.newValue, false);
		});
		applyTheme(mode, false);

		var header = document.querySelector('[data-cb-header]');
		if (header) {
			var hasHero = document.body.classList.contains('has-hero');
			var scrollFrame = 0;
			function updateHeader() {
				root.style.setProperty('--cb-header-progress', hasHero ? Math.min(1, Math.max(0, window.scrollY / 140)) : 1);
				scrollFrame = 0;
			}
			function measureHeader() { root.style.setProperty('--cb-header-height', header.getBoundingClientRect().height + 'px'); }
			updateHeader();
			measureHeader();
			if (hasHero) {
				window.addEventListener('scroll', function () {
					if (!scrollFrame) scrollFrame = window.requestAnimationFrame(updateHeader);
				}, { passive: true });
			}
			if ('ResizeObserver' in window) new ResizeObserver(measureHeader).observe(header);
			else window.addEventListener('resize', measureHeader, { passive: true });
		}

		function createDialogController(menu, openSelector, closeSelector) {
			if (!menu) return function () { return Promise.resolve(true); };
			var openers = document.querySelectorAll(openSelector);
			var closers = menu.querySelectorAll(closeSelector);
			var state = 'closed';
			var opener = null;
			var openingFrame = 0;
			var closingTimer = 0;
			var closingPromise = null;
			var resolveClosing = null;
			var scrollPosition = null;
			var savedStyles = null;
			var fallbackInert = [];
			var nativeDialog = typeof menu.showModal === 'function';
			var motionPanel = menu.querySelector('[data-cb-menu-panel]') || menu;
			var isNavigation = menu.hasAttribute('data-cb-menu');

			function setExpanded(expanded) {
				openers.forEach(function (button) { button.setAttribute('aria-expanded', String(expanded)); });
			}
			function lockScroll() {
				scrollPosition = { x: window.scrollX, y: window.scrollY };
				savedStyles = { overflow: root.style.overflow, position: document.body.style.position, top: document.body.style.top, left: document.body.style.left, width: document.body.style.width };
				root.style.overflow = 'hidden';
				document.body.style.position = 'fixed';
				document.body.style.top = -scrollPosition.y + 'px';
				document.body.style.left = -scrollPosition.x + 'px';
				document.body.style.width = '100%';
			}
			function unlockScroll() {
				if (!savedStyles) return;
				root.style.overflow = savedStyles.overflow;
				['position', 'top', 'left', 'width'].forEach(function (property) { document.body.style[property] = savedStyles[property]; });
				window.scrollTo({ left: scrollPosition.x, top: scrollPosition.y, behavior: 'instant' });
				savedStyles = null;
			}
			function setFallbackInert() {
				var branch = menu;
				while (branch.parentElement && branch !== document.body) {
					Array.from(branch.parentElement.children).forEach(function (sibling) {
						if (sibling !== branch) {
							fallbackInert.push({ node: sibling, value: sibling.inert });
							sibling.inert = true;
						}
					});
					branch = branch.parentElement;
				}
			}
			function finishClose() {
				if (state === 'closed') return;
				window.clearTimeout(closingTimer);
				window.cancelAnimationFrame(openingFrame);
				state = 'closed';
				menu.dataset.state = state;
				if (nativeDialog && menu.open) menu.close();
				else menu.removeAttribute('open');
				fallbackInert.forEach(function (item) { item.node.inert = item.value; });
				fallbackInert = [];
				unlockScroll();
				if (isNavigation) root.classList.remove('cb-menu-active');
				setExpanded(false);
				if (opener && opener.isConnected) opener.focus({ preventScroll: true });
				if (resolveClosing) resolveClosing(true);
				closingPromise = null;
				resolveClosing = null;
			}
			function openMenu(button) {
				if (state === 'open' || state === 'opening') return;
				window.clearTimeout(closingTimer);
				if (resolveClosing) resolveClosing(false);
				closingPromise = null;
				resolveClosing = null;
				if (state === 'closed') {
					opener = button || document.activeElement;
					if (isNavigation) root.classList.add('cb-menu-active');
					lockScroll();
					menu.dataset.state = 'opening';
					if (nativeDialog) menu.showModal();
					else {
						menu.setAttribute('open', '');
						menu.setAttribute('aria-modal', 'true');
						menu.setAttribute('role', 'dialog');
						setFallbackInert();
					}
					state = 'opening';
					openingFrame = window.requestAnimationFrame(function () {
						openingFrame = window.requestAnimationFrame(function () { state = 'open'; menu.dataset.state = state; });
					});
				} else {
					state = 'open';
					menu.dataset.state = state;
				}
				setExpanded(true);
				var firstFocus = button && button.hasAttribute('data-cb-menu-search-open') ? menu.querySelector('#cb-menu-search') : null;
				firstFocus = firstFocus || closers[0] || menu;
				if (!firstFocus.hasAttribute('tabindex') && firstFocus === menu) firstFocus.setAttribute('tabindex', '-1');
				firstFocus.focus({ preventScroll: true });
			}
			var closeDialog = function () {
				if (state === 'closed') return Promise.resolve(true);
				if (closingPromise) return closingPromise;
				window.cancelAnimationFrame(openingFrame);
				state = 'closing';
				menu.dataset.state = state;
				closingPromise = new Promise(function (resolve) { resolveClosing = resolve; });
				closingTimer = window.setTimeout(finishClose, reducedMotion && reducedMotion.matches ? 20 : 450);
				return closingPromise;
			};
			openers.forEach(function (button) { button.addEventListener('click', function () { openMenu(button); }); });
			closers.forEach(function (button) { button.addEventListener('click', closeDialog); });
			menu.addEventListener('cancel', function (event) { event.preventDefault(); closeDialog(); });
			menu.addEventListener('close', function () { if (state !== 'closed') finishClose(); });
			menu.addEventListener('transitionend', function (event) {
				if (event.target === motionPanel && event.propertyName === 'transform' && state === 'closing') finishClose();
			});
			menu.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') { event.preventDefault(); closeDialog(); return; }
				if (event.key !== 'Tab') return;
				var focusable = Array.from(menu.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')).filter(function (node) {
					return !node.disabled && node.tabIndex >= 0 && !node.closest('[inert]') && node.getClientRects().length;
				});
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (!first) { event.preventDefault(); menu.focus(); }
				else if (event.shiftKey && (document.activeElement === first || document.activeElement === menu)) { event.preventDefault(); last.focus(); }
				else if (!event.shiftKey && (document.activeElement === last || document.activeElement === menu)) { event.preventDefault(); first.focus(); }
			});
			setExpanded(false);
			return closeDialog;
		}
		var menu = document.querySelector('[data-cb-menu]');
		var closeMenu = createDialogController(menu, '[data-cb-menu-open]', '[data-cb-menu-close]');
		createDialogController(document.querySelector('[data-cb-filter-dialog]'), '[data-cb-filter-open]', '[data-cb-filter-close]');

		document.addEventListener('click', function (event) {
			if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
			var link = event.target.closest('a[data-cb-anchor], #wp-skip-link, [data-cb-menu] a[href]');
			if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
			var url = new URL(link.href, window.location.href);
			if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || url.search !== window.location.search || !url.hash) return;
			var identifier;
			try { identifier = decodeURIComponent(url.hash.slice(1)); } catch (error) { return; }
			var target = document.getElementById(identifier);
			if (!target) return;
			event.preventDefault();
			var settled = menu && menu.contains(link) ? closeMenu() : Promise.resolve(true);
			settled.then(function (closed) {
				if (!closed) return;
				if (window.location.hash !== url.hash) window.history.pushState(null, '', url.hash);
				var temporaryTabindex = !target.hasAttribute('tabindex') && !target.matches('a[href], button, input, select, textarea, summary, [contenteditable]');
				if (temporaryTabindex) {
					target.setAttribute('tabindex', '-1');
					target.addEventListener('blur', function () { target.removeAttribute('tabindex'); }, { once: true });
				}
				target.focus({ preventScroll: true });
				target.scrollIntoView({ behavior: reducedMotion && reducedMotion.matches ? 'instant' : 'smooth', block: 'start' });
			});
		});

		document.querySelectorAll('[data-cb-quantity]').forEach(function (quantity) {
			var input = quantity.querySelector('input[type="number"]');
			var minus = quantity.querySelector('[data-cb-quantity-minus]');
			var plus = quantity.querySelector('[data-cb-quantity-plus]');
			if (!input || !minus || !plus) return;
			function updateQuantity() {
				var value = input.valueAsNumber;
				minus.disabled = input.disabled || input.readOnly || (input.min !== '' && value <= Number(input.min));
				plus.disabled = input.disabled || input.readOnly || (input.max !== '' && value >= Number(input.max));
			}
			function changeQuantity(direction) {
				if (input.disabled || input.readOnly) return;
				try { if (direction > 0) input.stepUp(); else input.stepDown(); } catch (error) { return; }
				input.dispatchEvent(new Event('input', { bubbles: true }));
				input.dispatchEvent(new Event('change', { bubbles: true }));
			}
			minus.addEventListener('click', function () { changeQuantity(-1); });
			plus.addEventListener('click', function () { changeQuantity(1); });
			input.addEventListener('input', updateQuantity);
			input.addEventListener('change', updateQuantity);
			updateQuantity();
		});

		document.querySelectorAll('[data-cb-feedback-trigger]').forEach(function (button) {
			var feedback = document.getElementById(button.getAttribute('aria-controls')) || document.querySelector('[data-cb-demo-feedback]');
			if (!feedback) return;
			button.addEventListener('click', function () {
				feedback.hidden = !feedback.hidden;
				button.setAttribute('aria-expanded', String(!feedback.hidden));
			});
		});
		document.querySelectorAll('[data-cb-loading-demo]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (button.disabled) return;
				var label = button.querySelector('[data-cb-loading-label]') || button;
				var original = label.innerHTML;
				button.disabled = true;
				button.setAttribute('aria-busy', 'true');
				label.textContent = button.getAttribute('data-cb-loading-text') || 'Cargando…';
				window.setTimeout(function () {
					label.innerHTML = original;
					button.disabled = false;
					button.removeAttribute('aria-busy');
				}, 800);
			});
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
	else initialize();
}());
