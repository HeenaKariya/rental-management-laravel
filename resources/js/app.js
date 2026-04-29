import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('click', (event) => {
	const appNavToggle = event.target.closest('[data-app-nav-toggle]');

	if (appNavToggle) {
		document.body.classList.toggle('body-nav-open');
		return;
	}

	const toggleButton = event.target.closest('[data-ui-toggle]');

	if (toggleButton) {
		const pressed = toggleButton.getAttribute('aria-pressed') === 'true';
		const toggle = toggleButton.querySelector('.toggle');

		toggleButton.setAttribute('aria-pressed', pressed ? 'false' : 'true');
		toggle?.classList.toggle('is-on', !pressed);
		return;
	}

	const tabButton = event.target.closest('[data-ui-tab]');

	if (tabButton) {
		document.querySelectorAll('[data-ui-tab]').forEach((button) => {
			button.classList.toggle('is-active', button === tabButton);
		});

		const target = tabButton.getAttribute('data-toggle-target');

		if (target) {
			document.querySelectorAll('[data-toggle-panel]').forEach((panel) => {
				panel.classList.toggle('is-visible', panel.getAttribute('data-toggle-panel') === target);
			});
		}
	}
});
