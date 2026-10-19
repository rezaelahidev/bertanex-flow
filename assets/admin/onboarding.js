(function () {
	var config = window.groOnboarding || { labels: {}, providers: [], templates: [] };
	if (!window.wp || !wp.element || !wp.components || !wp.components.Modal) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var Modal = wp.components.Modal;
	var Button = wp.components.Button;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var labels = config.labels || {};

	function label(key, fallback) {
		return labels[key] || fallback;
	}

	function post(data) {
		var body = new URLSearchParams();
		body.set('action', 'gro_onboarding_save');
		body.set('nonce', config.nonce || '');
		Object.keys(data).forEach(function (key) {
			body.set(key, data[key] == null ? '' : String(data[key]));
		});
		return window.fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		}).then(function (response) {
			return response.json();
		});
	}

	var steps = [
		{ key: 'welcome', name: label('stepWelcome', 'Welcome') },
		{ key: 'sms', name: label('smsTitle', 'SMS delivery') },
		{ key: 'email', name: label('emailTitle', 'Email template') },
	];

	function Setup() {
		var openState = useState(true);
		var open = openState[0];
		var setOpen = openState[1];
		var stepState = useState(0);
		var step = stepState[0];
		var setStep = stepState[1];
		var providerState = useState(config.provider || 'auto');
		var provider = providerState[0];
		var setProvider = providerState[1];
		var usernameState = useState(config.username || '');
		var username = usernameState[0];
		var setUsername = usernameState[1];
		var passwordState = useState('');
		var password = passwordState[0];
		var setPassword = passwordState[1];
		var fromState = useState(config.from || '');
		var from = fromState[0];
		var setFrom = fromState[1];
		var emailState = useState(config.email || 'wordpress');
		var email = emailState[0];
		var setEmail = emailState[1];
		var errorState = useState('');
		var error = errorState[0];
		var setError = errorState[1];
		var savingState = useState(false);
		var saving = savingState[0];
		var setSaving = savingState[1];

		function payload(finish) {
			return {
				provider: provider,
				username: username,
				password: password,
				from: from,
				email: email,
				finish: finish ? '1' : '0',
			};
		}

		function close(finish) {
			if (saving) {
				return;
			}
			setError('');
			setSaving(true);
			post(payload(finish))
				.then(function (result) {
					if (!result || !result.success) {
						setSaving(false);
						setError(label('saveError', 'Could not save the setup.'));
						return;
					}
					setOpen(false);
				})
				.catch(function () {
					setSaving(false);
					setError(label('saveError', 'Could not save the setup.'));
				});
		}

		if (!open) {
			return null;
		}

		var panel;
		if (step === 0) {
			panel = el(
				'div',
				null,
				el('p', { className: 'gro-wizard__lead' }, label('welcomeTitle', 'Welcome to Bertanex Flow')),
				el('p', { className: 'gro-wizard__body' }, label('welcomeBody', 'Set how messages are delivered. You can change these later in Configuration and Email templates.')),
				el(
					'ol',
					{ className: 'gro-wizard__points' },
					el('li', null, el('strong', null, label('smsTitle', 'SMS delivery')), el('span', null, label('smsSummary', 'Choose the provider, username, and sender line.'))),
					el('li', null, el('strong', null, label('emailTitle', 'Email template')), el('span', null, label('emailSummary', 'Choose the layout used for automation emails.')))
				)
			);
		} else if (step === 1) {
			panel = el(
				'div',
				{ className: 'gro-wizard__fields' },
				el('p', { className: 'gro-wizard__body' }, label('smsSummary', 'Choose the provider, username, and sender line.')),
				el(SelectControl, {
					label: label('provider', 'Provider'),
					value: provider,
					options: config.providers || [],
					onChange: setProvider,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(TextControl, {
					label: label('username', 'Username'),
					value: username,
					onChange: setUsername,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(TextControl, {
					label: label('password', 'Password'),
					type: 'password',
					value: password,
					help: label('passwordHint', 'Leave blank to keep the current password.'),
					onChange: setPassword,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(TextControl, {
					label: label('from', 'Sender line (from)'),
					value: from,
					onChange: setFrom,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				})
			);
		} else {
			panel = el(
				'div',
				{ className: 'gro-wizard__fields' },
				el('p', { className: 'gro-wizard__body' }, label('emailHelp', 'WordPress plain email is used unless you choose a designed template.')),
				el(SelectControl, {
					label: label('template', 'Template'),
					value: email,
					options: (config.templates || []).map(function (template) {
						return { label: template.title, value: template.id };
					}),
					onChange: setEmail,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				})
			);
		}

		var last = steps.length - 1;

		return el(
			Modal,
			{
				title: label('setupTitle', 'Set up Flow'),
				className: 'gro-editor-modal gro-wizard',
				size: 'medium',
				isDismissible: true,
				onRequestClose: function () {
					close(true);
				},
			},
			el(
				'div',
				{ className: 'gro-wizard__layout' },
			el(
				'div',
				{ className: 'gro-editor-modal__body' },
				el(
					'ol',
					{ className: 'gro-wizard__steps' },
					steps.map(function (item, index) {
						var state = index < step ? 'is-complete' : index === step ? 'is-current' : '';
						return el(
							'li',
							{ key: item.key, className: state },
							el(
								'button',
								{
									type: 'button',
									className: 'gro-wizard__step',
									'aria-current': index === step ? 'step' : undefined,
									disabled: saving || index > step,
									onClick: function () {
										if (index <= step) {
											setError('');
											setStep(index);
										}
									},
								},
								el('span', { className: 'gro-wizard__index', 'aria-hidden': 'true' }, index < step ? '✓' : String(index + 1)),
								el('span', { className: 'gro-wizard__name' }, item.name)
							)
						);
					})
				),
				panel
			),
			error ? el('p', { className: 'gro-wizard__error', role: 'alert' }, error) : null,
			el(
				'div',
				{ className: 'gro-editor-modal__footer' },
				step > 0
					? el(
							Button,
							{
								variant: 'secondary',
								disabled: saving,
								onClick: function () {
									setError('');
									setStep(step - 1);
								},
							},
							label('back', 'Back')
						)
					: el('span', null),
				el(
					Button,
					{
						variant: 'primary',
						isBusy: saving,
						disabled: saving,
						onClick: function () {
							if (step < last) {
								setError('');
								setStep(step + 1);
								return;
							}
							close(true);
						},
					},
					saving ? label('saving', 'Saving…') : step < last ? label('next', 'Next') : label('finish', 'Finish')
				)
			)
			)
		);
	}

	var root = document.getElementById('gro-onboarding');
	if (!root && document.body) {
		root = document.createElement('div');
		root.id = 'gro-onboarding';
		document.body.appendChild(root);
	}
	if (!root) {
		return;
	}
	if (wp.element.createRoot) {
		wp.element.createRoot(root).render(el(Setup, null));
		return;
	}
	wp.element.render(el(Setup, null), root);
})();
