(function () {
	'use strict';
	var root = document.documentElement;
	var mode = 'system';
	try {
		var saved = window.localStorage.getItem('commerce-base-color-mode');
		if (saved === 'light' || saved === 'dark' || saved === 'system') mode = saved;
	} catch (error) { /* System mode also works when storage is unavailable. */ }
	var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	var theme = mode === 'system' ? (dark ? 'dark' : 'light') : mode;
	root.dataset.colorMode = mode;
	root.dataset.theme = theme;
	root.style.colorScheme = theme;
}());
