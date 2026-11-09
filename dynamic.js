/* Loads the content you edit in the admin (api/public.php) into the public pages.
   If the server is not reachable, the original HTML stays exactly as it is. */
(function () {
  'use strict';
  var ICONS = window.PORTFOLIO_ICONS || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function iconSvg(k) { return (ICONS[k] || ICONS.folder || { svg: '' }).svg; }
  function safeUrl(u) { return /^(https?:\/\/[^\s<>"']+|[A-Za-z0-9_\-\/]+\.(html|php))$/i.test(u || ''); }
  function safeImg(u) { return /^uploads\/[a-f0-9]{16}\.(jpg|png|webp|gif)$/.test(u || ''); }

  // text with *highlight*, **bold** and line breaks, built with DOM nodes (no innerHTML)
  function rich(el, text) {
    el.textContent = '';
    String(text).split(/\r?\n/).forEach(function (line, i) {
      if (i) el.appendChild(document.createElement('br'));
      line.split(/(\*\*[^*]+\*\*|\*[^*]+\*)/).forEach(function (part) {
        if (!part) return;
        var n;
        if (part.length > 4 && part.slice(0, 2) === '**' && part.slice(-2) === '**') { n = document.createElement('strong'); n.textContent = part.slice(2, -2); }
        else if (part.length > 2 && part[0] === '*' && part.slice(-1) === '*') { n = document.createElement('em'); n.textContent = part.slice(1, -1); }
        else n = document.createTextNode(part);
        el.appendChild(n);
      });
    });
  }
  function setText(sel, v) { var el = $(sel); if (el && v) el.textContent = v; }
  function setRich(sel, v) { var el = $(sel); if (el && v) rich(el, v); }

  function applySettings(s) {
    // home hero
    setText('.hero .eyebrow', s.hero_eyebrow);
    setRich('.hero h1', s.hero_headline);
    setText('.hero p.lead', s.hero_text);
    // home about
    setText('#about .about-copy h2', s.home_about_heading);
    setRich('#about .about-copy p', s.home_about_text);
    // about page
    setText('.about-text h2', s.about_heading);
    var ps = $$('.about-text p');
    if (ps[0] && s.about_p1) rich(ps[0], s.about_p1);
    if (ps[1] && s.about_p2) rich(ps[1], s.about_p2);
    // photo
    if (safeImg(s.profile_photo)) {
      $$('img.avatar, img.about-photo').forEach(function (img) { img.src = s.profile_photo; });
    }
    // footer
    $$('footer.site .admin-link').forEach(function (a) { if (s.full_name) a.textContent = s.full_name; });
    var tag = $('footer.site .foot > div:first-child > span');
    if (tag && s.footer_tagline) tag.textContent = s.footer_tagline;
    var copy = $('footer.site .copy');
    if (copy && s.full_name) copy.textContent = '© ' + new Date().getFullYear() + ' ' + s.full_name + '. All rights reserved.';

    // contact links (header/footer icons and the contact page cards)
    if (s.email && /^[^\s@<>"']+@[^\s@<>"']+\.[^\s@<>"']+$/.test(s.email)) {
      $$('a[href^="mailto:"]').forEach(function (a) { a.setAttribute('href', 'mailto:' + s.email); });
      var ce = $('#copy-email');
      if (ce) { ce.setAttribute('data-email', s.email); var b = $('b', ce); if (b) b.textContent = s.email; }
    }
    if (s.phone) {
      var digits = s.phone.replace(/[^\d+]/g, '');
      $$('a[href^="tel:"]').forEach(function (a) { a.setAttribute('href', 'tel:' + digits); var b = $('b', a); if (b) b.textContent = s.phone; });
    }
    function social(host, url, label) {
      $$('a[href*="' + host + '"]').forEach(function (a) {
        if (/^https?:\/\//i.test(url || '')) a.setAttribute('href', url);
        var b = $('b', a); if (b && label) b.textContent = label;
      });
    }
    social('facebook.com', s.facebook_url, s.facebook_label);
    social('instagram.com', s.instagram_url, s.instagram_label);
  }

  function projectCard(p) {
    var linked = safeUrl(p.link);
    var el = document.createElement(linked ? 'a' : 'div');
    el.className = 'card' + (linked ? '' : ' hover');
    if (linked) el.setAttribute('href', p.link);
    el.setAttribute('data-cat', p.category || '');
    var ico = document.createElement('div'); ico.className = 'ico';
    if (safeImg(p.image)) {
      var im = document.createElement('img'); im.src = p.image; im.alt = '';
      im.style.cssText = 'width:100%;height:100%;object-fit:cover';
      ico.style.overflow = 'hidden'; ico.appendChild(im);
    } else { ico.innerHTML = iconSvg(p.icon); }        // icons.js only, never user text
    var h3 = document.createElement('h3'); h3.textContent = p.title;
    el.appendChild(ico); el.appendChild(h3);
    if (p.description) { var d = document.createElement('p'); d.textContent = p.description; el.appendChild(d); }
    var tags = String(p.tags || '').split(',').map(function (t) { return t.trim(); }).filter(Boolean);
    if (tags.length) {
      var tw = document.createElement('div'); tw.className = 'tags';
      tags.forEach(function (t) { var sp = document.createElement('span'); sp.className = 'tag'; sp.textContent = t; tw.appendChild(sp); });
      el.appendChild(tw);
    }
    if (linked) { var m = document.createElement('span'); m.className = 'more'; m.textContent = 'View project →'; el.appendChild(m); }
    return el;
  }

  function applyProjects(list) {
    $$('#projects .grid.cols-3, .filters + .grid.cols-3').forEach(function (grid) {
      grid.textContent = '';
      list.forEach(function (p) { grid.appendChild(projectCard(p)); });
      // keep the active filter chip applied
      var chip = $('.chip.active');
      if (chip && chip.dataset.filter !== 'all') {
        $$('[data-cat]', grid).forEach(function (c) { c.classList.toggle('hidden', c.dataset.cat.split(' ').indexOf(chip.dataset.filter) === -1); });
      }
    });
  }

  function applySkills(list) {
    var bars = list.filter(function (s) { return s.kind === 'skill'; });
    var tools = list.filter(function (s) { return s.kind === 'tool'; });

    var card = null;
    $$('.card').forEach(function (c) { var t = $('h3', c); if (t && t.textContent.trim() === 'Skills' && $('.skill', c)) card = c; });
    if (card) {
      var note = $('p', card);
      $$('.skill', card).forEach(function (n) { n.remove(); });
      bars.forEach(function (s) {
        var wrap = document.createElement('div'); wrap.className = 'skill';
        var row = document.createElement('div'); row.className = 'row';
        var lbl = document.createElement('span'); lbl.className = 'lbl';
        lbl.innerHTML = iconSvg(s.icon);
        lbl.appendChild(document.createTextNode(s.name));
        var pct = document.createElement('span'); pct.textContent = s.percent + '%';
        row.appendChild(lbl); row.appendChild(pct);
        var bar = document.createElement('div'); bar.className = 'bar';
        var fill = document.createElement('i'); bar.appendChild(fill);
        wrap.appendChild(row); wrap.appendChild(bar);
        card.insertBefore(wrap, note);
        requestAnimationFrame(function () { requestAnimationFrame(function () { fill.style.width = Math.max(0, Math.min(100, +s.percent || 0)) + '%'; }); });
      });
    }

    var pills = $('#tool-list');
    if (pills) {
      pills.textContent = '';
      tools.forEach(function (s) {
        var li = document.createElement('li');
        li.innerHTML = iconSvg(s.icon);
        li.appendChild(document.createTextNode(s.name));
        pills.appendChild(li);
      });
    }
  }

  function applyEducation(list) {
    var box = $('#edu-list');
    if (!box) return;
    box.textContent = '';
    list.forEach(function (e) {
      var tl = document.createElement('div'); tl.className = 'tl';
      var sm = document.createElement('small'); sm.textContent = e.period;
      var h3 = document.createElement('h3'); h3.textContent = e.title;
      var p = document.createElement('p'); p.textContent = e.description;
      tl.appendChild(sm); tl.appendChild(h3); tl.appendChild(p);
      box.appendChild(tl);
    });
  }

  fetch('api/public.php', { cache: 'no-store', credentials: 'same-origin' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (j) {
      if (!j || !j.ok) return;
      applySettings(j.settings || {});
      applyProjects(j.projects || []);
      applySkills(j.skills || []);
      applyEducation(j.education || []);
    })
    .catch(function () { /* opened without PHP: keep the static content */ })
    .then(function () { if (window.__dynDone) window.__dynDone(); });
})();
