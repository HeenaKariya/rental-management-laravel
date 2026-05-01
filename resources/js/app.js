import './bootstrap';
import Alpine from 'alpinejs';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

window.Alpine = Alpine;

Alpine.start();

const initializeDataTables = () => {
	document.querySelectorAll('.js-data-table:not(.js-jquery-datatable)').forEach((table) => {
		if (table.dataset.dataTableInitialized === 'true') {
			return;
		}

		const pageSize = Number.parseInt(table.dataset.pageSize ?? '10', 10);
		const emptyMessage = table.dataset.emptyMessage ?? 'No records found.';
		const searchPlaceholder = table.dataset.searchPlaceholder ?? 'Search records';
		const infoLabel = table.dataset.infoLabel ?? 'records';
		const columnDefs = Array.from(table.querySelectorAll('thead th')).reduce((definitions, header, index) => {
			if (header.dataset.sortable === 'false') {
				definitions.push({ targets: index, orderable: false, searchable: false });
			}

			return definitions;
		}, []);

		const renumberRows = () => {
			table.querySelectorAll('tbody [data-row-number]').forEach((cell, index) => {
				cell.textContent = String(index + 1);
			});
		};

		const dataTable = new DataTable(table, {
			pageLength: Number.isNaN(pageSize) ? 10 : pageSize,
			lengthMenu: [10, 20, 30, 50],
			autoWidth: false,
			responsive: true,
			columnDefs,
			language: {
				searchPlaceholder,
				search: '',
				lengthMenu: '_MENU_ rows per page',
				zeroRecords: emptyMessage,
				info: `Showing _START_ to _END_ of _TOTAL_ ${infoLabel}`,
				infoEmpty: `No ${infoLabel} available`,
			},
		});

		dataTable.on('draw', renumberRows);

		renumberRows();
		table.dataset.dataTableInitialized = 'true';
	});
};

const initializeOversightDataTable = () => {
	const tableElement = document.getElementById('oversight-users-table');

	if (!tableElement) {
		return;
	}

	if (tableElement.dataset.jqDatatableInitialized === 'true') {
		return;
	}

	const dataTable = new DataTable(tableElement, {
		pageLength: 10,
		lengthMenu: [10, 20, 30, 50],
		order: [[2, 'asc']],
		columnDefs: [
			{ targets: 0, orderable: false, searchable: false, className: 'dt-control dtr-control', responsivePriority: 1 },
			{ targets: 1, searchable: false, responsivePriority: 2 },
			{ targets: 2, responsivePriority: 3 },
			{ targets: 3, responsivePriority: 4 },
			{ targets: 4, responsivePriority: 100 },
			{ targets: 5, responsivePriority: 101 },
			{ targets: 6, responsivePriority: 102 },
			{ targets: 7, orderable: false, searchable: false, responsivePriority: 103 },
		],
		language: {
			searchPlaceholder: 'Search monitored users',
			search: '',
			lengthMenu: '_MENU_ rows per page',
			zeroRecords: 'No monitored users found.',
			info: 'Showing _START_ to _END_ of _TOTAL_ users',
			infoEmpty: 'No users available',
		},
		autoWidth: false,
		responsive: {
			details: {
				type: 'column',
				target: 0,
				renderer: (api, rowIdx, columns) => {
					const hiddenColumns = columns.filter((column) => column.hidden);

					if (!hiddenColumns.length) {
						return false;
					}

					const rows = hiddenColumns
						.map((column) => {
							return `
								<tr data-dt-row="${column.rowIndex}" data-dt-column="${column.columnIndex}">
									<td><strong>${column.title}</strong></td>
									<td>${column.data}</td>
								</tr>
							`;
						})
						.join('');

					return `<table class="table table-sm mb-0">${rows}</table>`;
				},
			},
		},
	});

	const renumberRows = () => {
		Array.from(tableElement.querySelectorAll('tbody [data-row-number]')).forEach((cell, index) => {
			cell.textContent = String(index + 1);
		});
	};

	dataTable.on('draw', renumberRows);
	renumberRows();
	tableElement.dataset.jqDatatableInitialized = 'true';
};

const bootstrapPage = () => {
	initializeDataTables();
	initializeOversightDataTable();
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', bootstrapPage, { once: true });
} else {
	bootstrapPage();
}

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
