(function () {
	var config = window.groEmailEditor || { templates: {}, variables: [], restBase: 'gro_email', subjectKey: '_gro_email_subject' };
	if (!window.wp || !wp.plugins || !wp.element || !wp.editor) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var Panel = wp.editor.PluginDocumentSettingPanel;
	var TextControl = wp.components && wp.components.TextControl;
	var Button = wp.components && wp.components.Button;
	var Flex = wp.components && wp.components.Flex;

	function currentSlug() {
		var params = new URLSearchParams(window.location.search);
		var path = params.get('p') || '';
		var marker = '//';
		var at = path.lastIndexOf(marker);
		if (at === -1) {
			return '';
		}
		try {
			return decodeURIComponent(path.slice(at + marker.length));
		} catch (error) {
			return path.slice(at + marker.length);
		}
	}

	function insertToken(token) {
		var blocks = wp.blocks;
		var dispatch = wp.data.dispatch('core/block-editor');
		if (!blocks || !dispatch) {
			return;
		}
		dispatch.insertBlocks(blocks.createBlock('core/paragraph', { content: token }));
	}

	function EmailFields() {
		var slug = currentSlug();
		var isEmail = slug === 'email' || slug.indexOf('email-') === 0;
		var postId = config.templates[slug] || 0;
		var subjectState = useState('');
		var subject = subjectState[0];
		var setSubject = subjectState[1];

		useEffect(function () {
			if (!postId || !wp.apiFetch) {
				return undefined;
			}
			var active = true;
			wp.apiFetch({ path: '/wp/v2/' + config.restBase + '/' + postId }).then(function (post) {
				if (!active) {
					return;
				}
				var meta = post && post.meta ? post.meta : {};
				setSubject(meta[config.subjectKey] || '');
			});
			return function () {
				active = false;
			};
		}, [postId]);

		if (!isEmail) {
			return null;
		}

		function saveSubject(value) {
			setSubject(value);
			if (!postId || !wp.apiFetch) {
				return;
			}
			var meta = {};
			meta[config.subjectKey] = value;
			wp.apiFetch({
				path: '/wp/v2/' + config.restBase + '/' + postId,
				method: 'POST',
				data: { meta: meta },
			});
		}

		var variables = Array.isArray(config.variables) ? config.variables : [];

		return el(
			'div',
			null,
			postId && TextControl
				? el(TextControl, {
					label: 'Subject',
					value: subject,
					onChange: saveSubject,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				})
				: el('p', null, 'Subject is saved on templates created from Rules.'),
			Flex
				? el(
					Flex,
					{ gap: 2, wrap: true, style: { marginTop: 12 } },
					variables.map(function (name) {
						var token = '{' + name + '}';
						return el(
							Button,
							{
								key: name,
								variant: 'secondary',
								size: 'compact',
								onClick: function () {
									insertToken(token);
								},
							},
							token
						);
					})
				)
				: null
		);
	}

	function EmailPanel() {
		var slug = currentSlug();
		var isEmail = slug === 'email' || slug.indexOf('email-') === 0;
		if (!isEmail) {
			return null;
		}
		var pieces = [];
		if (Panel) {
			pieces.push(el(Panel, { name: 'gro-email-template', title: 'Email', key: 'panel' }, el(EmailFields, null)));
		}
		if (wp.editor.PluginSidebar) {
			pieces.push(
				el(
					wp.editor.PluginSidebar,
					{ name: 'gro-email-template-side', title: 'Email', icon: 'email', key: 'side' },
					el(EmailFields, null)
				)
			);
		}
		return el(wp.element.Fragment, null, pieces);
	}

	wp.plugins.registerPlugin('gro-email-template', { render: EmailPanel });
})();
