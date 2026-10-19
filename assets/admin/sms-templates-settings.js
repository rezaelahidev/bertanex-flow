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

	function truncateSnippet(text, maxLen) {
		if (!text || text === '—') {
			return '—';
		}
		if (text.length <= maxLen) {
			return text;
		}
		return text.slice(0, maxLen) + '…';
	}

	function updateAccordionSummary(row) {
		const titleEl = row.querySelector('[data-gro-pattern-accordion-title]');
		const snippetEl = row.querySelector('[data-gro-pattern-snippet]');
		const labelInput = row.querySelector('[data-gro-pattern-label-input]');
		const idInput = row.querySelector('[data-gro-pattern-id-input]');
		const idCode = row.querySelector('.gro-pattern-accordion__id');
		const currentEl = row.querySelector('[data-gro-sms-current]');

		if (titleEl && labelInput) {
			const label = labelInput.value.trim();
			const id = idInput ? idInput.value.trim() : idCode ? idCode.textContent.trim() : '';
			titleEl.textContent = label !== '' ? label : id !== '' ? id : titleEl.getAttribute('data-gro-fallback-title') || '…';
		}

		if (snippetEl && currentEl) {
			snippetEl.textContent = truncateSnippet(currentEl.textContent.trim(), 72);
		}
	}

	function setAccordionOpen(row, open) {
		const trigger = row.querySelector('[data-gro-pattern-accordion-toggle]');
		const panel = row.querySelector('.gro-pattern-accordion__panel');
		if (!trigger || !panel) {
			return;
		}
		row.classList.toggle('is-open', open);
		trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
		panel.hidden = !open;
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
		updateAccordionSummary(row);
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
					name.replace(/bertanex_flow_sms_templates\[[^\]]+]/, 'bertanex_flow_sms_templates[' + index + ']')
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

	function bindAccordion(root) {
		root.addEventListener('click', function (ev) {
			const toggle = ev.target.closest('[data-gro-pattern-accordion-toggle]');
			if (toggle) {
				const row = toggle.closest('[data-gro-pattern-accordion]');
				if (row) {
					setAccordionOpen(row, !row.classList.contains('is-open'));
				}
				return;
			}

			if (ev.target.closest('[data-gro-sms-templates-remove]')) {
				ev.stopPropagation();
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
		bindAccordion(root);

		list.querySelectorAll('[data-gro-sms-templates-row]').forEach(function (row) {
			const titleEl = row.querySelector('[data-gro-pattern-accordion-title]');
			if (titleEl && !titleEl.getAttribute('data-gro-fallback-title')) {
				titleEl.setAttribute('data-gro-fallback-title', titleEl.textContent);
			}
			updateRowCurrent(row);
		});

		root.addEventListener('input', function (ev) {
			const row = ev.target.closest('[data-gro-sms-templates-row]');
			if (!row) {
				return;
			}
			if (ev.target.matches('textarea, [data-gro-pattern-textarea]')) {
				updateRowCurrent(row);
			}
			if (ev.target.matches('[data-gro-pattern-label-input], [data-gro-pattern-id-input]')) {
				updateAccordionSummary(row);
				const idInput = row.querySelector('[data-gro-pattern-id-input]');
				const idCode = row.querySelector('.gro-pattern-accordion__id');
				if (idInput && idCode) {
					idCode.textContent = idInput.value.trim();
				}
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
				setAccordionOpen(row, true);
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
