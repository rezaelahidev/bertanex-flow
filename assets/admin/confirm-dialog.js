(function () {
	if (!window.wp || !wp.element || !wp.components || !wp.components.__experimentalConfirmDialog) {
		return;
	}

	var el = wp.element.createElement;
	var ConfirmDialog = wp.components.__experimentalConfirmDialog;
	var host = document.getElementById('gro-confirm-host');
	if (!host) {
		host = document.createElement('div');
		host.id = 'gro-confirm-host';
		document.body.appendChild(host);
	}
	var root = wp.element.createRoot ? wp.element.createRoot(host) : null;

	function PhraseConfirm(props) {
		var typedState = wp.element.useState('');
		var typed = typedState[0];
		var setTyped = typedState[1];
		var Modal = wp.components.Modal;
		var TextControl = wp.components.TextControl;
		var Button = wp.components.Button;

		return el(
			Modal,
			{
				title: props.confirmLabel,
				onRequestClose: props.onCancel,
				className: 'gro-editor-modal',
				size: 'medium',
			},
			el(
				'div',
				{ className: 'gro-editor-modal__body' },
				el('p', null, props.message),
				el(TextControl, {
					label: props.phraseLabel,
					value: typed,
					onChange: setTyped,
					autoComplete: 'off',
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				})
			),
			el(
				'div',
				{ className: 'gro-editor-modal__footer' },
				el(Button, {
					variant: 'primary',
					isDestructive: true,
					disabled: typed !== props.phrase,
					onClick: function () {
						if (typed === props.phrase) {
							props.onConfirm();
						}
					},
				}, props.confirmLabel)
			)
		);
	}

	function ask(form) {
		var message = form.getAttribute('data-gro-confirm') || '';
		var confirmLabel = form.getAttribute('data-gro-confirm-ok') || 'OK';
		var phrase = form.getAttribute('data-gro-confirm-phrase') || '';
		var phraseLabel = form.getAttribute('data-gro-confirm-phrase-label') || '';

		function close() {
			if (root) {
				root.render(null);
			}
		}

		function confirm() {
			close();
			if (phrase) {
				var field = form.querySelector('input[name="gro_reset_phrase"]');
				if (field) {
					field.value = phrase;
				}
			}
			form.dataset.groConfirmed = '1';
			form.submit();
		}

		var dialog = phrase
			? el(PhraseConfirm, {
				message: message,
				confirmLabel: confirmLabel,
				phrase: phrase,
				phraseLabel: phraseLabel,
				onConfirm: confirm,
				onCancel: close,
			})
			: el(ConfirmDialog, {
				isOpen: true,
				confirmButtonText: confirmLabel,
				onConfirm: confirm,
				onCancel: close,
			}, message);

		if (root) {
			root.render(dialog);
			return;
		}
		wp.element.render(dialog, host);
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.getAttribute || !form.getAttribute('data-gro-confirm')) {
			return;
		}
		if (form.dataset.groConfirmed === '1') {
			return;
		}
		event.preventDefault();
		ask(form);
	}, true);
})();
