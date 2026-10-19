(function () {
	var config = window.groEmailPicker || { active: 'wordpress', templates: [], routes: {}, labels: {} };
	if (!window.wp || !wp.element || !wp.components || !wp.element.createRoot && !wp.element.render) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var useEffect = wp.element.useEffect;
	var Card = wp.components.Card;
	var CardHeader = wp.components.CardHeader;
	var CardBody = wp.components.CardBody;
	var CardFooter = wp.components.CardFooter;
	var Button = wp.components.Button;
	var Flex = wp.components.Flex;
	var FlexBlock = wp.components.FlexBlock;
	var Spinner = wp.components.Spinner;
	var ConfirmDialog = wp.components.__experimentalConfirmDialog;
	var Grid = wp.components.__experimentalGrid;
	var labels = config.labels || {};

	function label(key, fallback) {
		return labels[key] || fallback;
	}

	function escapeHtml(value) {
		return String(value).replace(/[&<>"']/g, function (char) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
		});
	}

	function previewDocument(template) {
		var subject = template.subject
			? '<p style="margin:0 0 16px;font-weight:600">' + escapeHtml(template.subject) + '</p>'
			: '';
		return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
			+ 'html,body{margin:0;background:#fff;color:#1e1e1e;}'
			+ 'body{padding:4px 2px 8px;font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
			+ 'img,svg,video{max-width:100%;height:auto;}'
			+ 'h1,h2,h3,h4,p,ul,ol,figure{margin:0 0 12px;}'
			+ 'h1,h2,h3,h4{line-height:1.3;font-weight:600;}'
			+ '.wp-block-group,.wp-block-columns{margin:0;}'
			+ '</style></head><body>' + subject + (template.previewHtml || '') + '</body></html>';
	}

	function fitFrame(frame) {
		var doc = frame && frame.contentDocument;
		var shell = frame && frame.parentNode;
		if (!doc || !doc.documentElement || !shell) {
			return;
		}
		var height = doc.documentElement.scrollHeight;
		if (height < 1) {
			return;
		}
		var max = 180;
		var scale = height > max ? max / height : 1;
		frame.style.width = (100 / scale) + '%';
		frame.style.height = height + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		frame.style.transformOrigin = 'top left';
		shell.style.height = Math.round(height * scale) + 'px';
		Array.prototype.forEach.call(doc.images || [], function (image) {
			if (!image.complete) {
				image.addEventListener('load', function () {
					fitFrame(frame);
				}, { once: true });
			}
		});
	}

	function refitPreviews() {
		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(function () {
				Array.prototype.forEach.call(document.querySelectorAll('#gro-email-picker iframe'), fitFrame);
			});
		});
	}

	function EmailPreview(props) {
		var frameRef = useRef(null);
		useEffect(function () {
			var frame = frameRef.current;
			if (!frame) {
				return undefined;
			}
			fitFrame(frame);
			if (typeof IntersectionObserver !== 'function') {
				return undefined;
			}
			var observer = new IntersectionObserver(function (entries) {
				if (entries.some(function (entry) { return entry.isIntersecting; })) {
					refitPreviews();
				}
			});
			observer.observe(frame);
			return function () {
				observer.disconnect();
			};
		}, [props.html]);
		return el(
			'div',
			{ className: 'gro-email-preview', style: { overflow: 'hidden', height: '180px', border: '1px solid #dcdcde', borderRadius: '2px', background: '#fff' } },
			el('iframe', {
				ref: frameRef,
				title: props.title,
				sandbox: 'allow-same-origin',
				scrolling: 'no',
				srcDoc: props.html,
				onLoad: function (event) {
					fitFrame(event.target);
				},
				style: {
					display: 'block',
					width: '100%',
					height: '1px',
					border: 0,
					background: '#fff',
					pointerEvents: 'none',
				},
			})
		);
	}

	function Picker() {
		var templatesState = useState(config.templates || []);
		var templates = templatesState[0];
		var setTemplates = templatesState[1];
		var activeState = useState(config.active || 'wordpress');
		var active = activeState[0];
		var setActive = activeState[1];
		var busyState = useState('');
		var busy = busyState[0];
		var setBusy = busyState[1];
		var errorState = useState('');
		var error = errorState[0];
		var setError = errorState[1];
		var pendingState = useState(null);
		var pendingDelete = pendingState[0];
		var setPendingDelete = pendingState[1];

		function applyPayload(payload) {
			if (!payload) {
				return;
			}
			if (Array.isArray(payload.templates)) {
				setTemplates(payload.templates);
			}
			if (typeof payload.active === 'string') {
				setActive(payload.active);
			}
		}

		function selectTemplate(id) {
			if (busy || id === active) {
				return;
			}
			setBusy(id);
			setError('');
			wp.apiFetch({
				path: config.routes.active,
				method: 'POST',
				data: { id: id },
			})
				.then(applyPayload)
				.catch(function (err) {
					setError((err && err.message) || label('saveError', 'Could not save the template choice.'));
				})
				.finally(function () {
					setBusy('');
				});
		}

		function addTemplate() {
			setBusy('add');
			setError('');
			wp.apiFetch({
				path: config.routes.create,
				method: 'POST',
				data: { title: '' },
			})
				.then(function (payload) {
					if (payload && payload.designUrl) {
						window.location.href = payload.designUrl;
						return;
					}
					applyPayload(payload);
				})
				.catch(function (err) {
					setError((err && err.message) || label('addError', 'Could not add the template.'));
					setBusy('');
				});
		}

		function removeTemplate(template) {
			setPendingDelete(null);
			setBusy(template.id);
			setError('');
			wp.apiFetch({
				path: config.routes.remove + '/' + template.postId,
				method: 'DELETE',
			})
				.then(applyPayload)
				.catch(function (err) {
					setError((err && err.message) || label('deleteError', 'Could not delete the template.'));
				})
				.finally(function () {
					setBusy('');
				});
		}

		var cards = templates.map(function (template) {
			var selected = template.id === active;
			var card = el(
				Card,
				{
					key: template.id,
					size: 'small',
					className: selected ? 'is-selected' : undefined,
				},
				el(
					CardHeader,
					null,
					el(
						Flex,
						{ align: 'center', justify: 'space-between', gap: 2 },
						el(FlexBlock, null, el('strong', null, template.title)),
						selected ? el(Button, { variant: 'primary', isPressed: true, size: 'compact' }, label('selected', 'Selected')) : null
					)
				),
				el(
					CardBody,
					null,
					el(EmailPreview, {
						title: template.title,
						html: previewDocument(template),
					})
				),
				el(
					CardFooter,
					null,
					el(
						Flex,
						{ justify: 'flex-start', gap: 2, wrap: true },
						el(
							Button,
							{
								variant: selected ? 'primary' : 'secondary',
								isBusy: busy === template.id,
								disabled: busy !== '' && busy !== template.id,
								onClick: function () {
									selectTemplate(template.id);
								},
							},
							selected ? label('inUse', 'In use') : label('use', 'Use this template')
						),
						template.designUrl
							? el(Button, { variant: 'tertiary', href: template.designUrl }, label('design', 'Design'))
							: null,
						template.postId
							? el(
								Button,
								{
									variant: 'tertiary',
									isDestructive: true,
									disabled: busy !== '',
									onClick: function (event) {
										event.preventDefault();
										setPendingDelete(template);
									},
								},
								label('delete', 'Delete')
							)
							: null
					)
				)
			);
			return Grid ? el('div', { key: template.id }, card) : card;
		});

		return el(
			'div',
			{ className: 'gro-panel' },
			el('h2', { className: 'gro-panel-title' }, label('heading', 'Email templates')),
			el('p', { className: 'gro-panel-lede' }, label('intro', 'WordPress plain email is used unless you choose a designed template.')),
			error ? el('p', { className: 'gro-panel-error' }, error) : null,
			el('div', { className: 'gro-panel-toolbar' }, el(Button, {
				variant: 'primary',
				isBusy: busy === 'add',
				disabled: busy !== '',
				onClick: addTemplate,
			}, label('add', 'Add email template'))),
			busy && templates.length === 0 ? el(Spinner, null) : null,
			el('div', { className: 'gro-email-grid' }, cards),
			ConfirmDialog
				? el(ConfirmDialog, {
					isOpen: !!pendingDelete,
					onConfirm: function () {
						if (pendingDelete) {
							removeTemplate(pendingDelete);
						}
					},
					onCancel: function () {
						setPendingDelete(null);
					},
					confirmButtonText: label('delete', 'Delete'),
				}, label('confirmDelete', 'Delete this email template?'))
				: null
		);
	}

	var root = document.getElementById('gro-email-picker');
	if (!root) {
		return;
	}
	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!target || !target.closest || !target.closest('[data-gro-tab="email"]')) {
			return;
		}
		refitPreviews();
	});
	if (wp.element.createRoot) {
		wp.element.createRoot(root).render(el(Picker, null));
		return;
	}
	wp.element.render(el(Picker, null), root);
})();
