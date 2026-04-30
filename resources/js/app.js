import './bootstrap';
import Alpine from 'alpinejs';
import { DataTable } from 'simple-datatables';

window.Alpine = Alpine;

Alpine.start();

const initializeDataTables = () => {
	document.querySelectorAll('.js-data-table').forEach((table) => {
		if (table.dataset.dataTableInitialized === 'true') {
			return;
		}

		const pageSize = Number.parseInt(table.dataset.pageSize ?? '10', 10);
		const emptyMessage = table.dataset.emptyMessage ?? 'No records found.';

		const renumberRows = () => {
			table.querySelectorAll('tbody [data-row-number]').forEach((cell, index) => {
				cell.textContent = String(index + 1);
			});
		};

		const dataTable = new DataTable(table, {
			perPage: Number.isNaN(pageSize) ? 10 : pageSize,
			perPageSelect: [10, 20, 30, 50],
			searchable: true,
			fixedHeight: false,
			labels: {
				placeholder: 'Search properties',
				perPage: '{select} rows per page',
				noRows: emptyMessage,
				info: 'Showing {start} to {end} of {rows} properties',
			},
		});

		['datatable.init', 'datatable.page', 'datatable.sort', 'datatable.search', 'datatable.perpage', 'datatable.update'].forEach((eventName) => {
			dataTable.on(eventName, renumberRows);
		});

		renumberRows();
		table.dataset.dataTableInitialized = 'true';
	});
};

initializeDataTables();

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
		return;
	}

	const submenuToggle = event.target.closest('[data-sidebar-submenu-toggle]');

	if (submenuToggle) {
		const group = submenuToggle.closest('[data-sidebar-submenu]');

		if (!group) {
			return;
		}

		const isOpen = group.classList.toggle('is-open');
		submenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	}
});
