(function () {
	if (!window.wp || !wp.element || !wp.components || !wp.components.Modal) {
		return;
	}

	var dataNode = document.getElementById('gro-sms-patterns-data');
	var root = document.getElementById('gro-sms-patterns');
	if (!dataNode || !root) {
		return;
	}

	var config = {};
	try {
		config = JSON.parse(dataNode.textContent || '{}');
	} catch (error) {
		config = {};
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SearchControl = wp.components.SearchControl;
	var Modal = wp.components.Modal;
	var ConfirmDialog = wp.components.__experimentalConfirmDialog;
	var labels = config.labels || {};

	function label(key, fallback) {
		return labels[key] || fallback;
	}

	function applySamples(text) {
		var samples = config.samples || {};
		return String(text || '').replace(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g, function (match, name) {
			return Object.prototype.hasOwnProperty.call(samples, name) ? samples[name] : match;
		});
	}

	function statusOf(row) {
		if (row.body !== '') {
			return label('custom', 'Custom');
		}
		if (row.defaultBody !== '') {
			return label('default', 'Default');
		}
		return label('empty', 'Not set');
	}

	function Patterns() {
		var rowsState = useState(Array.isArray(config.rows) ? config.rows : []);
		var rows = rowsState[0];
		var setRows = rowsState[1];
		var queryState = useState('');
		var query = queryState[0];
		var setQuery = queryState[1];
		var openState = useState(-1);
		var openIndex = openState[0];
		var setOpenIndex = openState[1];
		var removeState = useState(-1);
		var removeIndex = removeState[0];
		var setRemoveIndex = removeState[1];

		function update(index, patch) {
			setRows(rows.map(function (row, i) {
				return i === index ? Object.assign({}, row, patch) : row;
			}));
		}

		var visible = rows
			.map(function (row, index) {
				return { row: row, index: index };
			})
			.filter(function (item) {
				if (!query) {
					return true;
				}
				var hay = (item.row.label + ' ' + item.row.id + ' ' + item.row.body).toLowerCase();
				return hay.indexOf(query.toLowerCase()) !== -1;
			});

		var seenRows = useRef('');
		useEffect(function () {
			var signature = JSON.stringify(rows.map(function (row) {
				return { id: row.id, label: row.label, body: row.body };
			}));
			if (seenRows.current === '') {
				seenRows.current = signature;
				document.dispatchEvent(new CustomEvent('gro-patterns-ready'));
				return;
			}
			if (signature === seenRows.current) {
				return;
			}
			seenRows.current = signature;
			document.dispatchEvent(new CustomEvent('gro-panel-touch', { detail: { panel: 'patterns' } }));
		});

		var openRow = openIndex >= 0 ? rows[openIndex] : null;
		var effective = openRow ? (openRow.body !== '' ? openRow.body : openRow.defaultBody) : '';
		var count = openRow ? (openRow.body || '').length : 0;

		return el(
			'div',
			null,
			el('h2', { className: 'gro-panel-title' }, label('title', 'SMS message patterns')),
			el('p', { className: 'gro-panel-lede' }, label('intro', '')),
			el('div', { className: 'gro-sms-toolbar' }, el(SearchControl, {
				label: label('search', 'Search patterns'),
				value: query,
				onChange: setQuery,
				__nextHasNoMarginBottom: true,
			})),
			visible.length
				? el(
					'ul',
					{ className: 'gro-sms-list', role: 'list' },
					visible.map(function (item) {
						var row = item.row;
						var title = row.label || row.id || label('add', 'Pattern');
						return el(
							'li',
							{ key: item.index },
							el(
								'article',
								{ className: 'gro-item-card gro-item-card--closed' },
								el(
									'button',
									{
										type: 'button',
										className: 'gro-item-card__open',
										onClick: function () {
											setOpenIndex(item.index);
										},
									},
									el('span', { className: 'gro-item-card__index', 'aria-hidden': 'true' }, '#' + (item.index + 1)),
									el('span', { className: 'gro-item-card__field' }, title),
									el(
										'span',
										{ className: 'gro-item-card__meta' },
										el('span', { className: 'gro-item-card__meta-key' }, label('id', 'Pattern ID')),
										' ',
										el('strong', null, row.id || '—'),
										el('span', { className: 'gro-item-card__sep', 'aria-hidden': 'true' }, ' · '),
										el('span', { className: 'gro-item-card__meta-key' }, label('body', 'Message')),
										' ',
										el('strong', null, statusOf(row))
									)
								),
								row.builtin
									? null
									: el(Button, {
										className: 'gro-item-card__delete',
										icon: 'trash',
										label: label('remove', 'Remove pattern'),
										showTooltip: true,
										size: 'compact',
										isDestructive: true,
										variant: 'tertiary',
										onClick: function () {
											setRemoveIndex(item.index);
										},
									})
							)
						);
					})
				)
				: (query ? el('p', { className: 'description' }, label('search', 'Search patterns')) : null),
			el(Button, {
				variant: 'primary',
				icon: 'plus',
				className: 'gro-sms-add',
				onClick: function () {
					var next = rows.concat([{ id: '', label: '', body: '', builtin: false, defaultBody: '' }]);
					setRows(next);
					setOpenIndex(next.length - 1);
				},
			}, label('add', 'Add pattern')),
			openRow
				? el(
					Modal,
					{
						title: (openRow.label || openRow.id || label('add', 'Pattern')) + ' #' + (openIndex + 1),
						onRequestClose: function () {
							setOpenIndex(-1);
						},
						size: 'large',
						className: 'gro-editor-modal',
					},
					el(
						'div',
						{ className: 'gro-editor-modal__body' },
						el(
							'div',
							{ className: 'gro-action-form' },
							el(
								'section',
								{ className: 'gro-action-form__section' },
								el('h3', { className: 'gro-action-form__heading' }, label('name', 'Title')),
								el(
									'div',
									{ className: 'gro-action-form__body' },
									el(TextControl, {
										label: label('name', 'Title'),
										value: openRow.label,
										onChange: function (value) {
											update(openIndex, { label: value });
										},
										__nextHasNoMarginBottom: true,
										__next40pxDefaultSize: true,
									}),
									el(TextControl, {
										label: label('id', 'Pattern ID'),
										value: openRow.id,
										disabled: !!openRow.builtin,
										onChange: function (value) {
											update(openIndex, { id: value });
										},
										__nextHasNoMarginBottom: true,
										__next40pxDefaultSize: true,
									})
								)
							),
							el(
								'section',
								{ className: 'gro-action-form__section' },
								el('h3', { className: 'gro-action-form__heading' }, label('body', 'Your message (override)')),
								el(
									'div',
									{ className: 'gro-action-form__body' },
									el(TextareaControl, {
										label: label('body', 'Your message (override)'),
										hideLabelFromVision: true,
										value: openRow.body,
										onChange: function (value) {
											update(openIndex, { body: value });
										},
										rows: 5,
										__nextHasNoMarginBottom: true,
									}),
									el(
										'div',
										{ className: 'gro-sms-tokens' },
										el('span', { className: 'gro-sms-tokens__label' }, label('placeholders', 'Placeholders')),
										el(
											'div',
											{ className: 'gro-sms-tokens__list' },
											(config.variables || []).map(function (name) {
												var token = '{' + name + '}';
												return el(
													Button,
													{
														key: name,
														variant: 'secondary',
														size: 'compact',
														onClick: function () {
															update(openIndex, { body: (openRow.body || '') + token });
														},
													},
													token
												);
											})
										)
									),
									el('p', { className: 'gro-sms-meta' }, label('emptyHint', '') + ' · ' + label('chars', '%1$d / %2$d').replace('%1$d', String(count)).replace('%2$d', String(config.maxBody || 500))),
									el(
										'div',
										{ className: 'gro-sms-preview' },
										el('span', { className: 'gro-sms-preview__label' }, label('preview', 'Preview')),
										el('p', { className: 'gro-sms-preview__text' }, applySamples(effective) || '—')
									)
								)
							)
						)
					)
				)
				: null,
			ConfirmDialog
				? el(ConfirmDialog, {
					isOpen: removeIndex >= 0,
					onConfirm: function () {
						var index = removeIndex;
						setRemoveIndex(-1);
						if (openIndex === index) {
							setOpenIndex(-1);
						} else if (openIndex > index) {
							setOpenIndex(openIndex - 1);
						}
						setRows(rows.filter(function (_, i) {
							return i !== index;
						}));
					},
					onCancel: function () {
						setRemoveIndex(-1);
					},
					confirmButtonText: label('remove', 'Remove pattern'),
				}, label('confirmRemove', 'Remove this pattern?'))
				: null,
			rows.map(function (row, index) {
				var name = (config.option || 'bertanex_flow_sms_templates') + '[' + index + ']';
				return el(
					'div',
					{ key: 'hidden-' + index },
					el('input', { type: 'hidden', name: name + '[id]', value: row.id }),
					el('input', { type: 'hidden', name: name + '[label]', value: row.label }),
					el('input', { type: 'hidden', name: name + '[body]', value: row.body })
				);
			})
		);
	}

	if (wp.element.createRoot) {
		wp.element.createRoot(root).render(el(Patterns, null));
		return;
	}
	wp.element.render(el(Patterns, null), root);
})();
