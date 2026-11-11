/*
 * The page builder. Edits the JSON layout of a page in the browser, saves it as the page's draft
 * (posts.builder_draft), and shows the page the server renders in an iframe. Plain JS, no build step, no library.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-builder]');
  // Folded widget groups in the Add panel, remembered per browser.
  var GROUPS_KEY = 'dyskflow.builder.groups';
  var closedGroups = { grid: true, advanced: true };
  try { closedGroups = JSON.parse(localStorage.getItem(GROUPS_KEY) || 'null') || closedGroups; } catch (err) { /* defaults */ }
  var phone = window.matchMedia('(max-width: 860px)');
  if (!root) return;

  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
  var boot = JSON.parse($('[data-builder-boot]', root).textContent);
  var t = JSON.parse($('[data-builder-i18n]', root).textContent);
  var ICONS = JSON.parse($('[data-builder-icons]', root).textContent);
  var tokenInput = $('#post-form input[name="_token"]');

  var urls = { spec: root.dataset.specUrl, draft: root.dataset.draftUrl, render: root.dataset.renderUrl, publish: root.dataset.publishUrl };
  var frame = $('[data-bld-frame]', root);
  var stage = $('[data-bld-stage]', root);
  var treeEl = $('[data-bld-tree]', root);
  var inspector = $('[data-bld-inspector]', root);
  var problemsEl = $('[data-bld-problems]', root);

  var spec, hash, isDraft, isLive, errors, warnings;
  var widgets = {};
  var tokens = boot.tokens;
  var categories = boot.categories || {};
  // Without code.manage, code widgets (html) can be moved, copied and deleted but not added or edited: the server refuses that.
  var locked = function (type) { return !boot.code && (boot.codeWidgets || []).indexOf(type) >= 0; };
  var selected = null;
  var locale = root.dataset.locale;
  var tab = null;
  var index = {};
  var history = [], future = [], lastEditKey = null, lastEditAt = 0;
  var changes = 0, savedChanges = 0, saving = null, saveTimer = null;
  var previewTimer = null, previewSeq = 0, frameReady = false, frameLayout = null, frameLocale = null, scrollAfterRender = false;

  var blocks = null, blockForm = null;

  boot.widgets.forEach(function (w) { widgets[w.name] = w; });
  applyState(boot);

  /* ---------- Data helpers ---------- */

  function clone(value) { return JSON.parse(JSON.stringify(value)); }
  function esc(text) { return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function icon(name) { return ICONS[name] || ''; }
  function isObject(v) { return v !== null && typeof v === 'object' && !Array.isArray(v); }

  function newId(prefix) {
    var id;
    do { id = prefix + Math.random().toString(36).slice(2, 6).padEnd(4, '0'); } while (index[id] || !/^[a-z][a-z0-9]{2,15}$/.test(id));
    index[id] = { reserved: true };
    return id;
  }

  /* Every node by id, with its list and parent, so moving and deleting stay simple. */
  function reindex() {
    index = {};
    (spec.sections || []).forEach(function (s) { visit(s, kindFor(s, 'section'), spec.sections, null, 0); });
  }
  /* A node's kind from its type: containers anywhere, inner sections in columns, otherwise what the list holds. */
  function kindFor(node, fallback) {
    if (isObject(node) && node.type === 'container') return 'container';
    if (isObject(node) && node.type === 'inner-section') return 'inner';
    return fallback;
  }
  /* The list a node holds its children in: columns' widgets, containers' children. */
  function itemsOf(node, kind) {
    var key = kind === 'container' ? 'children' : 'widgets';
    if (!Array.isArray(node[key])) node[key] = [];
    return node[key];
  }
  function containerDepth(node) {
    var depth = 0;
    for (var e = node && entry(node.id); e; e = e.parent ? entry(e.parent.id) : null) { if (e.kind === 'container') depth++; }
    return depth;
  }
  function nodeDepth(node) {
    var max = 0;
    (function walk(n, d) { if (!isObject(n) || n.type !== 'container') return; max = Math.max(max, d); (n.children || []).forEach(function (c) { walk(c, d + 1); }); })(node, 1);
    return max;
  }
  function visit(node, kind, list, parent, depth) {
    if (!isObject(node)) return;
    if (node.id) index[node.id] = { node: node, kind: kind, list: list, parent: parent, depth: depth };
    if (kind === 'section' || kind === 'inner') (Array.isArray(node.columns) ? node.columns : []).forEach(function (c) { visit(c, 'column', node.columns, node, depth + 1); });
    if (kind === 'column') (Array.isArray(node.widgets) ? node.widgets : []).forEach(function (w) { visit(w, w && w.type === 'inner-section' ? 'inner' : 'widget', node.widgets, node, depth + 1); });
    if (kind === 'container') (Array.isArray(node.children) ? node.children : []).forEach(function (c) { visit(c, kindFor(c, 'widget'), node.children, node, depth + 1); });
  }
  function entry(id) { var e = index[id]; return e && e.node ? e : null; }
  function textIn(value) {
    if (isObject(value)) return value[locale] || '';
    return typeof value === 'string' ? value : '';
  }
  function plain(html) { return String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(); }

  function freshIds(node) {
    if (!isObject(node)) return node;
    if (node.id) node.id = newId(node.id.charAt(0));
    ['columns', 'widgets', 'children'].forEach(function (key) { if (Array.isArray(node[key])) node[key].forEach(freshIds); });
    return node;
  }

  /* ---------- State ---------- */

  function applyState(data) {
    spec = data.spec;
    if (!Array.isArray(spec.sections)) spec.sections = [];
    hash = data.hash;
    isDraft = !!data.draft;
    isLive = !!data.live;
    var view = $('[data-bld-view]', root);
    if (view) { view.hidden = !data.viewUrl; if (data.viewUrl) view.href = data.viewUrl; }
    errors = data.errors || [];
    warnings = data.warnings || [];
    reindex();
    if (selected && !entry(selected)) selected = null;
    savedChanges = changes;
    renderAll();
    updateStatus();
  }

  function snapshot() { return JSON.stringify({ spec: spec, selected: selected }); }
  function remember(key) {
    var now = Date.now();
    if (key && key === lastEditKey && now - lastEditAt < 1000) { lastEditAt = now; return; }
    lastEditKey = key || null;
    lastEditAt = now;
    history.push(snapshot());
    if (history.length > 50) history.shift();
    future = [];
    updateUndo();
  }
  function restore(from, to) {
    if (!from.length) return;
    to.push(snapshot());
    var state = JSON.parse(from.pop());
    spec = state.spec;
    selected = state.selected;
    lastEditKey = null;
    reindex();
    changed(true);
    updateUndo();
  }
  function updateUndo() {
    $('[data-bld-undo]', root).disabled = !history.length;
    $('[data-bld-redo]', root).disabled = !future.length;
  }

  /* A change to the structure redraws the panels. A change typed in a field only redraws the tree and preview. */
  function commit(mutate, key) {
    remember(key);
    mutate();
    reindex();
    changed(true);
  }
  function edit(mutate, key) {
    remember(key);
    mutate();
    changed(false);
  }
  function changed(structural) {
    changes++;
    renderTree();
    if (structural) renderInspector();
    schedulePreview(structural ? 0 : 250);
    scheduleSave();
  }
  function dirty() { return changes !== savedChanges; }

  /* ---------- Server ---------- */

  function request(url, method, body) {
    return fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tokenInput ? tokenInput.value : '' },
      body: body ? JSON.stringify(body) : undefined
    });
  }
  function readJson(response) {
    return response.json().catch(function () { return {}; }).then(function (data) { return { status: response.status, data: data }; });
  }

  function setState(text) { $('[data-bld-state]', root).textContent = text || ''; }
  function updateStatus() {
    $('[data-bld-status]', root).textContent = isDraft || !isLive ? t.draft || 'Draft' : t.publishedBadge || 'Published';
    $('[data-bld-discard]', root).hidden = !isDraft;
  }
  function showMessage(text, kind, conflict) {
    var box = $('[data-bld-message]', root);
    $('[data-bld-message-text]', box).textContent = text;
    box.className = 'alert bld-message ' + (kind === 'danger' ? 'alert-danger' : 'alert-warning');
    $('[data-bld-conflict]', box).hidden = !conflict;
    box.hidden = false;
  }
  function hideMessage() { $('[data-bld-message]', root).hidden = true; }
  function toast(text) {
    var holder = $('.toasts');
    if (!holder) return;
    var el = document.createElement('div');
    el.className = 'toast';
    el.textContent = text;
    holder.appendChild(el);
    setTimeout(function () { el.remove(); }, 4000);
  }

  function scheduleSave() {
    setState(t.unsaved);
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () { save(false); }, 1500);
  }

  /* Resolves to true when the draft on disk matches the editor. */
  function save(force) {
    clearTimeout(saveTimer);
    if (saving) return saving.then(function () { return dirty() ? save(force) : true; });
    var at = changes;
    setState(t.saving);
    saving = request(urls.draft, 'POST', { spec: spec, hash: hash, force: !!force }).then(readJson).then(function (result) {
      var data = result.data || {};
      if (result.status === 200) {
        hash = data.hash;
        isDraft = true;
        errors = data.errors || [];
        warnings = data.warnings || [];
        savedChanges = at;
        if (force) hideMessage();
        updateStatus();
        renderProblems();
        renderTree();
        setState(dirty() ? t.unsaved : t.saved);
        return !dirty();
      }
      setState(t.unsaved);
      if (result.status === 409) showMessage(data.message || t.conflict, 'warning', true);
      else if (result.status === 419) showMessage(t.expired, 'danger');
      else showMessage(data.message || t.failed, 'danger');
      return false;
    }).catch(function () {
      setState(t.unsaved);
      showMessage(t.failed, 'danger');
      return false;
    }).then(function (ok) {
      saving = null;
      return ok;
    });
    return saving;
  }

  function publish() {
    var button = $('[data-bld-publish]', root);
    button.disabled = true;
    (dirty() || saving ? save(false) : Promise.resolve(true)).then(function (ok) {
      if (!ok) return;
      return request(urls.publish, 'POST', { hash: hash }).then(readJson).then(function (result) {
        var data = result.data || {};
        if (result.status === 200) {
          hideMessage();
          applyState(data);
          setState('');
          toast(data.message || t.published);
          schedulePreview(0);
        } else if (result.status === 409) {
          showMessage(data.message || t.conflict, 'warning', true);
        } else if (result.status === 422 && data.errors) {
          showMessage((data.message || t.publishFirst) + '\n' + data.errors.join('\n'), 'danger');
        } else {
          showMessage(data.message || t.failed, 'danger');
        }
      });
    }).catch(function () { showMessage(t.failed, 'danger'); }).then(function () { button.disabled = false; });
  }

  function reload() {
    return request(urls.spec, 'GET').then(readJson).then(function (result) {
      if (result.status !== 200) return;
      hideMessage();
      history = []; future = []; updateUndo();
      applyState(result.data);
      setState('');
      schedulePreview(0);
    });
  }

  /* An AI tool (builder:import) or another tab may change the page while the builder is open. */
  setInterval(function () {
    if (document.hidden || saving || !$('[data-bld-message]', root).hidden) return;
    request(urls.spec, 'GET').then(readJson).then(function (result) {
      if (result.status !== 200 || saving || result.data.hash === hash) return;
      if (dirty()) {
        showMessage(t.conflict, 'warning', true);
      } else {
        applyState(result.data);
        schedulePreview(0);
        toast(t.updated);
      }
    }).catch(function () {});
  }, 5000);

  /* ---------- Preview ---------- */

  function schedulePreview(delay) {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(renderPreview, delay || 0);
  }

  function renderPreview() {
    var seq = ++previewSeq;
    request(urls.render, 'POST', { spec: spec, locale: locale }).then(function (response) {
      return response.ok ? response.text() : null;
    }).then(function (html) {
      if (html === null || seq !== previewSeq) return;
      var layout = spec.layout || 'site';
      var doc = frameReady ? frame.contentDocument : null;
      var main = doc && doc.getElementById('main');
      if (!main || layout !== frameLayout || locale !== frameLocale) {
        frameLayout = layout;
        frameLocale = locale;
        frameReady = false;
        frame.srcdoc = html;
        return;
      }
      var freshDoc = new DOMParser().parseFromString(html, 'text/html');
      var fresh = freshDoc.getElementById('main');
      if (!fresh) { frameReady = false; frame.srcdoc = html; return; }
      main.innerHTML = fresh.innerHTML;
      // The page's own CSS lives in <head>, which a partial update leaves alone.
      ['data-b-layout-css', 'data-b-page-css'].forEach(function (name) {
        var css = doc.querySelector('[' + name + ']'), freshCss = freshDoc.querySelector('[' + name + ']');
        if (css && freshCss) css.textContent = freshCss.textContent;
      });
      makeDraggable(frame.contentDocument);
      highlight(scrollAfterRender);
      scrollAfterRender = false;
    }).catch(function () {});
  }

  frame.addEventListener('load', function () {
    var doc = frame.contentDocument;
    if (!doc || !doc.getElementById('main')) return;
    frameReady = true;
    var style = doc.createElement('style');
    style.textContent = '.b-w[data-b-id]:hover,.b-empty:hover{outline:1px dashed #3b82f6;outline-offset:2px;cursor:pointer}'
      + '.b-selected{outline:2px solid #3b82f6!important;outline-offset:2px}'
      + '.b-section.b-selected,.b-col.b-selected,.b-con.b-selected{outline-offset:-2px}'
      + '.b-con[data-b-id]{outline:1px dashed rgb(59 130 246 / .25);outline-offset:-1px}.b-con[data-b-id]:hover{outline-color:rgb(59 130 246 / .7)}'
      + '.site-header,.site-footer{pointer-events:none}'
      + '.b-drop-before{box-shadow:0 -3px 0 #3b82f6!important}.b-drop-after{box-shadow:0 3px 0 #3b82f6!important}'
      + '.b-drop-inside{outline:2px dashed #3b82f6!important;outline-offset:-2px}'
      + '[draggable=true]{-webkit-user-drag:element}';
    doc.head.appendChild(style);
    doc.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var node = e.target.closest('[data-b-id]');
      if (node) select(node.getAttribute('data-b-id'), false);
    }, true);
    doc.addEventListener('submit', function (e) { e.preventDefault(); }, true);
    doc.addEventListener('keydown', keys);
    wireFrameDrag(doc);
    makeDraggable(doc);
    highlight(scrollAfterRender);
    scrollAfterRender = false;
  });

  function highlight(scroll) {
    var doc = frameReady ? frame.contentDocument : null;
    if (!doc) return;
    $$('.b-selected', doc).forEach(function (el) { el.classList.remove('b-selected'); });
    if (!selected) return;
    var el = doc.querySelector('[data-b-id="' + selected + '"]');
    if (!el) return;
    el.classList.add('b-selected');
    if (scroll) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  /* ---------- Selection ---------- */

  function select(id, fromPanel) {
    if (fromPanel !== false) scrollAfterRender = true;
    selected = id && entry(id) ? id : null;
    tab = null;
    renderTree();
    renderInspector();
    highlight(fromPanel !== false);
    if (selected && fromPanel === false) {
      showLeft('tree');
      // On a phone, tapping an element in the preview opens its settings.
      if (phone.matches) mobile('edit');
    }
  }

  /* ---------- Structure ---------- */

  function section(widths) {
    return {
      id: newId('s'), type: 'section',
      columns: widths.map(function (w) { return { id: newId('c'), width: w, widgets: [] }; })
    };
  }

  /*
   * A flex container. A preset such as "1-2" gives a row of inner containers sized 1:2,
   * stacked on phones; "1" an empty container that lays its children out in a column.
   */
  function container(preset) {
    var parts = String(preset || '1').split('-').map(Number);
    var node = { id: newId('x'), type: 'container', children: [] };
    if (parts.length < 2) return node;
    var total = parts.reduce(function (a, b) { return a + b; }, 0);
    var equal = parts.every(function (p) { return p === parts[0]; });
    node.style = { direction: { base: 'row', mobile: 'column' } };
    node.children = parts.map(function (p) {
      var child = { id: newId('x'), type: 'container', children: [] };
      if (!equal) child.style = { width: { base: Math.round(p / total * 10000) / 100 + '%', mobile: 'auto' } };
      return child;
    });
    return node;
  }

  /* The top-level node (section or container) an element sits in. */
  function topSection(id) {
    var e = entry(id);
    while (e && e.parent) e = entry(e.parent.id);
    return e || null;
  }

  function addSection(preset, asContainer) {
    var node = asContainer ? container(preset) : section(preset.split('-').map(Number));
    commit(function () {
      var top = selected ? topSection(selected) : null;
      var at = top ? spec.sections.indexOf(top.node) + 1 : spec.sections.length;
      spec.sections.splice(at, 0, node);
    });
    select(asContainer ? (node.children[0] || node).id : node.columns[0].id);
  }

  function defaults(type) {
    var d = t.defaults || {};
    switch (type) {
      case 'heading': return { text: d.heading };
      case 'text': return { html: '<p>' + esc(d.text) + '</p>' };
      case 'button': return { buttons: [{ label: d.button, url: '#' }] };
      case 'icon-list': return { items: [{ title: d.item + ' 1' }, { title: d.item + ' 2' }, { title: d.item + ' 3' }] };
      case 'card': return { cards: [1, 2, 3].map(function () { return { title: d.card, text: d.cardText }; }) };
      case 'faq': return { items: [1, 2, 3].map(function (n) { return { question: d.question + ' ' + n, answer: d.answer }; }) };
      case 'testimonials': return { items: [1, 2, 3].map(function () { return { quote: d.quote, name: d.name }; }) };
      case 'pricing': return { plans: [d.basic, d.pro, d.business].map(function (name, i) { return { name: name, price: d.price, period: d.period, features: d.features, button_label: d.choose, url: '#', featured: i === 1 }; }) };
      case 'gallery': return { images: [{}] };
      case 'stats': return { items: [['10+', d.statA], ['500+', d.statB], ['99%', d.statC]].map(function (p) { return { value: p[0], label: p[1] }; }) };
      default: return {};
    }
  }

  /*
   * Where a new widget goes: after the selected widget, at the end of the selected column or
   * container, or in the first column of the selected section. holder is the column or container.
   */
  function target() {
    var e = selected ? entry(selected) : null;
    if (e && (e.kind === 'widget' || e.kind === 'inner')) return { holder: e.parent, list: e.list, at: e.list.indexOf(e.node) + 1 };
    if (e && (e.kind === 'column' || e.kind === 'container')) { var items = itemsOf(e.node, e.kind); return { holder: e.node, list: items, at: items.length }; }
    if (e && e.kind === 'section') { var first = e.node.columns[0]; return { holder: first, list: itemsOf(first, 'column'), at: first.widgets.length }; }
    return null;
  }

  function makeWidget(type) {
    if (type === 'inner-section') return { id: newId('s'), type: 'inner-section', columns: [{ id: newId('c'), width: 6, widgets: [] }, { id: newId('c'), width: 6, widgets: [] }] };
    if (type === 'container') return container('1');
    return Object.assign({ id: newId('w'), type: type }, defaults(type));
  }

  /* Why a node of this kind cannot go into this holder (null for the top level), or null when it can. */
  function refusal(kind, holder, node) {
    var holderKind = holder ? kindOf(holder.id) : 'page';
    if (kind === 'inner' && holderKind === 'container') return t.innerInContainer;
    if (kind === 'inner' && insideInner(holder)) return t.noNesting;
    if (kind === 'container' && holderKind === 'column') return t.containerInColumn;
    if (kind === 'widget' && holderKind === 'page') return t.containerInColumn;
    if (kind === 'container' && containerDepth(holder) + nodeDepth(node || { type: 'container' }) > (boot.box.maxDepth || 6)) return t.tooDeep.replace(':n', boot.box.maxDepth || 6);
    return null;
  }

  /* True when a column sits inside an inner section, where another inner section is not allowed. */
  function insideInner(column) {
    var c = column && entry(column.id);
    return !!(c && c.parent && entry(c.parent.id) && entry(c.parent.id).kind === 'inner');
  }

  function addWidget(type) {
    var place = target();
    var node = makeWidget(type);
    var kind = kindFor(node, 'widget');
    var why = place ? refusal(kind, place.holder, node) : null;
    if (why) { showMessage(why, 'warning'); return; }
    commit(function () {
      if (!place && kind === 'container') {
        place = { list: spec.sections, at: spec.sections.length };
      } else if (!place) {
        var c = container('1');
        spec.sections.push(c);
        place = { list: c.children, at: 0 };
      }
      place.list.splice(place.at, 0, node);
    });
    select(node.id);
  }

  function move(id, step) {
    var e = entry(id);
    if (!e) return;
    var i = e.list.indexOf(e.node), j = i + step;
    if (j < 0 || j >= e.list.length) return;
    commit(function () { e.list.splice(i, 1); e.list.splice(j, 0, e.node); });
    highlight(true);
  }

  /*
   * Alt + Up/Down: the keyboard version of dragging. Inside its list the element trades places;
   * a widget at the edge of its column moves on into the previous or next column of the page.
   */
  /* Columns and containers, in page order: the places a widget can move into. */
  function holdersInOrder() {
    var out = [];
    (function walk(nodes, kind) {
      (nodes || []).forEach(function (n) {
        if (!isObject(n)) return;
        if (n.type === 'container') { out.push(n); walk(n.children, 'widget'); }
        else if (kind === 'column') { out.push(n); walk(n.widgets, 'widget'); }
        else if (kind === 'section' || n.type === 'inner-section') walk(n.columns, 'column');
      });
    })(spec.sections, 'section');
    return out;
  }

  function moveByKey(id, step) {
    var e = entry(id);
    if (!e) return;
    var i = e.list.indexOf(e.node), j = i + step;
    if (j >= 0 && j < e.list.length) {
      commit(function () { e.list.splice(i, 1); e.list.splice(j, 0, e.node); });
    } else if (e.kind === 'widget' || e.kind === 'inner' || (e.kind === 'container' && e.parent)) {
      var cols = holdersInOrder().filter(function (c) {
        if (c === e.parent) return true;
        if (refusal(e.kind, c, e.node)) return false;
        // Not into the node that is moving.
        for (var p = entry(c.id); p; p = p.parent ? entry(p.parent.id) : null) { if (p.node === e.node) return false; }
        return true;
      });
      var next = cols[cols.indexOf(e.parent) + step];
      if (!next) return;
      commit(function () {
        e.list.splice(i, 1);
        var items = itemsOf(next, kindOf(next.id));
        if (step > 0) items.unshift(e.node); else items.push(e.node);
      });
    } else {
      return;
    }
    // Say where it went, for screen readers, and keep the focus on it in the navigator.
    var moved = entry(id);
    var where = t.movedTo.replace(':item', label(moved)).replace(':n', moved.list.indexOf(moved.node) + 1).replace(':total', moved.list.length)
      .replace(':parent', moved.parent ? label(entry(moved.parent.id)) : t.page);
    $('[data-bld-state]', root).textContent = where;
    select(id);
    var row = treeEl.querySelector('.bld-node[data-id="' + id + '"] .bld-node-label');
    if (row && keyFromTree) row.focus();
  }
  var keyFromTree = false;

  function duplicate(id) {
    var e = entry(id);
    if (!e) return;
    var copy = freshIds(clone(e.node));
    commit(function () { e.list.splice(e.list.indexOf(e.node) + 1, 0, copy); });
    select(copy.id);
  }

  function remove(id) {
    var e = entry(id);
    if (!e) return;
    if (e.kind === 'column' && e.list.length < 2) { showMessage(t.onlyColumn, 'warning'); return; }
    var i = e.list.indexOf(e.node);
    commit(function () { e.list.splice(i, 1); });
    var next = e.list[i] || e.list[i - 1] || e.parent;
    select(next ? next.id : null);
  }

  function addColumn(id) {
    var e = entry(id);
    if (!e) return;
    var col = { id: newId('c'), width: 6, widgets: [] };
    commit(function () {
      var count = e.node.columns.length + 1;
      if (12 % count === 0) e.node.columns.forEach(function (c) { if (typeof c.width !== 'object') c.width = 12 / count; });
      col.width = 12 % count === 0 ? 12 / count : 6;
      e.node.columns.push(col);
    });
    select(col.id);
  }

  /* ---------- Drag and drop ----------
   * One plan for both the navigator and the preview: what is dragged (a new widget, a section preset,
   * or an existing node), over which node, and in which half of it. The move buttons stay as the keyboard way.
   */

  var dragging = null;

  function dropPlan(drag, targetId, ratio) {
    var target = entry(targetId);
    if (!drag || !target) return null;
    var after = ratio > 0.5, plan;

    if (drag.kind === 'section') {
      // A section, or a new section or container from the presets: always at the top level.
      var top = topSection(targetId);
      if (!top) return null;
      plan = { list: spec.sections, holder: null, index: spec.sections.indexOf(top.node) + (after ? 1 : 0), mark: top.node.id, pos: after ? 'after' : 'before' };
    } else if (drag.kind === 'column') {
      if (target.kind === 'column') plan = { list: target.list, index: target.list.indexOf(target.node) + (after ? 1 : 0), mark: target.node.id, pos: after ? 'after' : 'before' };
      else if (target.kind === 'section' || target.kind === 'inner') plan = { list: target.node.columns, index: target.node.columns.length, mark: target.node.id, pos: 'inside' };
      else return null;
    } else {
      // A widget, inner section or container. Near the top or bottom edge of a container it goes
      // next to it; elsewhere inside it. Widgets never go next to a top-level container.
      var edge = ratio < 0.25 || ratio > 0.75;
      if (target.kind === 'widget' || target.kind === 'inner' || (target.kind === 'container' && edge && (target.parent || drag.kind === 'container'))) {
        plan = { list: target.list, holder: target.parent, index: target.list.indexOf(target.node) + (after ? 1 : 0), mark: target.node.id, pos: after ? 'after' : 'before' };
      } else if (target.kind === 'column' || target.kind === 'container') {
        var items = itemsOf(target.node, target.kind);
        plan = { list: items, holder: target.node, index: items.length, mark: target.node.id, pos: 'inside' };
      } else if (target.kind === 'section' && drag.kind === 'container') {
        plan = { list: spec.sections, holder: null, index: spec.sections.indexOf(target.node) + (after ? 1 : 0), mark: target.node.id, pos: after ? 'after' : 'before' };
      } else {
        return null;
      }
      var moving = drag.id ? entry(drag.id) : null;
      if (refusal(drag.kind, plan.holder, moving ? moving.node : null)) return null;
    }

    if (drag.id) {
      // Not onto itself, and not into something it contains.
      for (var p = entry(plan.mark); p; p = p.parent ? entry(p.parent.id) : null) {
        if (p.node.id === drag.id) return null;
      }
      var moving = entry(drag.id);
      if (!moving) return null;
      if (moving.kind === 'column' && moving.list.length < 2 && moving.list !== plan.list) return null;
    }
    return plan;
  }

  function drop(drag, plan) {
    if (drag.id) {
      var e = entry(drag.id);
      commit(function () {
        var from = e.list.indexOf(e.node), at = plan.index;
        if (e.list === plan.list && from < at) at--;
        e.list.splice(from, 1);
        plan.list.splice(at, 0, e.node);
      });
      select(drag.id);
    } else if (drag.preset) {
      var node = drag.container ? container(drag.preset) : section(drag.preset.split('-').map(Number));
      commit(function () { plan.list.splice(plan.index, 0, node); });
      select(drag.container ? (node.children[0] || node).id : node.columns[0].id);
    } else if (drag.add) {
      var widget = makeWidget(drag.add);
      commit(function () { plan.list.splice(plan.index, 0, widget); });
      select(widget.id);
    }
  }

  function kindOf(id) {
    var e = entry(id);
    return e ? e.kind : null;
  }

  var marked = null;
  function mark(el, pos) {
    if (marked && (marked.el !== el || marked.pos !== pos)) unmark();
    if (!el) return;
    el.classList.add(el.ownerDocument === document ? 'bld-drop-' + pos : 'b-drop-' + pos);
    marked = { el: el, pos: pos };
  }
  function unmark() {
    if (!marked) return;
    marked.el.classList.remove('bld-drop-' + marked.pos, 'b-drop-' + marked.pos);
    marked = null;
  }
  function endDrag() {
    dragging = null;
    unmark();
    root.classList.remove('bld-dragging');
  }

  function startDrag(e, drag) {
    dragging = drag;
    root.classList.add('bld-dragging');
    e.dataTransfer.effectAllowed = drag.id ? 'move' : 'copy';
    try { e.dataTransfer.setData('text/plain', drag.id || drag.add || drag.preset); } catch (err) {}
  }

  root.addEventListener('dragstart', function (e) {
    var el;
    if ((el = e.target.closest('[data-bld-add]'))) startDrag(e, { add: el.dataset.bldAdd, kind: kindFor({ type: el.dataset.bldAdd }, 'widget') });
    else if ((el = e.target.closest('[data-bld-section]'))) startDrag(e, { preset: el.dataset.bldSection, kind: 'section' });
    else if ((el = e.target.closest('[data-bld-container]'))) startDrag(e, { preset: el.dataset.bldContainer, container: true, kind: 'section' });
    else if ((el = e.target.closest('.bld-node[data-id]'))) startDrag(e, { id: el.dataset.id, kind: kindOf(el.dataset.id) });
  });
  root.addEventListener('dragend', endDrag);

  treeEl.addEventListener('dragover', function (e) {
    var row = e.target.closest('.bld-node[data-id]');
    var rect = row && row.getBoundingClientRect();
    var plan = row && dropPlan(dragging, row.dataset.id, (e.clientY - rect.top) / rect.height);
    if (!plan) { unmark(); return; }
    e.preventDefault();
    e.dataTransfer.dropEffect = dragging.id ? 'move' : 'copy';
    mark(treeEl.querySelector('.bld-node[data-id="' + plan.mark + '"]'), plan.pos);
  });
  treeEl.addEventListener('dragleave', function (e) { if (!treeEl.contains(e.relatedTarget)) unmark(); });
  treeEl.addEventListener('drop', function (e) {
    var row = e.target.closest('.bld-node[data-id]');
    var rect = row && row.getBoundingClientRect();
    var plan = row && dropPlan(dragging, row.dataset.id, (e.clientY - rect.top) / rect.height);
    var drag = dragging;
    e.preventDefault();
    endDrag();
    if (plan) drop(drag, plan);
  });

  /* In the preview, the node under the pointer that this kind of drag can land on. */
  function previewTarget(doc, e) {
    if (!dragging || !e.target.closest) return null;
    var el = dragging.kind === 'section' ? e.target.closest('.b-section:not(.b-inner-section)[data-b-id], .b-con-top[data-b-id]')
      : dragging.kind === 'column' ? e.target.closest('.b-col[data-b-id]')
      : e.target.closest('.b-w[data-b-id], .b-inner-section[data-b-id], .b-col[data-b-id], .b-con[data-b-id]');
    if (!el) return null;
    var rect = el.getBoundingClientRect();
    var plan = dropPlan(dragging, el.getAttribute('data-b-id'), (e.clientY - rect.top) / rect.height);
    return plan ? { plan: plan, el: doc.querySelector('[data-b-id="' + plan.mark + '"]') } : null;
  }

  function wireFrameDrag(doc) {
    doc.addEventListener('dragstart', function (e) {
      var el = e.target.closest && e.target.closest('[data-b-id][draggable="true"]');
      if (el) startDrag(e, { id: el.getAttribute('data-b-id'), kind: kindOf(el.getAttribute('data-b-id')) });
    });
    doc.addEventListener('dragend', endDrag);
    doc.addEventListener('dragover', function (e) {
      var hit = previewTarget(doc, e);
      if (!hit) { unmark(); return; }
      e.preventDefault();
      e.dataTransfer.dropEffect = dragging.id ? 'move' : 'copy';
      mark(hit.el, hit.plan.pos);
    });
    doc.addEventListener('drop', function (e) {
      var hit = previewTarget(doc, e);
      var drag = dragging;
      e.preventDefault();
      endDrag();
      if (hit) drop(drag, hit.plan);
    });
  }

  /* Widgets in the preview can be dragged to a new place. */
  function makeDraggable(doc) {
    $$('.b-w[data-b-id], .b-inner-section[data-b-id], .b-con[data-b-id]', doc).forEach(function (el) { el.setAttribute('draggable', 'true'); });
  }

  /* ---------- Rendering the panels ---------- */

  function renderAll() {
    renderPalette();
    renderTree();
    renderInspector();
    renderProblems();
  }

  /* The widget list: groups that fold (remembered per browser) and a search across all of them. */

  function renderPalette() {
    var groups = {};
    var order = boot.templateContext ? ['template', 'layout', 'basic', 'content', 'media', 'site', 'advanced', 'custom'] : ['layout', 'basic', 'content', 'media', 'site', 'template', 'advanced', 'custom'];
    // Dynamic widgets for an article or a list are offered only in that kind of template.
    var offered = function (w) { return !w.context || w.context === boot.templateContext; };
    var all = [{ name: 'container', label: t.container, icon: 'landing', group: 'layout', description: t.emptyContainerHint }, { name: 'inner-section', label: t.innerSection, icon: 'columns', group: 'layout' }].concat(Object.keys(widgets).map(function (k) { return widgets[k]; }).filter(offered));
    all.forEach(function (w) { (groups[w.group] = groups[w.group] || []).push(w); });
    var structural = function (w) { return w.name === 'container' || w.name === 'inner-section'; };
    var group = function (key, label, items) {
      return '<details class="bld-group" data-bld-group="' + esc(key) + '"' + (closedGroups[key] ? '' : ' open') + '><summary>' + esc(label) + '<span class="bld-group-count">' + items.length + '</span></summary><div class="bld-palette">' + items.join('') + '</div></details>';
    };
    var html = '';
    Object.keys(groups).sort(function (a, b) { return (order.indexOf(a) + 99) % 99 - (order.indexOf(b) + 99) % 99; }).forEach(function (key) {
      var items = groups[key].sort(function (a, b) {
        if (structural(a) !== structural(b)) return structural(a) ? -1 : 1;
        return String(a.label).localeCompare(String(b.label));
      }).map(function (w) {
        var off = locked(w.name);
        var find = (w.label + ' ' + w.name + ' ' + (w.description || '')).toLowerCase();
        return '<button type="button" class="bld-widget" draggable="' + !off + '" data-bld-add="' + esc(w.name) + '" data-bld-find="' + esc(find) + '" title="' + esc(off ? t.needsCode : (w.description || w.label)) + '"' + (off ? ' disabled' : '') + '>' + icon(w.icon) + '<span>' + esc(w.label) + '</span></button>';
      });
      html += group(key, t.groups && t.groups[key] || key, items);
    });
    // Saved blocks (Design > Blocks): a click inserts a copy.
    if (blocks) {
      html += blocks.length
        ? group('blocks', t.savedBlocks, blocks.map(function (b) {
          return '<button type="button" class="bld-widget" data-bld-block="' + b.id + '" data-bld-find="' + esc(String(b.title).toLowerCase()) + '" title="' + esc(t.insertBlock) + '">' + icon('copy') + '<span>' + esc(b.title) + '</span></button>';
        }))
        : '<details class="bld-group" data-bld-group="blocks"' + (closedGroups.blocks ? '' : ' open') + '><summary>' + esc(t.savedBlocks) + '<span class="bld-group-count">0</span></summary><p class="bld-hint">' + esc(t.noBlocks) + '</p></details>';
    }
    $('[data-bld-palette]', root).innerHTML = html;
    filterPalette();
  }

  function filterPalette() {
    var input = $('[data-bld-search-input]', root);
    var q = input ? input.value.trim().toLowerCase() : '';
    var pane = $('[data-bld-pane="add"]', root);
    var any = false;
    $$('.bld-group', pane).forEach(function (g) {
      var tiles = $$('[data-bld-find]', g);
      var shown = 0;
      tiles.forEach(function (b) {
        var hit = !q || q.split(/\s+/).every(function (word) { return b.dataset.bldFind.indexOf(word) >= 0; });
        b.hidden = !hit;
        if (hit) shown++;
      });
      // While searching, the structure presets step aside and matching groups open.
      g.hidden = q ? shown === 0 : false;
      if (q && shown) { g.dataset.searchOpen = '1'; g.open = true; }
      else if (!q && g.dataset.searchOpen) { delete g.dataset.searchOpen; g.open = !closedGroups[g.dataset.bldGroup]; }
      any = any || shown > 0;
    });
    $('[data-bld-search-empty]', root).hidden = !q || any;
  }

  root.addEventListener('input', function (e) { if (e.target.matches('[data-bld-search-input]')) filterPalette(); });
  root.addEventListener('keydown', function (e) {
    if (!e.target.matches('[data-bld-search-input]')) return;
    if (e.key === 'Escape' && e.target.value) { e.preventDefault(); e.stopPropagation(); e.target.value = ''; filterPalette(); }
    // Enter adds the first match.
    if (e.key === 'Enter') {
      e.preventDefault();
      var first = $$('[data-bld-pane="add"] [data-bld-find]:not([hidden]):not(:disabled)', root)[0];
      if (first) first.click();
    }
  });
  root.addEventListener('toggle', function (e) {
    var g = e.target;
    if (!g.matches || !g.matches('.bld-group') || g.dataset.searchOpen) return;
    closedGroups[g.dataset.bldGroup] = !g.open;
    try { localStorage.setItem(GROUPS_KEY, JSON.stringify(closedGroups)); } catch (err) { /* not remembered */ }
  }, true);
  $$('details.bld-group', root).forEach(function (g) { if (g.dataset.bldGroup in closedGroups) g.open = !closedGroups[g.dataset.bldGroup]; });

  function loadBlocks() {
    if (!boot.blocks) return;
    request(boot.blocks, 'GET').then(readJson).then(function (result) {
      if (result.status !== 200) return;
      blocks = result.data.blocks || [];
      renderPalette();
    }).catch(function () {});
  }

  /*
   * A copy of a saved block, with new ids. A container goes where a widget would (or at the top
   * level); an old-style section always goes to the top level, after the selected one.
   */
  function insertBlock(id) {
    var block = (blocks || []).filter(function (b) { return String(b.id) === String(id); })[0];
    if (!block) return;
    var node = freshIds(clone(block.node));
    var e = selected ? entry(selected) : null;
    // Next to the selected container, never inside it: a block is usually a whole band.
    var place = node.type !== 'container' ? null
      : e && e.kind === 'container' ? { holder: e.parent, list: e.list, at: e.list.indexOf(e.node) + 1 }
      : target();
    var why = place ? refusal('container', place.holder, node) : null;
    if (why) place = null;
    commit(function () {
      if (!place) {
        var top = selected ? topSection(selected) : null;
        place = { list: spec.sections, at: top ? spec.sections.indexOf(top.node) + 1 : spec.sections.length };
      }
      place.list.splice(place.at, 0, node);
    });
    select(node.id);
  }

  function saveBlock(id, title) {
    var e = entry(id);
    if (!e || !title) return;
    request(boot.blocks, 'POST', { title: title, node: clone(e.node) }).then(readJson).then(function (result) {
      if (result.status !== 200) { showMessage(result.data.message || t.saveFailed || 'Error', 'danger'); return; }
      blockForm = null;
      (blocks = blocks || []).unshift(result.data.block);
      renderPalette();
      renderInspector();
      toast(result.data.message);
    }).catch(function () { showMessage(t.saveFailed || 'Error', 'danger'); });
  }

  function errorIds() {
    var ids = {};
    errors.forEach(function (message) {
      var m = /\(([a-z][a-z0-9]{2,15})\):/.exec(message);
      if (m) ids[m[1]] = true;
    });
    return ids;
  }

  function label(e) {
    var node = e.node;
    if (e.kind === 'section') return t.section + (node.anchor ? ' #' + node.anchor : '');
    if (e.kind === 'inner') return t.innerSection;
    if (e.kind === 'container') {
      var dir = isObject(node.style) ? node.style.direction : null;
      var row = (isObject(dir) ? dir.base : dir) === 'row';
      return t.container + (row ? ' ↔' : '') + (node.anchor ? ' #' + node.anchor : '');
    }
    if (e.kind === 'column') {
      var w = isObject(node.width) ? node.width.base : node.width;
      return t.column + ' ' + (w || 12) + '/12';
    }
    var def = widgets[node.type];
    var name = def ? def.label : node.type;
    var text = plain(textIn(node.text) || textIn(node.html) || textIn(node.title) || (node.buttons && node.buttons[0] && textIn(node.buttons[0].label)) || '');
    return name + (text ? ': ' + (text.length > 32 ? text.slice(0, 32) + '…' : text) : '');
  }

  function kindIcon(e) {
    if (e.kind === 'section' || e.kind === 'container') return icon('landing');
    if (e.kind === 'column' || e.kind === 'inner') return icon('columns');
    return icon((widgets[e.node.type] || {}).icon || 'page');
  }

  function renderTree() {
    if (!spec.sections.length) {
      treeEl.innerHTML = '<p class="bld-hint">' + esc(t.empty) + '</p>';
      return;
    }
    var bad = errorIds();
    var html = '';
    var walk = function (node, kind, depth) {
      var e = index[node.id];
      if (!e || !e.node) return;
      var first = e.list.indexOf(node) === 0, last = e.list.indexOf(node) === e.list.length - 1;
      html += '<div class="bld-node" draggable="true" role="treeitem" aria-selected="' + (node.id === selected) + '" style="--depth:' + depth + '" data-id="' + esc(node.id) + '">'
        + '<button type="button" class="bld-node-label" data-bld-select="' + esc(node.id) + '">' + esc(label(e)) + '</button>'
        + (bad[node.id] ? '<span class="bld-node-err" title="' + esc(t.errors) + '">' + icon('alert') + '</span>' : '')
        + '<span class="bld-node-tools">'
        + '<button type="button" class="bld-icon-btn" data-bld-move="-1" data-id="' + esc(node.id) + '" title="' + esc(t.moveUp) + '" aria-label="' + esc(t.moveUp) + '"' + (first ? ' disabled' : '') + '>' + icon('arrow-up') + '</button>'
        + '<button type="button" class="bld-icon-btn" data-bld-move="1" data-id="' + esc(node.id) + '" title="' + esc(t.moveDown) + '" aria-label="' + esc(t.moveDown) + '"' + (last ? ' disabled' : '') + '>' + icon('arrow-down') + '</button>'
        + '<button type="button" class="bld-icon-btn" data-bld-duplicate="' + esc(node.id) + '" title="' + esc(t.duplicate) + '" aria-label="' + esc(t.duplicate) + '">' + icon('copy') + '</button>'
        + '<button type="button" class="bld-icon-btn danger" data-bld-delete="' + esc(node.id) + '" title="' + esc(t.delete) + '" aria-label="' + esc(t.delete) + '">' + icon('trash') + '</button>'
        + '</span></div>';
      if (kind === 'section' || kind === 'inner') (node.columns || []).forEach(function (c) { walk(c, 'column', depth + 1); });
      if (kind === 'column') (node.widgets || []).forEach(function (w) { if (isObject(w)) walk(w, w.type === 'inner-section' ? 'inner' : 'widget', depth + 1); });
      if (kind === 'container') (node.children || []).forEach(function (c) { if (isObject(c)) walk(c, kindFor(c, 'widget'), depth + 1); });
    };
    spec.sections.forEach(function (s) { if (isObject(s)) walk(s, kindFor(s, 'section'), 0); });
    treeEl.innerHTML = html;
  }

  function renderProblems() {
    if (!errors.length && !warnings.length) { problemsEl.hidden = true; return; }
    var html = '<p class="bld-label">' + esc(t.errors) + ' (' + (errors.length + warnings.length) + ')</p>';
    errors.forEach(function (m) { html += problemButton(m, ''); });
    warnings.forEach(function (m) { html += problemButton(m, ' warning'); });
    problemsEl.innerHTML = html;
    problemsEl.hidden = false;
  }
  function problemButton(message, kind) {
    var m = /\(([a-z][a-z0-9]{2,15})\):\s*(.*)$/.exec(message);
    return '<button type="button" class="bld-problem' + kind + '"' + (m ? ' data-bld-select="' + esc(m[1]) + '"' : '') + '>' + esc(m ? m[2] : message) + '</button>';
  }

  /* ---------- Inspector ---------- */

  function renderInspector() {
    var e = selected ? entry(selected) : null;
    if (!e) { renderPagePanel(); return; }

    var tabs = e.kind === 'widget' ? ['content', 'style', 'advanced'] : e.kind === 'container' ? ['layout', 'style', 'advanced'] : ['style', 'advanced'];
    if (!tab || tabs.indexOf(tab) < 0) tab = tabs[0];

    var crumbs = [], p = e;
    while (p) { crumbs.unshift(p); p = p.parent ? entry(p.parent.id) : null; }
    var head = '<div class="bld-inspector-head"><div class="bld-crumbs">'
      + '<button type="button" data-bld-select="">' + esc(t.page) + '</button>' + icon('chevron-right')
      + crumbs.map(function (c, i) { return (i ? icon('chevron-right') : '') + '<button type="button" data-bld-select="' + esc(c.node.id) + '">' + esc(c.kind === 'widget' ? (widgets[c.node.type] || {}).label || c.node.type : t[c.kind === 'inner' ? 'innerSection' : c.kind]) + '</button>'; }).join('')
      + '</div><div class="bld-inspector-title"><span>' + esc(label(e)) + '</span><span>'
      + (boot.blocks && (e.kind === 'container' || e.kind === 'section') ? '<button type="button" class="bld-icon-btn" data-bld-save-block="' + esc(e.node.id) + '" title="' + esc(t.saveBlock) + '" aria-label="' + esc(t.saveBlock) + '" aria-expanded="' + (blockForm === e.node.id) + '">' + icon('download') + '</button>' : '')
      + '<button type="button" class="bld-icon-btn" data-bld-duplicate="' + esc(e.node.id) + '" title="' + esc(t.duplicate) + '" aria-label="' + esc(t.duplicate) + '">' + icon('copy') + '</button>'
      + '<button type="button" class="bld-icon-btn danger" data-bld-delete="' + esc(e.node.id) + '" title="' + esc(t.delete) + '" aria-label="' + esc(t.delete) + '">' + icon('trash') + '</button>'
      + '</span></div>'
      + (blockForm === e.node.id ? '<form class="bld-block-form" data-bld-block-form="' + esc(e.node.id) + '"><label class="bld-label" for="bld-block-name">' + esc(t.blockName) + '</label>'
        + '<div class="bld-block-row"><input class="input" id="bld-block-name" name="title" maxlength="255" required value="' + esc(e.node.anchor ? e.node.anchor.charAt(0).toUpperCase() + e.node.anchor.slice(1).replace(/-/g, ' ') : t[e.kind] || label(e)) + '">'
        + '<button class="btn btn-sm" type="submit">' + esc(t.save) + '</button><button class="btn btn-sm btn-ghost" type="button" data-bld-block-cancel>' + esc(t.cancel) + '</button></div></form>' : '')
      + '<div class="bld-tabs" role="tablist">'
      + tabs.map(function (name) { return '<button type="button" role="tab" class="bld-tab" data-bld-tab="' + name + '" aria-selected="' + (name === tab) + '">' + esc(t[name]) + '</button>'; }).join('')
      + '</div></div>';
    inspector.innerHTML = head + '<div class="bld-fields" data-bld-fields></div>';
    var body = $('[data-bld-fields]', inspector);

    if (tab === 'content') {
      var def = widgets[e.node.type];
      if (!def) { body.innerHTML = '<p class="bld-hint">' + esc(e.node.type) + '</p>'; return; }
      def.fields.forEach(function (f) { body.appendChild(field(f, e.node, e.node.id + '.' + f.key)); });
      if (locked(e.node.type)) {
        $$('input, textarea, select, button', body).forEach(function (el) { el.disabled = true; });
        var note = document.createElement('p');
        note.className = 'bld-hint';
        note.textContent = t.needsCode;
        body.insertBefore(note, body.firstChild);
      }
      if (!def.fields.length) body.innerHTML = '<p class="bld-hint">' + esc(def.description || '') + '</p>';
    } else if (tab === 'layout') {
      boxFields(e.node, boot.box.container, CONTAINER_LAYOUT, body);
      var dir = isObject(e.node.style) ? e.node.style.direction : null;
      if (dir === 'row' || (isObject(dir) && Object.keys(dir).some(function (k) { return dir[k] === 'row'; }))) hintEl(body, t.rowHint);
      if (!(e.node.children || []).length) hintEl(body, t.emptyContainerHint);
    } else if (tab === 'style') {
      styleFields(e, body);
    } else {
      advancedFields(e, body);
    }
  }

  function renderPagePanel() {
    var layouts = { site: t.layoutSite, blank: t.layoutBlank };
    inspector.innerHTML = '<div class="bld-inspector-head"><div class="bld-inspector-title"><span>' + esc(t.page) + '</span></div></div><div class="bld-fields" data-bld-fields></div>';
    var body = $('[data-bld-fields]', inspector);
    // A header or footer template is a part of other pages: it has no layout of its own.
    if (!boot.template || ['header', 'footer'].indexOf(boot.template) < 0) body.appendChild(wrap(t.layout, selectEl(Object.keys(layouts).map(function (k) { return [k, layouts[k]]; }), spec.layout || 'site', function (value) {
      commit(function () { spec.layout = value; });
    })));
    // The page's own CSS (anyone who may edit the page) and JavaScript (code.manage only).
    body.appendChild(codeField('css', t.css, t.cssHelp, false));
    body.appendChild(codeField('js', t.js, boot.code ? t.jsHelp : t.jsLocked, !boot.code));
    var hint = document.createElement('p');
    hint.className = 'bld-hint';
    hint.textContent = t.nothing;
    body.appendChild(hint);
  }

  function codeField(key, label, help, disabled) {
    var area = document.createElement('textarea');
    area.className = 'textarea code bld-code';
    area.rows = 10;
    area.spellcheck = false;
    area.value = typeof spec[key] === 'string' ? spec[key] : '';
    area.disabled = disabled;
    area.placeholder = key === 'css' ? '.promo { border-radius: 24px; }' : "document.querySelector('#pricing')…";
    area.addEventListener('keydown', function (e) {
      // Tab indents instead of leaving the field; Esc then Tab still moves on.
      if (e.key !== 'Tab' || e.shiftKey) return;
      e.preventDefault();
      var start = area.selectionStart;
      area.setRangeText('  ', start, area.selectionEnd, 'end');
      area.dispatchEvent(new Event('input', { bubbles: true }));
    });
    area.addEventListener('input', function () {
      edit(function () { if (area.value.trim() === '') delete spec[key]; else spec[key] = area.value; }, 'page.' + key);
    });
    return wrap(label, area, help);
  }

  /* Container settings, like Elementor's Layout and Style tabs. Everything else goes in the Advanced tab. */
  var CONTAINER_LAYOUT = ['direction', 'justify', 'align', 'wrap', 'gap', 'content_width', 'boxed_width', 'width', 'min_height', 'padding', 'margin', 'tag'];
  var CONTAINER_STYLE = ['background', 'image', 'overlay', 'background_color', 'color', 'radius', 'border_width', 'border_color', 'shadow', 'overflow'];
  var LENGTH = new RegExp(boot.box.length);

  function hintEl(body, text) {
    var hint = document.createElement('p');
    hint.className = 'bld-hint';
    hint.textContent = text;
    body.appendChild(hint);
  }

  /* Fields for the free values of a node's "style" (App\Support\Builder\Box), from their definitions. */
  function boxFields(node, table, keys, body) {
    var names = function (v) { return (t.values && t.values[v]) || String(v); };
    keys.forEach(function (key) {
      var def = table[key];
      var style = isObject(node.style) ? node.style : {};
      if (!def || (key === 'image' && style.background !== 'image')) return;
      var structural = key === 'background' || key === 'direction';
      var onChange = function (value) {
        (structural ? commit : edit)(function () {
          var st = isObject(node.style) ? node.style : (node.style = {});
          if (value === undefined || value === '') delete st[key]; else st[key] = value;
          if (!Object.keys(st).length) delete node.style;
        }, node.id + '.style.' + key);
      };
      var current = style[key], control, help = null;
      if (def.type === 'enum') {
        control = def.responsive
          ? responsiveEl(def.values, current, true, onChange, names)
          : selectEl([['', '—']].concat(def.values.map(function (v) { return [v, names(v)]; })), current == null ? '' : current, onChange);
      } else if (def.type === 'length' || def.type === 'box') {
        control = sizeEl(def, current, onChange);
        if (def.type === 'box') help = t.boxHelp;
      } else if (def.type === 'color') {
        control = colorEl(current || '', onChange);
      } else {
        control = imageEl(current || '', onChange);
        help = t.imageHelp;
      }
      body.appendChild(wrap((t.box && t.box[key]) || key, control, help));
    });
  }

  function sizeOk(def, value) {
    var parts = def.type === 'box' ? value.split(/\s+/) : [value];
    return parts.length <= 4 && parts.every(function (p) {
      return LENGTH.test(p) && (def.negative || p.charAt(0) !== '-') && (def.auto || p !== 'auto');
    });
  }

  /* One text box, or one per screen. A value that is not a valid size is marked and not saved. */
  function sizeEl(def, current, onChange) {
    var vals = isObject(current) ? current : (current == null ? {} : { base: current });
    var screens = def.responsive ? [['base', t.desktop], ['tablet', t.tablet], ['mobile', t.mobile]] : [['base', null]];
    var box = document.createElement('div');
    box.className = def.responsive ? 'bld-resp' : 'bld-size';
    var inputs = {};
    screens.forEach(function (pair) {
      var input = document.createElement('input');
      input.type = 'text';
      input.className = 'input mono';
      input.spellcheck = false;
      input.value = vals[pair[0]] || '';
      input.placeholder = pair[0] === 'base' ? (def.type === 'box' ? '24px' : (def.auto ? 'auto' : '24px')) : '↑';
      if (pair[0] !== 'base') input.title = t.inherit;
      input.addEventListener('input', emit);
      inputs[pair[0]] = input;
      if (!pair[1]) { box.appendChild(input); return; }
      var label = document.createElement('label');
      label.textContent = pair[1];
      label.appendChild(input);
      box.appendChild(label);
    });
    function emit() {
      var out = {}, ok = true;
      Object.keys(inputs).forEach(function (name) {
        var value = inputs[name].value.trim().replace(/\s+/g, ' ');
        var good = value === '' || sizeOk(def, value);
        inputs[name].setAttribute('aria-invalid', good ? 'false' : 'true');
        inputs[name].title = good ? (name === 'base' ? '' : t.inherit) : t.invalidSize;
        if (!good) ok = false; else if (value !== '') out[name] = value;
      });
      if (!ok) return;
      var keys = Object.keys(out);
      onChange(!keys.length ? undefined : (keys.length === 1 && out.base !== undefined ? out.base : out));
    }
    return box;
  }

  /* A color as text (#hex, rgb(), var(--primary)) with a picker and the theme colors to choose from. */
  var colorList = null;
  function colorEl(value, onChange) {
    if (!colorList) {
      colorList = document.createElement('datalist');
      colorList.id = 'bld-theme-colors';
      boot.box.colors.forEach(function (name) { var o = document.createElement('option'); o.value = 'var(--' + name + ')'; colorList.appendChild(o); });
      ['transparent', 'currentColor'].forEach(function (v) { var o = document.createElement('option'); o.value = v; colorList.appendChild(o); });
      root.appendChild(colorList);
    }
    var box = document.createElement('div');
    box.className = 'bld-color';
    var picker = document.createElement('input');
    picker.type = 'color';
    picker.className = 'bld-color-pick';
    picker.setAttribute('aria-label', t.choose);
    var text = document.createElement('input');
    text.type = 'text';
    text.className = 'input mono';
    text.spellcheck = false;
    text.placeholder = '#ff5500 · var(--primary)';
    text.setAttribute('list', 'bld-theme-colors');
    text.value = value;
    var sync = function () { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; };
    sync();
    text.addEventListener('input', function () { sync(); onChange(text.value.trim()); });
    picker.addEventListener('input', function () { text.value = picker.value; onChange(picker.value); });
    box.appendChild(picker);
    box.appendChild(text);
    return box;
  }

  function styleFields(e, body) {
    if (e.kind === 'container') {
      boxFields(e.node, boot.box.container, CONTAINER_STYLE, body);
      return;
    }
    if (e.kind === 'section' || e.kind === 'inner') {
      if (!isObject(e.node.style)) e.node.style = {};
      tokenFields(tokens.section, e.node.style, body, e.node.id);
      if (e.node.style.background === 'image') {
        body.appendChild(wrap(t.image, imageEl(e.node.style.image || '', function (value) {
          edit(function () { if (value) e.node.style.image = value; else delete e.node.style.image; }, e.node.id + '.image');
        }), t.imageHelp));
      }
      var add = document.createElement('button');
      add.type = 'button';
      add.className = 'btn btn-outline btn-sm';
      add.innerHTML = icon('plus') + ' ' + esc(t.addColumn);
      add.addEventListener('click', function () { addColumn(e.node.id); });
      body.appendChild(add);
    } else if (e.kind === 'column') {
      body.appendChild(wrap(t.width, responsiveEl(tokens.widths, e.node.width, false, function (value) {
        commit(function () { if (value === undefined) e.node.width = 12; else e.node.width = value; }, e.node.id + '.width');
      })));
      tokenFields(tokens.column, e.node, body, e.node.id);
      var hint = document.createElement('p');
      hint.className = 'bld-hint';
      hint.textContent = t.addWidgetHere;
      body.appendChild(hint);
    } else {
      tokenFields(tokens.widget, e.node, body, e.node.id);
      var head = document.createElement('p');
      head.className = 'bld-label';
      head.textContent = t.sizeSpacing;
      body.appendChild(head);
      boxFields(e.node, boot.box.widget, Object.keys(boot.box.widget), body);
    }
  }

  function tokenFields(table, holder, body, id) {
    Object.keys(table).forEach(function (key) {
      var token = table[key];
      var name = (t.tokens && t.tokens[key]) || key;
      var onChange = function (value) {
        var structural = key === 'background';
        (structural ? commit : edit)(function () { if (value === undefined || value === '') delete holder[key]; else holder[key] = value; }, id + '.' + key);
      };
      body.appendChild(wrap(name, token.responsive
        ? responsiveEl(token.values, holder[key], true, onChange)
        : selectEl([['', '—']].concat(token.values.map(function (v) { return [v, v]; })), holder[key] == null ? '' : holder[key], onChange)));
    });
  }

  function advancedFields(e, body) {
    var node = e.node;
    var hide = document.createElement('div');
    hide.className = 'bld-checks';
    [['base', t.desktop], ['tablet', t.tablet], ['mobile', t.mobile]].forEach(function (pair) {
      var label = document.createElement('label');
      label.className = 'bld-check';
      var box = document.createElement('input');
      box.type = 'checkbox';
      box.className = 'checkbox';
      box.checked = !!(isObject(node.hide) && node.hide[pair[0]] === true);
      box.addEventListener('change', function () {
        commit(function () {
          var h = isObject(node.hide) ? node.hide : {};
          if (box.checked) h[pair[0]] = true; else delete h[pair[0]];
          if (Object.keys(h).length) node.hide = h; else delete node.hide;
        });
      });
      label.appendChild(box);
      label.appendChild(document.createTextNode(pair[1]));
      hide.appendChild(label);
    });
    body.appendChild(wrap(t.hideOn, hide));
    body.appendChild(wrap(t.anchor, inputEl('text', node.anchor || '', function (value) {
      edit(function () { if (value) node.anchor = value; else delete node.anchor; }, node.id + '.anchor');
    }), t.anchorHelp));
    body.appendChild(wrap(t['class'], inputEl('text', node['class'] || '', function (value) {
      edit(function () { if (value.trim()) node['class'] = value; else delete node['class']; }, node.id + '.class');
    }), t.classHelp));
    var id = inputEl('text', node.id, function () {});
    id.readOnly = true;
    id.classList.add('mono');
    body.appendChild(wrap(t.id, id));
  }

  /* One widget field, from its definition in widget.json. holder is the widget or a repeater item. */
  function field(f, holder, key) {
    var help = f.help || null;
    var value = holder[f.key];

    if (f.type === 'repeater') return wrap(f.label || f.key, repeaterEl(f, holder, key));

    var control;
    var set = function (v) {
      edit(function () {
        if (f.translatable && isObject(holder[f.key])) {
          var copy = Object.assign({}, holder[f.key]);
          copy[locale] = v;
          holder[f.key] = copy;
        } else if (v === '' || v === undefined) {
          delete holder[f.key];
        } else {
          holder[f.key] = v;
        }
      }, key);
    };
    var shown = f.translatable ? textIn(value) : value;
    if (shown === undefined || shown === null) shown = f.translatable ? '' : f['default'];

    switch (f.type) {
      case 'textarea':
      case 'richtext':
        control = document.createElement('textarea');
        control.className = 'textarea' + (f.type === 'richtext' ? ' code' : '');
        control.value = shown || '';
        control.addEventListener('input', function () { set(control.value); });
        if (f.type === 'richtext') help = t.html;
        break;
      case 'image':
        control = imageEl(shown || '', set);
        break;
      case 'file':
        control = imageEl(shown || '', set, f.accept || []);
        break;
      case 'select':
        if (f.responsive) {
          control = responsiveEl(f.options, value === undefined ? f['default'] : value, false, set);
        } else {
          control = selectEl(f.options.map(function (o) { return [o, (t.options && t.options[o]) || o]; }), shown, set);
        }
        break;
      case 'category':
        control = selectEl([['', t.none]].concat(Object.keys(categories).map(function (slug) { return [slug, categories[slug]]; })), shown || '', set);
        break;
      case 'toggle':
        control = document.createElement('label');
        control.className = 'bld-check';
        var box = document.createElement('input');
        box.type = 'checkbox';
        box.className = 'checkbox';
        box.checked = shown === true;
        box.addEventListener('change', function () { set(box.checked); });
        control.appendChild(box);
        control.appendChild(document.createTextNode(f.label || f.key));
        return wrap('', control, help);
      case 'number':
        control = inputEl('number', shown == null ? '' : shown, function (v) { set(v === '' ? undefined : Number(v)); });
        if (f.min != null) control.min = f.min;
        if (f.max != null) control.max = f.max;
        break;
      default:
        control = inputEl(f.type === 'url' ? 'text' : 'text', shown || '', set);
        if (f.type === 'url') control.placeholder = 'https://… / #… / /path';
    }

    var box2 = wrap(f.label || f.key, control, help, f.translatable);
    if (f.translatable) box2.appendChild(translateNote(f, holder));
    return box2;
  }

  function translateNote(f, holder) {
    var note = document.createElement('div');
    note.className = 'bld-note';
    var value = holder[f.key];
    if (isObject(value)) {
      if (!value[locale]) note.textContent = t.missing;
      else note.hidden = true;
      return note;
    }
    if (typeof value !== 'string' || value === '') { note.hidden = true; return note; }
    note.innerHTML = '<span>' + esc(t.same) + '</span>';
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline btn-sm';
    button.textContent = t.translate;
    button.addEventListener('click', function () {
      commit(function () {
        var all = {};
        $$('[data-bld-locale]', root).forEach(function (b) { all[b.dataset.bldLocale] = value; });
        holder[f.key] = all;
      });
    });
    note.appendChild(button);
    return note;
  }

  // Which repeater items are open, by field path and index, so a re-render keeps them as they were.
  var openItems = {};

  function repeaterEl(f, holder, key) {
    var list = Array.isArray(holder[f.key]) ? holder[f.key] : [];
    var isOpen = function (i) { var v = openItems[key + '.' + i]; return v === undefined ? list.length === 1 : v; };
    var box = document.createElement('div');
    box.className = 'bld-repeater';
    list.forEach(function (item, i) {
      if (!isObject(item)) return;
      var card = document.createElement('div');
      card.className = 'bld-item';
      // Name the item after its first text, such as a caption, rather than an image path or link.
      var first = f.fields.filter(function (sub) { return sub.type === 'text' || sub.type === 'textarea'; }).find(function (sub) { return plain(textIn(item[sub.key])); }) || f.fields[0];
      var title = first ? plain(textIn(item[first.key])) : '';
      var open = isOpen(i);
      card.innerHTML = '<div class="bld-item-head"><button type="button" class="bld-item-toggle" data-act="toggle" aria-expanded="' + open + '">' + icon('chevron-right') + '<span>' + esc((t.item || 'Item') + ' ' + (i + 1) + (title ? ': ' + title : '')) + '</span></button>'
        + '<button type="button" class="bld-icon-btn" data-act="up" title="' + esc(t.moveUp) + '" aria-label="' + esc(t.moveUp) + '"' + (i === 0 ? ' disabled' : '') + '>' + icon('arrow-up') + '</button>'
        + '<button type="button" class="bld-icon-btn" data-act="down" title="' + esc(t.moveDown) + '" aria-label="' + esc(t.moveDown) + '"' + (i === list.length - 1 ? ' disabled' : '') + '>' + icon('arrow-down') + '</button>'
        + '<button type="button" class="bld-icon-btn danger" data-act="remove" title="' + esc(t.delete) + '" aria-label="' + esc(t.delete) + '">' + icon('trash') + '</button></div>';
      var body = document.createElement('div');
      body.className = 'bld-item-body';
      body.hidden = !open;
      f.fields.forEach(function (sub) { body.appendChild(field(sub, item, key + '.' + i + '.' + sub.key)); });
      card.appendChild(body);
      card.querySelector('.bld-item-head').addEventListener('click', function (e) {
        var act = e.target.closest('[data-act]');
        if (!act) return;
        if (act.dataset.act === 'toggle') {
          var now = body.hidden;
          body.hidden = !now;
          act.setAttribute('aria-expanded', String(now));
          openItems[key + '.' + i] = now;
          return;
        }
        var states = list.map(function (_, n) { return isOpen(n); });
        commit(function () {
          var arr = holder[f.key];
          if (act.dataset.act === 'remove') { arr.splice(i, 1); states.splice(i, 1); }
          else { var j = act.dataset.act === 'up' ? i - 1 : i + 1; arr.splice(j, 0, arr.splice(i, 1)[0]); states.splice(j, 0, states.splice(i, 1)[0]); }
          // The open state follows its item.
          for (var n = 0; n <= list.length; n++) delete openItems[key + '.' + n];
          states.forEach(function (v, n) { openItems[key + '.' + n] = v; });
        });
      });
      box.appendChild(card);
    });
    if (!f.max_items || list.length < f.max_items) {
      var add = document.createElement('button');
      add.type = 'button';
      add.className = 'btn btn-outline btn-sm';
      add.innerHTML = icon('plus') + ' ' + esc(t.addItem);
      add.addEventListener('click', function () {
        var states = list.map(function (_, n) { return isOpen(n); });
        commit(function () {
          if (!Array.isArray(holder[f.key])) holder[f.key] = [];
          holder[f.key].push({});
          // Keep the others as they were and open the new one.
          states.forEach(function (v, n) { openItems[key + '.' + n] = v; });
          openItems[key + '.' + (holder[f.key].length - 1)] = true;
        });
      });
      box.appendChild(add);
    }
    return box;
  }

  /* ---------- Small controls ---------- */

  function wrap(text, control, help, translatable) {
    var box = document.createElement('div');
    box.className = 'bld-field';
    if (text) {
      var label = document.createElement('label');
      label.className = 'label';
      label.textContent = text;
      if (translatable) {
        var lang = document.createElement('span');
        lang.className = 'bld-lang';
        lang.textContent = locale.toUpperCase();
        label.appendChild(lang);
      }
      var input = control.matches && control.matches('input,select,textarea') ? control : null;
      if (input) { input.id = 'bld-' + Math.random().toString(36).slice(2, 9); label.htmlFor = input.id; }
      box.appendChild(label);
    }
    box.appendChild(control);
    if (help) {
      var p = document.createElement('p');
      p.className = 'help';
      p.textContent = help;
      box.appendChild(p);
    }
    return box;
  }

  function inputEl(type, value, onInput) {
    var input = document.createElement('input');
    input.type = type;
    input.className = 'input';
    input.value = value == null ? '' : value;
    input.addEventListener('input', function () { onInput(input.value); });
    return input;
  }

  function selectEl(options, value, onChange) {
    var select = document.createElement('select');
    select.className = 'select';
    options.forEach(function (o) {
      var opt = document.createElement('option');
      opt.value = o[0];
      opt.textContent = o[1];
      if (String(o[0]) === String(value == null ? '' : value)) opt.selected = true;
      select.appendChild(opt);
    });
    select.addEventListener('change', function () {
      var raw = select.value;
      var match = options.filter(function (o) { return String(o[0]) === raw; })[0];
      onChange(match ? match[0] : raw);
    });
    return select;
  }

  /* A value for every screen, or one per screen: "lg" or {"base": "lg", "mobile": "sm"}. */
  function responsiveEl(values, current, emptyBase, onChange, names) {
    var vals = isObject(current) ? current : { base: current };
    var box = document.createElement('div');
    box.className = 'bld-resp';
    var selects = {};
    [['base', t.desktop], ['tablet', t.tablet], ['mobile', t.mobile]].forEach(function (pair) {
      var label = document.createElement('label');
      label.textContent = pair[1];
      var first = pair[0] === 'base' ? (emptyBase ? [['', '—']] : []) : [['', '↑']];
      var select = selectEl(first.concat(values.map(function (v) { return [v, names ? names(v) : String(v)]; })), vals[pair[0]] == null ? '' : vals[pair[0]], function () { emit(); });
      if (pair[0] !== 'base') select.title = t.inherit;
      selects[pair[0]] = select;
      label.appendChild(select);
      box.appendChild(label);
    });
    function read(name) {
      var raw = selects[name].value;
      if (raw === '') return undefined;
      var match = values.filter(function (v) { return String(v) === raw; })[0];
      return match === undefined ? raw : match;
    }
    function emit() {
      var out = {};
      ['base', 'tablet', 'mobile'].forEach(function (name) { var v = read(name); if (v !== undefined) out[name] = v; });
      var keys = Object.keys(out);
      onChange(!keys.length ? undefined : (keys.length === 1 && out.base !== undefined ? out.base : out));
    }
    return box;
  }

  // An image field, or with accept (["mp4", "webm"]) a file field. The media picker lists images only.
  function imageEl(value, onInput, accept) {
    var box = document.createElement('div');
    box.className = 'media-field';
    box.setAttribute('data-media-field', '');
    var input = inputEl('text', value, onInput);
    input.placeholder = 'uploads/… · https://…' + (accept ? ' (' + accept.join(', ') + ')' : '');
    box.appendChild(input);
    if (t.mediaPicker && !accept) {
      var pick = document.createElement('button');
      pick.type = 'button';
      pick.className = 'btn btn-outline';
      pick.setAttribute('data-media-pick', '');
      pick.textContent = t.choose;
      box.appendChild(pick);
    }
    if (!t.mediaPicker) return box;
    // Upload into the media library, like the classic editor.
    var file = document.createElement('input');
    file.type = 'file';
    file.accept = accept ? accept.map(function (x) { return '.' + x; }).join(',') : 'image/png,image/jpeg,image/webp,image/avif,image/gif';
    file.hidden = true;
    var up = document.createElement('button');
    up.type = 'button';
    up.className = 'btn btn-outline';
    up.textContent = t.upload;
    up.title = t.uploadHelp;
    up.addEventListener('click', function () { file.click(); });
    file.addEventListener('change', function () {
      if (!file.files.length) return;
      var form = new FormData();
      form.append('file', file.files[0]);
      up.disabled = true;
      up.textContent = t.uploading;
      fetch(root.dataset.uploadUrl, {
        method: 'POST', body: form, credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tokenInput ? tokenInput.value : '' }
      }).then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (data) {
          if (!r.ok) throw new Error(data.message || (data.errors && Object.values(data.errors)[0][0]) || t.failed);
          input.value = data.path;
          input.dispatchEvent(new Event('input', { bubbles: true }));
        });
      }).catch(function (err) { showMessage(err.message, 'danger'); })
        .then(function () { up.disabled = false; up.textContent = t.upload; file.value = ''; });
    });
    box.appendChild(up);
    box.appendChild(file);
    return box;
  }

  /* ---------- Events ---------- */

  /* Phones: one panel at a time, as a sheet over the preview, picked from the tab bar at the bottom. */
  function mobile(mode) {
    var body = $('.bld-body', root);
    mode = mode === 'preview' ? null : mode;
    body.classList.toggle('bld-show-left', mode === 'add' || mode === 'tree');
    body.classList.toggle('bld-show-right', mode === 'edit');
    if (mode === 'add' || mode === 'tree') showLeft(mode);
    $$('[data-bld-mobile]', root).forEach(function (b) {
      if (b.tagName === 'BUTTON') b.setAttribute('aria-pressed', String(b.dataset.bldMobile === (mode || 'preview')));
    });
  }
  function afterAdd() { if (phone.matches) mobile('edit'); }
  phone.addEventListener('change', function () { mobile(null); });

  /* The "More" menu in the top bar: closes on Escape, on a choice, or on a click outside. */
  function moreMenu(open) {
    var toggle = $('[data-bld-more-toggle]', root);
    var menu = $('#bld-more-menu', root);
    toggle.setAttribute('aria-expanded', String(open));
    menu.hidden = !open;
    if (open) { var first = $('.bld-menu-item:not([hidden])', menu); if (first) first.focus(); }
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-bld-more]') && !$('#bld-more-menu', root).hidden) moreMenu(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !$('#bld-more-menu', root).hidden) { moreMenu(false); $('[data-bld-more-toggle]', root).focus(); }
  });

  function showLeft(name) {
    $$('[data-bld-left]', root).forEach(function (b) { b.setAttribute('aria-selected', String(b.dataset.bldLeft === name)); });
    $$('[data-bld-pane]', root).forEach(function (p) { p.hidden = p.dataset.bldPane !== name; });
  }

  root.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-bld-block-form]');
    if (!form) return;
    e.preventDefault();
    saveBlock(form.dataset.bldBlockForm, form.elements.title.value.trim());
  });

  root.addEventListener('click', function (e) {
    var el;
    if ((el = e.target.closest('[data-bld-select]'))) { select(el.dataset.bldSelect || null); return; }
    if ((el = e.target.closest('[data-bld-move]'))) { move(el.dataset.id, Number(el.dataset.bldMove)); return; }
    if ((el = e.target.closest('[data-bld-duplicate]'))) { duplicate(el.dataset.bldDuplicate); return; }
    if ((el = e.target.closest('[data-bld-save-block]'))) {
      blockForm = blockForm === el.dataset.bldSaveBlock ? null : el.dataset.bldSaveBlock;
      renderInspector();
      var name = $('#bld-block-name', root);
      if (name) { name.focus(); name.select(); }
      return;
    }
    if ((el = e.target.closest('[data-bld-block-cancel]'))) { blockForm = null; renderInspector(); return; }
    if ((el = e.target.closest('[data-bld-block]'))) { insertBlock(el.dataset.bldBlock); afterAdd(); return; }
    if ((el = e.target.closest('[data-bld-delete]'))) { remove(el.dataset.bldDelete); return; }
    if ((el = e.target.closest('[data-bld-add]'))) { addWidget(el.dataset.bldAdd); afterAdd(); return; }
    if ((el = e.target.closest('[data-bld-section]'))) { addSection(el.dataset.bldSection); afterAdd(); return; }
    if ((el = e.target.closest('[data-bld-container]'))) { addSection(el.dataset.bldContainer, true); afterAdd(); return; }
    if ((el = e.target.closest('[data-bld-mobile]'))) { mobile(el.dataset.bldMobile); return; }
    if ((el = e.target.closest('[data-bld-more-toggle]'))) { moreMenu(el.getAttribute('aria-expanded') !== 'true'); return; }
    if (e.target.closest('.bld-menu-item')) moreMenu(false);
    if ((el = e.target.closest('[data-bld-tab]'))) { tab = el.dataset.bldTab; renderInspector(); return; }
    if ((el = e.target.closest('[data-bld-left]'))) { showLeft(el.dataset.bldLeft); return; }
    if ((el = e.target.closest('[data-bld-locale]'))) {
      locale = el.dataset.bldLocale;
      $$('[data-bld-locale]', root).forEach(function (b) { b.setAttribute('aria-pressed', String(b === el)); });
      renderTree();
      renderInspector();
      schedulePreview(0);
      return;
    }
    if ((el = e.target.closest('[data-bld-panel]'))) {
      setPanel(el.dataset.bldPanel, el.getAttribute('aria-pressed') !== 'true');
      return;
    }
    if ((el = e.target.closest('[data-bld-device]'))) {
      stage.dataset.device = el.dataset.bldDevice;
      $$('[data-bld-device]', root).forEach(function (b) { b.setAttribute('aria-pressed', String(b === el)); });
      return;
    }
    if (e.target.closest('[data-bld-undo]')) { restore(history, future); return; }
    if (e.target.closest('[data-bld-redo]')) { restore(future, history); return; }
    if (e.target.closest('[data-bld-publish]')) { publish(); return; }
    if (e.target.closest('[data-bld-reload]')) { reload(); return; }
    if (e.target.closest('[data-bld-overwrite]')) { save(true); return; }
  });

  function keys(e) {
    if (e.key === 'Escape' && selected && !(e.target && e.target.closest && e.target.closest('.sheet, .modal'))) { select(null); return; }
    if (e.altKey && !e.ctrlKey && !e.metaKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown') && selected
      && !(e.target && e.target.matches && e.target.matches('input,textarea,select,[contenteditable]'))
      && !(e.target && e.target.closest && e.target.closest('.sheet, .modal'))) {
      e.preventDefault();
      keyFromTree = !!(e.target && e.target.closest && e.target.closest('[data-bld-tree]'));
      moveByKey(selected, e.key === 'ArrowUp' ? -1 : 1);
      return;
    }
    var mod = e.ctrlKey || e.metaKey;
    if (!mod) return;
    var key = e.key.toLowerCase();
    if (key === 's') { e.preventDefault(); save(false); return; }
    var typing = e.target && e.target.matches && e.target.matches('input,textarea,select');
    if (typing) return;
    if (key === 'z' && !e.shiftKey) { e.preventDefault(); restore(history, future); }
    else if ((key === 'z' && e.shiftKey) || key === 'y') { e.preventDefault(); restore(future, history); }
  }
  document.addEventListener('keydown', keys);

  /* The side panels can be hidden to give the preview more room. Remembered per browser only. */
  var PANELS_KEY = 'dyskflow.builder.panels';
  function setPanel(side, open, quiet) {
    var body = $('.bld-body', root);
    body.classList.toggle('bld-hide-' + side, !open);
    var button = $('[data-bld-panel="' + side + '"]', root);
    button.setAttribute('aria-pressed', String(open));
    $('#bld-' + side, root).hidden = !open;
    if (quiet) return;
    try {
      var saved = JSON.parse(localStorage.getItem(PANELS_KEY) || '{}');
      saved[side] = open;
      localStorage.setItem(PANELS_KEY, JSON.stringify(saved));
    } catch (err) { /* private mode: not remembered */ }
  }
  try {
    var panels = JSON.parse(localStorage.getItem(PANELS_KEY) || '{}');
    ['left', 'right'].forEach(function (side) { if (panels[side] === false) setPanel(side, false, true); });
  } catch (err) { /* nothing saved */ }

  window.addEventListener('beforeunload', function (e) {
    if (dirty() || saving) { e.preventDefault(); e.returnValue = ''; }
  });

  loadBlocks();
  schedulePreview(0);
})();
