import './bootstrap';

document.addEventListener('click', (event) => {
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
	}
});
