(function () {
	'use strict';

	function getRoot(row) {
		return row.closest('[data-gro-sms-templates]');
	}

	function updateStatus(row, override, defaultBody, effective) {
		const root = getRoot(row);
		const statusEl = row.querySelector('[data-gro-pattern-status]');
		if (!statusEl || !root) {
			return;
		}

		const labelDefault = root.getAttribute('data-gro-badge-default') || 'Default';
		const labelCustom = root.getAttribute('data-gro-badge-custom') || 'Custom';
		const labelEmpty = root.getAttribute('data-gro-badge-empty') || 'Not set';

		statusEl.classList.remove(
			'gro-pattern-card__status--default',
			'gro-pattern-card__status--custom',
			'gro-pattern-card__status--empty'
		);

		if (override !== '') {
			statusEl.textContent = labelCustom;
			statusEl.classList.add('gro-pattern-card__status--custom');
		} else if (defaultBody !== '' && effective === defaultBody) {
			statusEl.textContent = labelDefault;
			statusEl.classList.add('gro-pattern-card__status--default');
		} else {
			statusEl.textContent = labelEmpty;
			statusEl.classList.add('gro-pattern-card__status--empty');
		}
	}

	function updateCharCount(row) {
		const textarea = row.querySelector('[data-gro-pattern-textarea]');
		const counter = row.querySelector('[data-gro-pattern-chars]');
		if (!textarea || !counter) {
			return;
		}

		const text = textarea.value;
		const len = text.length;
		const max = parseInt(counter.getAttribute('data-max') || '500', 10);
		const template = counter.getAttribute('data-gro-chars-template');

		if (template) {
			counter.textContent = template.replace('%1$d', String(len)).replace('%2$d', String(max));
		} else {
			counter.textContent = len + ' / ' + max;
		}

		counter.classList.toggle('gro-pattern-card__chars--warn', len > max * 0.9);
	}

	function updateRowCurrent(row) {
		const textarea = row.querySelector('[data-gro-pattern-textarea], textarea');
		const currentEl = row.querySelector('[data-gro-sms-current]');
		if (!textarea || !currentEl) {
			return;
		}

		const override = textarea.value.trim();
		const defaultBody = row.getAttribute('data-gro-sms-default-body') || '';
		const effective = override !== '' ? override : defaultBody !== '' ? defaultBody : '—';

		currentEl.textContent = effective;
		updateStatus(row, override, defaultBody, effective);
		updateCharCount(row);
	}

	function reindexRows(list) {
		const rows = list.querySelectorAll('[data-gro-sms-templates-row]');
		rows.forEach(function (row, index) {
			row.querySelectorAll('input, textarea').forEach(function (el) {
				const name = el.getAttribute('name');
				if (!name) {
					return;
				}
				el.setAttribute(
					'name',
					name.replace(/growth_orchestrator_sms_templates\[[^\]]+]/, 'growth_orchestrator_sms_templates[' + index + ']')
				);
			});
		});
	}

	function bindCopyVars(root) {
		const copiedLabel = root.getAttribute('data-gro-copied-label') || 'Copied';

		root.closest('.gro-patterns-shell')?.addEventListener('click', function (ev) {
			const btn = ev.target.closest('[data-gro-copy-var]');
			if (!btn) {
				return;
			}
			const token = btn.getAttribute('data-gro-copy-var');
			if (!token) {
				return;
			}

			const done = function () {
				btn.classList.add('gro-patterns-var--copied');
				const prev = btn.getAttribute('title');
				btn.setAttribute('title', copiedLabel);
				window.setTimeout(function () {
					btn.classList.remove('gro-patterns-var--copied');
					if (prev) {
						btn.setAttribute('title', prev);
					}
				}, 1200);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(token).then(done).catch(function () {
					window.prompt('', token);
				});
			} else {
				window.prompt('', token);
				done();
			}
		});
	}

	function bindEditor(root) {
		const list = root.querySelector('[data-gro-sms-templates-rows]');
		const tpl = document.getElementById('gro-sms-template-row-blank');
		if (!list || !tpl) {
			return;
		}

		bindCopyVars(root);

		list.querySelectorAll('[data-gro-sms-templates-row]').forEach(updateRowCurrent);

		root.addEventListener('input', function (ev) {
			const row = ev.target.closest('[data-gro-sms-templates-row]');
			if (row && ev.target.matches('textarea, [data-gro-pattern-textarea]')) {
				updateRowCurrent(row);
			}
		});

		root.querySelector('[data-gro-sms-templates-add]')?.addEventListener('click', function () {
			const index = list.querySelectorAll('[data-gro-sms-templates-row]').length;
			const html = tpl.innerHTML.replace(/__INDEX__/g, String(index));
			const wrap = document.createElement('div');
			wrap.innerHTML = html.trim();
			const row = wrap.firstElementChild;
			if (row) {
				list.appendChild(row);
				updateRowCurrent(row);
				row.querySelector('textarea, [data-gro-pattern-textarea]')?.focus();
			}
		});

		root.addEventListener('click', function (ev) {
			const btn = ev.target.closest('[data-gro-sms-templates-remove]');
			if (!btn) {
				return;
			}
			const row = btn.closest('[data-gro-sms-templates-row]');
			if (row && window.confirm(btn.getAttribute('data-confirm') || 'Remove this pattern?')) {
				row.remove();
				reindexRows(list);
			}
		});
	}

	document.querySelectorAll('[data-gro-sms-templates]').forEach(bindEditor);
})();
