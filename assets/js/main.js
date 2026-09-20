(function () {
	'use strict';

	const savedTheme = window.localStorage.getItem('spotly-theme');
	if (savedTheme === 'dark' || savedTheme === 'light') {
		document.documentElement.dataset.theme = savedTheme;
	}

	const toggle = document.createElement('button');
	toggle.type = 'button';
	toggle.className = 'theme-toggle';
	toggle.setAttribute('aria-label', 'Switch to dark mode');
	toggle.setAttribute('title', 'Switch to dark mode');

	function updateToggle() {
		const isDark = document.documentElement.dataset.theme === 'dark';
		toggle.textContent = isDark ? 'Light' : 'Dark';
		toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
		toggle.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
	}

	toggle.addEventListener('click', function () {
		const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
		document.documentElement.dataset.theme = nextTheme;
		window.localStorage.setItem('spotly-theme', nextTheme);
		updateToggle();
	});

	document.body.appendChild(toggle);
	updateToggle();
}());
