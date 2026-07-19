(function () {
	'use strict';

	function formToObject(form) {
		var fd = new FormData(form);
		var out = {};
		fd.forEach(function (value, key) {
			var m = key.match(/^config\[(.+)\]$/);
			if (m) {
				out.config = out.config || {};
				out.config[m[1]] = value;
				return;
			}
			if (key === 'active' || key === 'cron' || key === 'use_proxy' || key === 'auth') {
				out[key] = true;
				return;
			}
			if (key.slice(-2) === '[]') {
				var base = key.slice(0, -2);
				if (!Array.isArray(out[base])) {
					out[base] = [];
				}
				out[base].push(value);
				return;
			}
			out[key] = value;
		});
		['active', 'cron', 'use_proxy', 'auth'].forEach(function (k) {
			if (!(k in out)) {
				out[k] = false;
			}
		});
		var typeSelect = form.querySelector('select[name="template_type[]"]');
		if (typeSelect) {
			var types = [];
			Array.prototype.forEach.call(typeSelect.selectedOptions || [], function (opt) {
				if (opt.value) {
					types.push(opt.value);
				}
			});
			out.template_type = types;
		}
		return out;
	}

	function bindForm(selector, method) {
		var form = document.querySelector(selector);
		if (!form || !window.DevCraft || !DevCraft.Ajax) {
			return;
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (selector === '#repost-template-form') {
				syncConditions();
				if (typeof tinymce !== 'undefined' && tinymce.triggerSave) {
					tinymce.triggerSave();
				}
			}
			DevCraft.Ajax.post(method, formToObject(form)).then(function (res) {
				if (res && res.data && res.data.redirect) {
					window.location.href = res.data.redirect;
				}
			});
		});
	}

	function parseJsonAttr(el, attr, fallback) {
		try {
			var raw = el.getAttribute(attr) || '';
			if (!raw) {
				return fallback;
			}
			return JSON.parse(raw);
		} catch (e) {
			return fallback;
		}
	}

	function optionHtml(value, label, selected) {
		return '<option value="' + String(value).replace(/"/g, '&quot;') + '"' +
			(selected ? ' selected' : '') + '>' +
			String(label).replace(/</g, '&lt;') + '</option>';
	}

	function nameOptionsHtml(fields, source, selectedName) {
		var map = (fields && fields[source]) || {};
		var html = optionHtml('', '— поле —', !selectedName);
		Object.keys(map).forEach(function (key) {
			html += optionHtml(key, map[key], String(selectedName) === String(key));
		});
		if (selectedName && !(selectedName in map)) {
			html += optionHtml(selectedName, selectedName, true);
		}
		return html;
	}

	function createConditionRow(fields, cond) {
		cond = cond || {};
		var source = cond.source || 'post';
		var name = cond.name || '';
		var op = cond.op || '=';
		var value = cond.value != null ? String(cond.value) : '';
		var row = document.createElement('div');
		row.className = 'repost-cond-row d-flex flex-wrap flex-align-center gap-2 mb-2';
		row.innerHTML =
			'<select class="repost-cond-source input-small">' +
				optionHtml('post', 'post', source === 'post') +
				optionHtml('post_extras', 'post_extras', source === 'post_extras') +
				optionHtml('xfields', 'xfields', source === 'xfields') +
				optionHtml('category', 'category', source === 'category') +
			'</select>' +
			'<select class="repost-cond-name input-small">' + nameOptionsHtml(fields, source, name) + '</select>' +
			'<select class="repost-cond-op input-small">' +
				optionHtml('=', '=', op === '=') +
				optionHtml('!=', '!=', op === '!=') +
				optionHtml('like', 'like', op === 'like') +
			'</select>' +
			'<input type="text" class="repost-cond-value input-small" value="' +
				value.replace(/"/g, '&quot;') + '" placeholder="значение">' +
			'<button type="button" class="button alert small repost-cond-remove" title="Удалить">−</button>';
		return row;
	}

	function syncConditions() {
		var root = document.getElementById('repost-conditions');
		var hidden = document.getElementById('repost-condition-json');
		if (!root || !hidden) {
			return;
		}
		var rows = root.querySelectorAll('.repost-cond-row');
		var list = [];
		rows.forEach(function (row) {
			var sourceEl = row.querySelector('.repost-cond-source');
			var nameEl = row.querySelector('.repost-cond-name');
			var opEl = row.querySelector('.repost-cond-op');
			var valueEl = row.querySelector('.repost-cond-value');
			var name = nameEl ? nameEl.value : '';
			if (!name) {
				return;
			}
			list.push({
				source: sourceEl ? sourceEl.value : 'post',
				name: name,
				op: opEl ? opEl.value : '=',
				value: valueEl ? valueEl.value : ''
			});
		});
		hidden.value = JSON.stringify(list);
	}

	function initConditions() {
		var root = document.getElementById('repost-conditions');
		var rowsBox = document.getElementById('repost-cond-rows');
		var addBtn = document.getElementById('repost-cond-add');
		if (!root || !rowsBox) {
			return;
		}
		var fields = parseJsonAttr(root, 'data-fields', {});
		var conditions = parseJsonAttr(root, 'data-conditions', []);
		if (!Array.isArray(conditions)) {
			conditions = [];
		}

		function appendRow(cond) {
			rowsBox.appendChild(createConditionRow(fields, cond));
		}

		conditions.forEach(function (c) {
			if (c && typeof c === 'object') {
				appendRow(c);
			}
		});

		if (addBtn) {
			addBtn.addEventListener('click', function () {
				appendRow({ source: 'post', name: '', op: '=', value: '' });
			});
		}

		rowsBox.addEventListener('click', function (e) {
			var t = e.target;
			if (t && t.classList && t.classList.contains('repost-cond-remove')) {
				var row = t.closest('.repost-cond-row');
				if (row) {
					row.remove();
					syncConditions();
				}
			}
		});

		rowsBox.addEventListener('change', function (e) {
			var t = e.target;
			if (!t || !t.classList) {
				return;
			}
			if (t.classList.contains('repost-cond-source')) {
				var row = t.closest('.repost-cond-row');
				if (!row) {
					return;
				}
				var nameSel = row.querySelector('.repost-cond-name');
				if (nameSel) {
					nameSel.innerHTML = nameOptionsHtml(fields, t.value, '');
				}
			}
			syncConditions();
		});

		syncConditions();
	}

	function insertIntoEditor(code) {
		var ed = null;
		if (typeof tinymce !== 'undefined') {
			ed = tinymce.get('template') || tinymce.activeEditor;
			if (!ed) {
				var nodes = tinymce.editors || [];
				for (var i = 0; i < nodes.length; i++) {
					if (nodes[i] && nodes[i].getElement && nodes[i].getElement().classList.contains('dc-repost-editor')) {
						ed = nodes[i];
						break;
					}
				}
			}
		}
		if (ed && ed.insertContent) {
			ed.insertContent(code);
			return;
		}
		var ta = document.querySelector('textarea.dc-repost-editor, textarea[name="template"]');
		if (!ta) {
			return;
		}
		var start = ta.selectionStart || 0;
		var end = ta.selectionEnd || 0;
		var val = ta.value || '';
		ta.value = val.slice(0, start) + code + val.slice(end);
		ta.focus();
		var pos = start + code.length;
		if (ta.setSelectionRange) {
			ta.setSelectionRange(pos, pos);
		}
	}

	function toolbarForAllowed(tags) {
		var set = {};
		(tags || []).forEach(function (t) { set[String(t).toLowerCase()] = true; });
		var parts = [];
		if (set.b || set.strong) { parts.push('bold'); }
		if (set.i || set.em) { parts.push('italic'); }
		if (set.u || set.ins) { parts.push('underline'); }
		if (set.s || set.strike || set.del) { parts.push('strikethrough'); }
		if (set.a) { parts.push('link'); }
		if (set.code || set.pre) { parts.push('code'); }
		if (set.blockquote) { parts.push('blockquote'); }
		return parts.length ? parts.join(' ') : '';
	}

	function validElementsFromTags(tags) {
		if (!tags || !tags.length) {
			return '';
		}
		return tags.map(function (t) {
			t = String(t).toLowerCase();
			if (t === 'a') {
				return 'a[href|title]';
			}
			return t;
		}).join(',');
	}

	function constrainTinyMce() {
		var box = document.getElementById('repost-tag-chips');
		if (!box || typeof tinymce === 'undefined') {
			return;
		}
		var allowed = parseJsonAttr(box, 'data-allowed-html', []);
		var toolbar = toolbarForAllowed(allowed);
		var valid = validElementsFromTags(allowed);
		(tinymce.editors || []).forEach(function (ed) {
			if (!ed || !ed.getElement || !ed.getElement().classList.contains('dc-repost-editor')) {
				return;
			}
			try {
				if (valid !== undefined) {
					ed.settings.valid_elements = valid || '@[id]';
					ed.settings.extended_valid_elements = valid;
				}
				if (toolbar !== '') {
					ed.settings.toolbar = toolbar;
					var bar = ed.getContainer() && ed.getContainer().querySelector('.tox-toolbar__primary, .mce-toolbar-grp');
					if (bar && ed.theme && ed.theme.panel) {
						/* TinyMCE 4/5: пересоздать toolbar сложно — достаточно valid_elements */
					}
				}
			} catch (e) { /* ignore */ }
		});
	}

	function initTagChips() {
		var box = document.getElementById('repost-tag-chips');
		if (!box) {
			return;
		}
		var hints = parseJsonAttr(box, 'data-hints', []);
		box.innerHTML = '';
		hints.forEach(function (h) {
			if (!h || !h.code) {
				return;
			}
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'button small outline';
			btn.textContent = h.tag || h.code;
			btn.title = h.descr || h.code;
			btn.setAttribute('data-code', h.code);
			btn.addEventListener('click', function () {
				insertIntoEditor(h.code);
			});
			box.appendChild(btn);
		});
		constrainTinyMce();
	}

	window.DevCraftRepost = window.DevCraftRepost || {};
	window.DevCraftRepost.constrainTinyMce = constrainTinyMce;
	window.DevCraftRepost.insertIntoEditor = insertIntoEditor;

	document.addEventListener('DOMContentLoaded', function () {
		bindForm('#repost-connection-form', 'connection_save');
		bindForm('#repost-template-form', 'template_save');
		bindForm('#repost-proxy-form', 'proxy_save');
		initConditions();
		initTagChips();

		var connForm = document.getElementById('repost-connection-form');
		if (connForm) {
			var providerSelect = connForm.querySelector('select[name="provider"]');
			if (providerSelect) {
				providerSelect.addEventListener('change', function () {
					var idInput = connForm.querySelector('input[name="id"]');
					var id = idInput ? String(idInput.value || '0') : '0';
					var code = providerSelect.value || 'telegram';
					var url = '?mod=repost&action=edit_connection&provider=' + encodeURIComponent(code);
					if (id && id !== '0') {
						url += '&id=' + encodeURIComponent(id);
					}
					window.location.href = url;
				});
			}
		}

		document.querySelectorAll('[data-repost-delete]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var kind = btn.getAttribute('data-repost-delete');
				var id = btn.getAttribute('data-id');
				var method = kind + '_delete';
				if (!window.DevCraft || !DevCraft.Ajax) {
					return;
				}
				if (!window.confirm('Удалить?')) {
					return;
				}
				DevCraft.Ajax.post(method, { id: id }).then(function () {
					window.location.reload();
				});
			});
		});

		document.querySelectorAll('[data-repost-copy]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var kind = btn.getAttribute('data-repost-copy');
				var id = btn.getAttribute('data-id');
				var method = kind + '_copy';
				if (!window.DevCraft || !DevCraft.Ajax) {
					return;
				}
				DevCraft.Ajax.post(method, { id: id }).then(function () {
					window.location.reload();
				});
			});
		});

		document.querySelectorAll('[data-repost-toggle]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var kind = btn.getAttribute('data-repost-toggle');
				var id = btn.getAttribute('data-id');
				var method = kind + '_toggle';
				if (!window.DevCraft || !DevCraft.Ajax) {
					return;
				}
				DevCraft.Ajax.post(method, { id: id }).then(function () {
					window.location.reload();
				});
			});
		});

		document.querySelectorAll('[data-repost-cron]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var action = btn.getAttribute('data-repost-cron');
				var id = btn.getAttribute('data-id');
				var method = action === 'delete' ? 'cron_delete' : 'cron_send';
				var row = btn.closest('tr');
				if (!window.DevCraft || !DevCraft.Ajax) {
					return;
				}
				btn.disabled = true;
				DevCraft.Ajax.post(method, { id: id }).then(function (res) {
					var deleted = action === 'delete' || (res && res.data && res.data.deleted);
					if (deleted && row) {
						row.remove();
						var tbody = document.querySelector('.container-fluid table tbody');
						if (tbody && !tbody.querySelector('tr')) {
							var empty = document.createElement('tr');
							empty.innerHTML = '<td colspan="6">Очередь пуста</td>';
							tbody.appendChild(empty);
						}
					} else {
						btn.disabled = false;
					}
				}).catch(function () {
					btn.disabled = false;
				});
			});
		});

		var chatBtn = document.getElementById('repost-tg-chat');
		var testBtn = document.getElementById('repost-tg-test');
		var result = document.getElementById('repost-tg-result');
		var form = document.getElementById('repost-connection-form');

		function tgPayload() {
			var data = formToObject(form);
			return Object.assign({}, data.config || {}, { text: 'RePost test' });
		}

		if (chatBtn && form && window.DevCraft) {
			chatBtn.addEventListener('click', function () {
				DevCraft.Ajax.post('telegram_chat_id', tgPayload()).then(function (res) {
					if (result) {
						result.textContent = JSON.stringify((res && res.data) || res, null, 2);
					}
				});
			});
		}

		if (testBtn && form && window.DevCraft) {
			testBtn.addEventListener('click', function () {
				DevCraft.Ajax.post('telegram_test', tgPayload()).then(function (res) {
					if (result) {
						result.textContent = JSON.stringify((res && res.data) || res, null, 2);
					}
				});
			});
		}

		document.addEventListener('click', function (event) {
			var el = event.target.closest('[data-repost-copy-tag]');
			if (!el) {
				return;
			}
			event.preventDefault();
			var text = el.getAttribute('data-repost-copy-tag') || '';
			if (!text) {
				return;
			}
			function done() {
				if (window.DevCraft && DevCraft.Metro && typeof DevCraft.Metro.notifySuccess === 'function') {
					DevCraft.Metro.notifySuccess('OK', 'Скопировано');
				}
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done).catch(function () {
					window.prompt('Скопируйте тег', text);
				});
			} else {
				window.prompt('Скопируйте тег', text);
			}
		});
	});
})();
