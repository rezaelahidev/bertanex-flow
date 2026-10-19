(function () {
	var button = document.getElementById('gro-sidebar-save');
	if (!button) {
		return;
	}

	var labels = {
		save: button.getAttribute('data-label-save') || 'Save',
		saving: button.getAttribute('data-label-saving') || 'Saving…',
		saved: button.getAttribute('data-label-saved') || 'Saved',
	};
	var baselines = { patterns: '', config: '' };
	var dirty = { patterns: false, config: false };
	var rules = { dirty: false, saving: false };

	function serialize(form) {
		if (!form) {
			return '';
		}
		return new URLSearchParams(new FormData(form)).toString();
	}

	function formFor(panel) {
		return document.querySelector(panel === 'patterns' ? '.gro-patterns-form' : '.gro-config-form');
	}

	function activeTab() {
		var current = document.querySelector('[data-gro-tab][aria-current="true"]');
		return current ? current.getAttribute('data-gro-tab') : 'visual';
	}

	function capture(panel) {
		baselines[panel] = serialize(formFor(panel));
		dirty[panel] = false;
	}

	function refresh(panel) {
		dirty[panel] = serialize(formFor(panel)) !== baselines[panel];
		paint();
	}

	function paint() {
		var tab = activeTab();
		var mode = 'saved';
		if (tab === 'visual') {
			if (rules.saving) {
				mode = 'saving';
			} else if (rules.dirty) {
				mode = 'save';
			}
		} else if ((tab === 'patterns' || tab === 'config') && dirty[tab]) {
			mode = 'save';
		}

		button.classList.toggle('is-primary', mode === 'save');
		button.classList.toggle('is-busy', mode === 'saving');
		button.setAttribute('aria-disabled', mode === 'save' ? 'false' : 'true');
		button.replaceChildren();
		if (mode === 'saved') {
			var icon = document.createElement('span');
			icon.className = 'dashicons dashicons-yes';
			icon.setAttribute('aria-hidden', 'true');
			button.appendChild(icon);
		}
		button.appendChild(document.createTextNode(labels[mode]));
	}

	document.addEventListener('gro-patterns-ready', function () {
		capture('patterns');
		paint();
	});

	document.addEventListener('gro-panel-touch', function (event) {
		var panel = event.detail && event.detail.panel;
		if (panel === 'patterns' || panel === 'config') {
			refresh(panel);
		}
	});

	document.addEventListener('gro-rules-state', function (event) {
		rules.dirty = !!(event.detail && event.detail.dirty);
		rules.saving = !!(event.detail && event.detail.saving);
		paint();
	});

	var tabs = document.querySelector('.gro-tabs');
	if (tabs) {
		tabs.addEventListener('click', function () {
			window.setTimeout(paint, 0);
		});
		new MutationObserver(paint).observe(tabs, {
			attributes: true,
			subtree: true,
			attributeFilter: ['aria-current'],
		});
	}

	var configForm = formFor('config');
	if (configForm) {
		capture('config');
		configForm.addEventListener('input', function () {
			refresh('config');
		});
		configForm.addEventListener('change', function () {
			refresh('config');
		});
	}

	button.addEventListener('click', function () {
		if (button.getAttribute('aria-disabled') === 'true') {
			return;
		}
		var tab = activeTab();
		if (tab === 'visual') {
			document.dispatchEvent(new CustomEvent('gro-sidebar-save'));
			return;
		}
		var form = formFor(tab);
		if (form && typeof form.requestSubmit === 'function') {
			form.requestSubmit();
		}
	});

	paint();
})();
