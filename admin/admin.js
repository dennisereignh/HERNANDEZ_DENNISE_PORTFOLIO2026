/* Admin dashboard. All user/visitor text is inserted with textContent (never innerHTML) to prevent XSS. */
(function () {
  'use strict';

  var ICONS = window.PORTFOLIO_ICONS || {};
  var CSRF = document.querySelector('meta[name="csrf"]').content;
  var CATS = { web: 'Web', python: 'Python', design: 'Design', art: 'Art', network: 'Networking', data: 'Data' };
  var app = document.getElementById('app');
  var toastEl = document.getElementById('toast');
  var state = { data: null, tab: 'projects', editing: {}, selMsg: null };
  var toastTimer;

  /* ---------- tiny DOM helper ---------- */
  function h(tag, attrs) {
    var el = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k === 'svg') el.innerHTML = v;            // only ever fed from icons.js
      else if (k === 'value' || k === 'checked' || k === 'selected' || k === 'disabled') el[k] = v;
      else if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v === true ? '' : v);
    });
    for (var i = 2; i < arguments.length; i++) add(el, arguments[i]);
    return el;
  }
  function add(el, c) {
    if (c === null || c === undefined || c === false) return;
    if (Array.isArray(c)) c.forEach(function (x) { add(el, x); });
    else el.appendChild(typeof c === 'object' ? c : document.createTextNode(String(c)));
  }

  function toast(msg, type) {
    toastEl.textContent = msg;
    toastEl.className = 'toast show ' + (type || '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.className = 'toast'; }, 3200);
  }

  function api(action, fd) {
    fd = fd || new FormData();
    fd.set('action', action);
    return fetch('api.php', { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd, credentials: 'same-origin' })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false, error: 'Unexpected server response. Is Apache running?' }; })
          .then(function (j) {
            if (r.status === 401) { location.href = 'login.php'; throw new Error('Session expired'); }
            if (!j.ok) throw new Error(j.error || 'Something went wrong.');
            return j;
          });
      });
  }

  function load() {
    return api('load').then(function (j) { state.data = j; render(); })
      .catch(function (e) { if (!state.data) app.textContent = e.message; else toast(e.message, 'bad'); });
  }

  function fmtDate(s) {
    var d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? s : d.toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }
  function icon(key) { return (ICONS[key] || ICONS.folder || { svg: '' }).svg; }
  function thumb(row) {
    if (row.image) return h('span', { class: 'thumb' }, h('img', { src: '../' + row.image, alt: '' }));
    return h('span', { class: 'thumb', svg: icon(row.icon) });
  }

  /* ---------- generic list + form view ---------- */
  function crudView(cfg) {
    var d = state.data;
    var rows = cfg.rows(d);
    var editing = state.editing[cfg.key] || null;
    var inputs = {};

    // left: table
    var head = h('tr', null, cfg.columns.map(function (c) { return h('th', { class: c.cls || '', text: c.label }); }), h('th'));
    var body = rows.length ? rows.map(function (r) {
      return h('tr', null,
        cfg.columns.map(function (c) { return h('td', { class: c.cls || '' }, c.cell(r)); }),
        h('td', { class: 'act' },
          h('button', { class: 'btn', type: 'button', text: 'Edit', onclick: function () { state.editing[cfg.key] = r; render(); window.scrollTo({ top: 0, behavior: 'smooth' }); } }), ' ',
          h('button', { class: 'btn del', type: 'button', text: 'Delete', onclick: function () {
            if (!confirm('Delete "' + cfg.name(r) + '"? This cannot be undone.')) return;
            var fd = new FormData(); fd.set('entity', cfg.entity); fd.set('id', r.id);
            api('delete', fd).then(function () { if (editing && editing.id === r.id) state.editing[cfg.key] = null; toast('Deleted.', 'ok'); return load(); })
              .catch(function (e) { toast(e.message, 'bad'); });
          } })));
    }) : h('tr', null, h('td', { class: 'empty', colspan: cfg.columns.length + 1, text: 'Nothing here yet. Add your first one on the right.' }));
    var left = h('div', { class: 'panel' }, h('h2', { text: cfg.title }), h('div', { class: 'tbl-wrap' }, h('table', null, h('thead', null, head), h('tbody', null, body))));

    // right: form
    var form = h('form', { class: 'form', novalidate: false });
    cfg.fields.forEach(function (f) {
      var val = editing ? editing[f.name] : (f.def !== undefined ? f.def : '');
      var el;
      if (f.type === 'textarea') {
        el = h('textarea', { name: f.name, maxlength: f.max, value: val || '' });
      } else if (f.type === 'select') {
        el = h('select', { name: f.name }, f.options.map(function (o) { return h('option', { value: o[0], text: o[1], selected: String(val) === o[0] }); }));
      } else if (f.type === 'checks') {
        var have = String(val || '').split(/\s+/);
        el = h('div', { class: 'checks' }, f.options.map(function (o) {
          var cb = h('input', { type: 'checkbox', value: o[0], checked: have.indexOf(o[0]) !== -1 });
          cb._v = o[0];
          return h('label', null, cb, o[1]);
        }));
      } else if (f.type === 'checkbox') {
        el = h('input', { type: 'checkbox', checked: editing ? !!editing[f.name] : true });
      } else if (f.type === 'file') {
        el = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif' });
      } else {
        el = h('input', { type: f.type || 'text', name: f.name, maxlength: f.max, min: f.min, max: f.maxv, value: val === null || val === undefined ? '' : val, required: f.required });
      }
      inputs[f.name] = el;
      if (f.type === 'checkbox') {
        form.appendChild(h('label', { class: 'chk', style: 'margin-top:14px!important' }, el, f.label));
      } else {
        form.appendChild(h('label', { text: f.label + (f.required ? ' *' : '') }));
        form.appendChild(el);
      }
      if (f.type === 'file' && editing && editing.image) {
        var rm = h('input', { type: 'checkbox' }); inputs['remove_' + f.name] = rm;
        form.appendChild(h('div', { class: 'cur-img' }, thumb(editing), h('label', { class: 'chk' }, rm, 'Remove current image')));
      }
      if (f.hint) form.appendChild(h('div', { class: 'hint', text: f.hint }));
    });
    var submit = h('button', { class: 'btn-primary', type: 'submit', text: editing ? 'Save Changes' : 'Add ' + cfg.singular });
    form.appendChild(editing
      ? h('div', { class: 'row-btns' }, submit, h('button', { class: 'btn-cancel', type: 'button', text: 'Cancel', onclick: function () { state.editing[cfg.key] = null; render(); } }))
      : submit);

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (!form.reportValidity()) return;
      var fd = new FormData();
      fd.set('entity', cfg.entity);
      if (editing) fd.set('id', editing.id);
      Object.keys(cfg.extra || {}).forEach(function (k) { fd.set(k, cfg.extra[k]); });
      cfg.fields.forEach(function (f) {
        var el = inputs[f.name];
        if (f.type === 'checks') {
          fd.set(f.name, Array.prototype.filter.call(el.querySelectorAll('input'), function (c) { return c.checked; }).map(function (c) { return c._v; }).join(' '));
        } else if (f.type === 'checkbox') {
          if (el.checked) fd.set(f.name, '1');
        } else if (f.type === 'file') {
          if (el.files[0]) fd.set(f.name, el.files[0]);
          if (inputs['remove_' + f.name] && inputs['remove_' + f.name].checked) fd.set('remove_' + f.name, '1');
        } else {
          fd.set(f.name, el.value);
        }
      });
      submit.disabled = true;
      api('save', fd).then(function () {
        state.editing[cfg.key] = null; toast(editing ? 'Changes saved.' : cfg.singular + ' added.', 'ok'); return load();
      }).catch(function (e) { toast(e.message, 'bad'); submit.disabled = false; });
    });

    var right = h('div', { class: 'panel' }, h('h2', { text: (editing ? 'Edit ' : 'Add ') + cfg.singular }), form);
    return h('div', { class: 'cols' }, left, right);
  }

  var iconOptions = Object.keys(ICONS).map(function (k) { return [k, ICONS[k].label]; });
  var catOptions = Object.keys(CATS).map(function (k) { return [k, CATS[k]]; });

  var views = {
    projects: function () {
      return crudView({
        key: 'projects', entity: 'projects', singular: 'Project', title: 'My Projects',
        rows: function (d) { return d.projects; }, name: function (r) { return r.title; },
        columns: [
          { label: 'ID', cls: 'id', cell: function (r) { return r.id; } },
          { label: 'Project', cell: function (r) {
            return h('div', { class: 'item' }, thumb(r), h('div', null, h('b', { text: r.title }),
              h('small', { text: String(r.category || '').split(/\s+/).filter(Boolean).map(function (c) { return CATS[c] || c; }).join(', ') || 'No category' })));
          } },
          { label: 'Status', cell: function (r) { return h('span', { class: 'pill' + (r.visible ? '' : ' off'), text: r.visible ? 'Visible' : 'Hidden' }); } }
        ],
        fields: [
          { name: 'title', label: 'Project Title', required: true, max: 150 },
          { name: 'category', label: 'Categories (used by the filter buttons)', type: 'checks', options: catOptions },
          { name: 'description', label: 'Description', type: 'textarea', max: 500 },
          { name: 'tags', label: 'Tags', max: 200, hint: 'Separate with commas, e.g. HTML, CSS' },
          { name: 'link', label: 'Link', max: 255, hint: 'A page of your site like web.html, or a full https:// link. Leave empty for no link.' },
          { name: 'icon', label: 'Icon', type: 'select', options: iconOptions, def: 'folder' },
          { name: 'image', label: 'Image (optional)', type: 'file', hint: 'Shown instead of the icon. JPG, PNG, WEBP or GIF, up to 3 MB.' },
          { name: 'visible', label: 'Show on website', type: 'checkbox' }
        ]
      });
    },
    skills: function () {
      return crudView({
        key: 'skills', entity: 'skills', singular: 'Skill', title: 'My Skills (About page bars)', extra: { kind: 'skill' },
        rows: function (d) { return d.skills.filter(function (s) { return s.kind === 'skill'; }); }, name: function (r) { return r.name; },
        columns: [
          { label: 'ID', cls: 'id', cell: function (r) { return r.id; } },
          { label: 'Skill', cell: function (r) { return h('div', { class: 'item' }, thumb(r), h('div', null, h('b', { text: r.name }))); } },
          { label: 'Level', cls: 'num', cell: function (r) { return r.percent + '%'; } }
        ],
        fields: [
          { name: 'name', label: 'Skill Name', required: true, max: 100 },
          { name: 'percent', label: 'Level (0–100)', type: 'number', min: 0, maxv: 100, required: true, def: 50 },
          { name: 'icon', label: 'Icon', type: 'select', options: iconOptions, def: 'code' }
        ]
      });
    },
    tools: function () {
      return crudView({
        key: 'tools', entity: 'skills', singular: 'Tool', title: 'My Tools (Home page "Skills & tools")', extra: { kind: 'tool' },
        rows: function (d) { return d.skills.filter(function (s) { return s.kind === 'tool'; }); }, name: function (r) { return r.name; },
        columns: [
          { label: 'ID', cls: 'id', cell: function (r) { return r.id; } },
          { label: 'Tool', cell: function (r) { return h('div', { class: 'item' }, thumb(r), h('div', null, h('b', { text: r.name }))); } }
        ],
        fields: [
          { name: 'name', label: 'Tool Name', required: true, max: 100 },
          { name: 'icon', label: 'Icon', type: 'select', options: iconOptions, def: 'code' }
        ]
      });
    },
    education: function () {
      return crudView({
        key: 'education', entity: 'education', singular: 'Education', title: 'My Education (About page timeline)',
        rows: function (d) { return d.education; }, name: function (r) { return r.title; },
        columns: [
          { label: 'ID', cls: 'id', cell: function (r) { return r.id; } },
          { label: 'Education', cell: function (r) { return h('div', null, h('small', { text: r.period, style: 'color:#2c6cb5;font-weight:700' }), h('b', { text: r.title, style: 'display:block;font-weight:400' }), h('small', { text: r.description, style: 'color:#7b8aa0' })); } }
        ],
        fields: [
          { name: 'period', label: 'Period', max: 60, hint: 'e.g. PRESENT or 2022 – 2024' },
          { name: 'title', label: 'Title', required: true, max: 150 },
          { name: 'description', label: 'Description', type: 'textarea', max: 300 }
        ]
      });
    },

    profile: function () {
      var s = state.data.settings;
      var groups = [
        ['Basic info', [
          { n: 'full_name', l: 'Full Name', max: 100 }, { n: 'footer_tagline', l: 'Footer tagline', max: 150 }]],
        ['Home page — top section', [
          { n: 'hero_eyebrow', l: 'Small label above the headline', max: 100 },
          { n: 'hero_headline', l: 'Headline', t: 'textarea', max: 200, hint: 'Put *stars* around a word to highlight it. Press Enter for a new line.' },
          { n: 'hero_text', l: 'Intro text', t: 'textarea', max: 600 }]],
        ['Home page — About section', [
          { n: 'home_about_heading', l: 'Heading', max: 100 }, { n: 'home_about_text', l: 'Text', t: 'textarea', max: 800 }]],
        ['About page', [
          { n: 'about_heading', l: 'Heading', max: 100 },
          { n: 'about_p1', l: 'Paragraph 1', t: 'textarea', max: 800, hint: 'Put **two stars** around words to make them bold.' },
          { n: 'about_p2', l: 'Paragraph 2', t: 'textarea', max: 800 }]],
        ['Contact details', [
          { n: 'email', l: 'Email', t: 'email', max: 150 }, { n: 'phone', l: 'Phone', max: 30 },
          { n: 'facebook_url', l: 'Facebook link', max: 255, hint: 'https://…' }, { n: 'facebook_label', l: 'Facebook name shown', max: 100 },
          { n: 'instagram_url', l: 'Instagram link', max: 255, hint: 'https://…' }, { n: 'instagram_label', l: 'Instagram name shown', max: 100 }]]
      ];
      var inputs = {};
      var form = h('form', { class: 'form' });
      groups.forEach(function (g) {
        form.appendChild(h('div', { class: 'grp', text: g[0] }));
        g[1].forEach(function (f) {
          var el = f.t === 'textarea'
            ? h('textarea', { maxlength: f.max, value: s[f.n] || '' })
            : h('input', { type: f.t || 'text', maxlength: f.max, value: s[f.n] || '' });
          inputs[f.n] = el;
          form.appendChild(h('label', { text: f.l }));
          form.appendChild(el);
          if (f.hint) form.appendChild(h('div', { class: 'hint', text: f.hint }));
        });
      });
      form.appendChild(h('div', { class: 'grp', text: 'Profile photo' }));
      var file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif' });
      form.appendChild(h('label', { text: 'Photo (optional)' }));
      form.appendChild(file);
      form.appendChild(h('div', { class: 'hint', text: 'Replaces the photo on the Home and About pages. JPG, PNG, WEBP or GIF, up to 3 MB.' }));
      var rm = h('input', { type: 'checkbox' });
      if (s.profile_photo) form.appendChild(h('div', { class: 'cur-img' }, h('span', { class: 'thumb' }, h('img', { src: '../' + s.profile_photo, alt: '' })), h('label', { class: 'chk' }, rm, 'Remove current photo (use the default)')));
      var submit = h('button', { class: 'btn-primary', type: 'submit', text: 'Save Details' });
      form.appendChild(submit);
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!form.reportValidity()) return;
        var fd = new FormData();
        Object.keys(inputs).forEach(function (k) { fd.set(k, inputs[k].value); });
        if (file.files[0]) fd.set('profile_photo', file.files[0]);
        if (rm.checked) fd.set('remove_profile_photo', '1');
        submit.disabled = true;
        api('save_settings', fd).then(function () { toast('Details saved. Refresh your website to see them.', 'ok'); return load(); })
          .catch(function (e) { toast(e.message, 'bad'); submit.disabled = false; });
      });
      return h('div', { class: 'panel wide' }, h('h2', { text: 'My Details' }), form);
    },

    messages: function () {
      var msgs = state.data.messages;
      var sel = msgs.filter(function (m) { return m.id === state.selMsg; })[0] || null;

      var body = msgs.length ? msgs.map(function (m) {
        return h('tr', { class: 'msg' + (m.is_read ? '' : ' unread') + (sel && sel.id === m.id ? ' sel' : ''), onclick: function () { openMsg(m); } },
          h('td', { class: 'id' }, h('span', { class: 'dot' + (m.is_read ? ' read' : '') })),
          h('td', null, h('div', { text: m.name }), h('span', { class: 'preview', text: m.message })),
          h('td', { text: fmtDate(m.created_at), style: 'white-space:nowrap;font-weight:400;color:#7b8aa0;font-size:.85rem' }));
      }) : h('tr', null, h('td', { class: 'empty', colspan: 3, text: 'No messages yet. Messages from your Contact page will show up here.' }));
      var left = h('div', { class: 'panel' }, h('h2', { text: 'Messages from Contact' }),
        h('div', { class: 'tbl-wrap' }, h('table', null, h('thead', null, h('tr', null, h('th'), h('th', { text: 'From' }), h('th', { text: 'Received' }))), h('tbody', null, body))));

      var right;
      if (!sel) {
        right = h('div', { class: 'panel' }, h('h2', { text: 'Message' }), h('p', { class: 'hint', text: 'Click a message on the left to read it.' }));
      } else {
        var okMail = /^[^\s@<>"']+@[^\s@<>"']+\.[^\s@<>"']+$/.test(sel.email);
        var subject = 'Re: your message on my portfolio';
        var quoted = sel.message.length > 800 ? sel.message.slice(0, 800) + '…' : sel.message;
        var bodyText = 'Hi ' + sel.name + ',\n\n\n\n---\nOn ' + fmtDate(sel.created_at) + ', you wrote:\n' + quoted.split(/\r?\n/).map(function (l) { return '> ' + l; }).join('\n');
        var gmail = okMail ? h('a', { class: 'btn', target: '_blank', rel: 'noopener',
          href: 'https://mail.google.com/mail/?view=cm&fs=1&to=' + encodeURIComponent(sel.email) + '&su=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(bodyText),
          text: 'Reply with Gmail' }) : null;
        var copyBtn = h('button', { class: 'btn ghost', type: 'button', text: 'Copy email', onclick: function () {
          (navigator.clipboard ? navigator.clipboard.writeText(sel.email) : Promise.reject()).then(function () { toast('Email copied: ' + sel.email, 'ok'); }, function () { toast(sel.email); });
        } });
        right = h('div', { class: 'panel' }, h('h2', { text: 'Message' }),
          h('div', { class: 'msg-head' }, h('b', { text: sel.name }), h('small', { text: sel.email }), h('small', { text: fmtDate(sel.created_at) })),
          h('div', { class: 'msg-body', text: sel.message }),
          h('div', { class: 'msg-actions' }, gmail, copyBtn,
            h('button', { class: 'btn ghost', type: 'button', text: sel.is_read ? 'Mark as unread' : 'Mark as read', onclick: function () { setRead(sel, sel.is_read ? 0 : 1); } }),
            h('button', { class: 'btn del', type: 'button', text: 'Delete', onclick: function () {
              if (!confirm('Delete this message from ' + sel.name + '? This cannot be undone.')) return;
              var fd = new FormData(); fd.set('id', sel.id);
              api('msg_delete', fd).then(function () { state.selMsg = null; toast('Message deleted.', 'ok'); return load(); }).catch(function (e) { toast(e.message, 'bad'); });
            } })));
      }
      return h('div', { class: 'cols' }, left, right);
    },

    account: function () {
      var cur = h('input', { type: 'password', required: true, autocomplete: 'current-password' });
      var nw = h('input', { type: 'password', required: true, minlength: 8, autocomplete: 'new-password' });
      var cf = h('input', { type: 'password', required: true, minlength: 8, autocomplete: 'new-password' });
      var submit = h('button', { class: 'btn-primary', type: 'submit', text: 'Change Password' });
      var form = h('form', { class: 'form' },
        h('label', { text: 'Current password' }), cur,
        h('label', { text: 'New password' }), nw, h('div', { class: 'hint', text: 'At least 8 characters.' }),
        h('label', { text: 'Confirm new password' }), cf, submit);
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (nw.value !== cf.value) { toast('New passwords do not match.', 'bad'); return; }
        var fd = new FormData(); fd.set('current', cur.value); fd.set('new', nw.value); fd.set('confirm', cf.value);
        submit.disabled = true;
        api('change_password', fd).then(function () { toast('Password changed.', 'ok'); form.reset(); })
          .catch(function (e) { toast(e.message, 'bad'); }).then(function () { submit.disabled = false; });
      });
      return h('div', { class: 'panel', style: 'max-width:420px' }, h('h2', { text: 'Change Password' }), form);
    }
  };

  function setRead(m, v) {
    var fd = new FormData(); fd.set('id', m.id); fd.set('value', v);
    return api('msg_read', fd).then(function () { m.is_read = v; render(); }).catch(function (e) { toast(e.message, 'bad'); });
  }
  function openMsg(m) {
    state.selMsg = m.id;
    if (!m.is_read) setRead(m, 1); else render();
  }

  /* ---------- page shell ---------- */
  var SUN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
  function toggleTheme() {
    var root = document.documentElement;
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('theme', next); } catch (e) {}
  }

  function render() {
    var d = state.data;
    var unread = d.messages.filter(function (m) { return !m.is_read; }).length;
    var tabs = [['projects', 'Projects'], ['skills', 'Skills'], ['tools', 'Tools'], ['education', 'Education'], ['profile', 'My Details'], ['messages', 'Messages'], ['account', 'Account']];

    var logoImg = h('img', { src: '../imgs/logo1.png', alt: '' });
    logoImg.addEventListener('error', function () { logoImg.replaceWith(document.createTextNode('DH')); }, { once: true });
    var header = h('header', { class: 'site' }, h('div', { class: 'container' }, h('nav', { class: 'main', 'aria-label': 'Admin' },
      h('a', { class: 'logo', href: '../index.html', 'aria-label': 'Back to website' }, h('span', { class: 'mono' }, logoImg), h('span', { text: 'Dennise H.' }), h('small', { text: 'Admin' })),
      h('div', { class: 'nav-actions' },
        h('span', { class: 'who', text: d.user }),
        h('a', { class: 'btn ghost', href: '../index.html', target: '_blank', rel: 'noopener', text: 'View website' }),
        h('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Toggle dark mode', svg: SUN, onclick: toggleTheme }),
        h('button', { class: 'btn', type: 'button', text: 'Log out', onclick: function () {
          api('logout').catch(function () {}).then(function () { location.href = 'login.php'; });
        } })))));

    var hero = h('section', { class: 'page-hero' }, h('div', { class: 'container' },
      h('h1', { text: 'Admin Dashboard' }), h('p', { text: 'Manage your portfolio content and read messages from your Contact page.' })));

    var nav = h('div', { class: 'tabs', role: 'tablist' }, tabs.map(function (t) {
      return h('button', { class: 'tab' + (state.tab === t[0] ? ' on' : ''), type: 'button', role: 'tab', 'aria-selected': state.tab === t[0] ? 'true' : 'false',
        onclick: function () { state.tab = t[0]; render(); } },
        t[1], t[0] === 'messages' && unread ? h('span', { class: 'badge', text: String(unread) }) : null);
    }));

    app.textContent = '';
    app.appendChild(header);
    app.appendChild(hero);
    app.appendChild(h('main', null, h('div', { class: 'container' }, nav, views[state.tab]())));
  }

  load();
})();
