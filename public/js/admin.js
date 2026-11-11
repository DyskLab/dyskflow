/* Admin behaviour without dependencies: theme, right sheets, confirmations, tables, media, editor, SEO. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var i18n = window.CMS_I18N || {};

  /* ---------- Theme ---------- */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-theme-toggle]');
    if (!toggle) return;
    var dark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (err) {}
    var themeColor = $('meta[name="theme-color"]');
    if (themeColor) themeColor.content = dark ? '#0a0a0a' : '#ffffff';
  });

  /* ---------- Forms: focus, field errors, loading state, unsaved changes ---------- */
  var focusTarget = $('[data-autofocus]');
  if (focusTarget && matchMedia('(pointer: fine)').matches) focusTarget.focus();

  function showFieldErrors() {
    var errors = window.CMS_ERRORS;
    if (!errors) return;
    var controls = $$('input,select,textarea'), keys = Object.keys(errors), first = null, shown = 0;
    keys.forEach(function (key) {
      var name = key.split('.')[0];
      var named = controls.filter(function (c) { return c.name === name || c.name === name + '[]'; });
      if (!named.length) return;
      var control = named.filter(function (c) { return c.type !== 'hidden'; })[0] || named[0];
      var holder = control.closest('.field') || control.parentNode;
      /* The editor keeps its value in a hidden input: mark the visible area instead. */
      var target = control.type === 'hidden' ? ($('[contenteditable]', holder) || $('input:not([type=hidden]),select,textarea', holder)) : control;
      if (!target) return;
      var message = document.createElement('p');
      message.className = 'error';
      message.id = 'error-' + name;
      message.textContent = errors[key][0];
      holder.appendChild(message);
      target.setAttribute('aria-invalid', 'true');
      target.setAttribute('aria-describedby', message.id);
      first = first || target;
      shown++;
    });
    /* The summary stays when an error has no field on this page. */
    var summary = $('[data-error-summary]');
    if (summary && shown === keys.length) summary.hidden = true;
    if (first) first.focus();
  }
  showFieldErrors();

  document.addEventListener('submit', function (e) {
    var button = e.submitter, form = e.target;
    if (!button || (form.getAttribute('method') || '').toLowerCase() !== 'post' || form.hasAttribute('data-no-loading')) return;
    /* Waits a tick: the request carries the button's value, and other handlers may still cancel it. */
    setTimeout(function () {
      if (e.defaultPrevented) return;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
    }, 0);
  });
  /* The back button can restore a page with its button still disabled. */
  window.addEventListener('pageshow', function () {
    $$('[aria-busy="true"]').forEach(function (button) { button.disabled = false; button.removeAttribute('aria-busy'); });
  });

  $$('[data-dirty-guard]').forEach(function (form) {
    var dirty = false;
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    /* The browser's own leave-page prompt: closing a tab cannot be caught by a right sheet. */
    window.addEventListener('beforeunload', function (e) {
      if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
  });

  /* ---------- Sidebar: a drawer on phones, minimized to icons on larger screens ---------- */
  var root = document.documentElement, sidebarToggle = $('.menu-toggle');
  var isPhone = function () { return matchMedia('(max-width: 768px)').matches; };
  var syncToggle = function () {
    if (!sidebarToggle) return;
    sidebarToggle.setAttribute('aria-expanded', isPhone() ? String($('.sidebar').classList.contains('open')) : String(!root.classList.contains('sidebar-collapsed')));
  };
  syncToggle();
  document.addEventListener('click', function (e) {
    var sidebar = $('.sidebar');
    if (!sidebar) return;
    if (e.target.closest('.menu-toggle')) {
      if (isPhone()) {
        sidebar.classList.toggle('open');
      } else {
        var collapsed = root.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (err) {}
      }
      syncToggle();
    } else if (!e.target.closest('.sidebar')) {
      sidebar.classList.remove('open');
      syncToggle();
    }
  });

  /* ---------- Notifications in the header ---------- */
  var notif = $('[data-notifications]');
  if (notif) {
    var notifToggle = $('.notif-toggle', notif), notifPanel = $('.notif-panel', notif);
    var setNotifOpen = function (open) {
      notifPanel.hidden = !open;
      notifToggle.setAttribute('aria-expanded', String(open));
    };
    notifToggle.addEventListener('click', function () { setNotifOpen(notifPanel.hidden); });
    document.addEventListener('click', function (e) { if (!notif.contains(e.target)) setNotifOpen(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !notifPanel.hidden) { setNotifOpen(false); notifToggle.focus(); }
    });
    /* The server says when an update check is due. It runs apart so GitHub never slows the page. */
    var checkUrl = notif.getAttribute('data-check-url');
    if (checkUrl) fetch(checkUrl, { credentials: 'same-origin' }).catch(function () {});
  }

  /* ---------- Global search in the header ---------- */
  var search = $('[data-global-search]');
  if (search) {
    var searchInput = $('input', search), searchList = $('.global-search-results', search);
    var searchTimer = null, searchRun = 0, searchIndex = -1;
    var searchOptions = function () { return $$('[role=option]', searchList); };
    var closeSearch = function () {
      searchList.hidden = true;
      searchInput.setAttribute('aria-expanded', 'false');
      searchInput.removeAttribute('aria-activedescendant');
      searchIndex = -1;
    };
    var markOption = function (index) {
      var options = searchOptions();
      if (!options.length) return;
      searchIndex = (index + options.length) % options.length;
      options.forEach(function (option, i) { option.setAttribute('aria-selected', String(i === searchIndex)); });
      searchInput.setAttribute('aria-activedescendant', options[searchIndex].id);
      options[searchIndex].scrollIntoView({ block: 'nearest' });
    };
    var renderSearch = function (groups) {
      searchList.innerHTML = '';
      groups.forEach(function (group) {
        var title = document.createElement('div');
        title.className = 'global-search-group';
        title.textContent = group.label;
        searchList.appendChild(title);
        group.items.forEach(function (item) {
          var link = document.createElement('a');
          link.href = item.url;
          link.setAttribute('role', 'option');
          link.setAttribute('aria-selected', 'false');
          link.id = 'global-search-option-' + searchOptions().length;
          var label = document.createElement('span');
          label.className = 'truncate';
          label.textContent = item.label;
          link.appendChild(label);
          if (item.hint) {
            var hint = document.createElement('span');
            hint.className = 'muted small';
            hint.textContent = item.hint;
            link.appendChild(hint);
          }
          searchList.appendChild(link);
        });
      });
      if (!groups.length) {
        var empty = document.createElement('div');
        empty.className = 'global-search-empty';
        empty.textContent = i18n.noResults || 'No results.';
        searchList.appendChild(empty);
      }
      searchList.hidden = false;
      searchInput.setAttribute('aria-expanded', 'true');
      searchIndex = -1;
      /* With a query, Enter opens the best match right away. */
      if (searchInput.value.trim() && groups.length) markOption(0);
    };
    var runSearch = function () {
      var run = ++searchRun;
      fetch(search.getAttribute('data-url') + '?q=' + encodeURIComponent(searchInput.value.trim()), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        /* A slower, older answer must not replace a newer one. */
        .then(function (data) { if (run === searchRun && document.activeElement === searchInput) renderSearch(data.groups); })
        .catch(function () { if (run === searchRun) renderSearch([]); });
    };
    searchInput.addEventListener('focus', runSearch);
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(runSearch, 150);
    });
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); markOption(searchIndex + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); markOption(searchIndex - 1); }
      else if (e.key === 'Enter') {
        e.preventDefault();
        var chosen = searchOptions()[searchIndex];
        if (chosen) location.href = chosen.href;
      } else if (e.key === 'Escape') { closeSearch(); searchInput.blur(); }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('[data-global-search]')) closeSearch(); });
    document.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); searchInput.focus(); searchInput.select(); }
    });
    if (navigator.platform.indexOf('Mac') !== 0) $('kbd', search).textContent = 'Ctrl K';
  }

  /* ---------- Sheet ---------- */
  var sheet = $('#sheet'), backdrop = $('#sheet-backdrop'), body = $('#sheet-body'), lastFocus = null;

  function openSheet() {
    lastFocus = document.activeElement;
    sheet.classList.add('open');
    backdrop.classList.add('open');
    sheet.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeSheet() {
    sheet.classList.remove('open', 'sheet-wide');
    backdrop.classList.remove('open');
    sheet.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function loadSheet(url, wide) {
    if (!sheet) return;
    sheet.classList.toggle('sheet-wide', !!wide);
    body.innerHTML = '<div class="sheet-loading">' + (i18n.loading || 'Loading…') + '</div>';
    openSheet();
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
      .then(function (html) {
        body.innerHTML = html;
        initFragment(body);
        var first = $('input:not([type=hidden]),select,textarea', body);
        if (first) first.focus();
      })
      .catch(function () { body.innerHTML = '<div class="sheet-loading">' + (i18n.loadFailed || 'Could not load.') + '</div>'; });
  }

  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-sheet-url]');
    if (open) { e.preventDefault(); loadSheet(open.getAttribute('data-sheet-url'), open.hasAttribute('data-sheet-wide')); return; }
    if (e.target.closest('[data-sheet-close]') || e.target === backdrop) closeSheet();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (confirmSheet && confirmSheet.classList.contains('open')) closeConfirm();
      else if (sheet && sheet.classList.contains('open')) closeSheet();
    }
    if (e.key === 'Tab') {
      var active = confirmSheet && confirmSheet.classList.contains('open') ? confirmSheet : (sheet && sheet.classList.contains('open') ? sheet : null);
      if (!active) return;
      var items = $$('a[href],button:not([disabled]),input:not([type=hidden]):not([disabled]),select,textarea,[contenteditable=true]', active);
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* ---------- Confirmation modal ---------- */
  var confirmSheet = $('#confirm-modal'), confirmBackdrop = $('#confirm-backdrop'), pending = null, confirmReturn = null;

  function askConfirm(message, danger, onYes) {
    $('#confirm-message').textContent = message;
    var yes = $('#confirm-yes');
    yes.className = 'btn ' + (danger ? 'btn-danger' : '');
    pending = onYes;
    confirmReturn = document.activeElement;
    confirmSheet.classList.add('open');
    confirmBackdrop.classList.add('open');
    yes.focus();
  }
  function closeConfirm() {
    confirmSheet.classList.remove('open');
    confirmBackdrop.classList.remove('open');
    pending = null;
    if (confirmReturn && confirmReturn.focus) confirmReturn.focus();
    confirmReturn = null;
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('#confirm-yes')) { var run = pending; closeConfirm(); if (run) run(); return; }
    if (e.target.closest('[data-confirm-close]') || e.target === confirmBackdrop) { closeConfirm(); return; }

    /* POST from a row action, or any button with data-confirm */
    var button = e.target.closest('[data-post-url],[data-confirm]');
    if (!button || button.disabled) return;
    var message = button.getAttribute('data-confirm');
    var submit = function () {
      if (button.hasAttribute('data-post-url')) {
        var form = $('#post-form');
        form.action = button.getAttribute('data-post-url');
        $$('input:not([name=_token])', form).forEach(function (i) { i.remove(); });
        var extra = button.getAttribute('data-post-fields');
        if (extra) {
          var fields = JSON.parse(extra);
          Object.keys(fields).forEach(function (k) {
            var values = Array.isArray(fields[k]) ? fields[k] : [fields[k]];
            values.forEach(function (v) {
              var input = document.createElement('input');
              input.type = 'hidden'; input.name = k; input.value = v;
              form.appendChild(input);
            });
          });
        }
        form.submit();
      } else {
        var target = button.form;
        if (!target) return;
        if (button.name) {
          var hidden = document.createElement('input');
          hidden.type = 'hidden'; hidden.name = button.name; hidden.value = button.value;
          target.appendChild(hidden);
        }
        target.submit();
      }
    };
    e.preventDefault();
    if (message) askConfirm(message, button.classList.contains('btn-danger') || button.hasAttribute('data-danger'), submit);
    else submit();
  });

  /* ---------- Data tables ---------- */
  function syncTable(table) {
    var boxes = $$('[data-row-check]', table);
    var checked = boxes.filter(function (b) { return b.checked; });
    var all = $('[data-check-all]', table);
    if (all) {
      all.checked = boxes.length > 0 && checked.length === boxes.length;
      all.indeterminate = checked.length > 0 && checked.length < boxes.length;
    }
    boxes.forEach(function (b) { (b.closest('tr, .dt-tile') || b).classList.toggle('selected', b.checked); });
    var bar = $('[data-bulk-bar]', table);
    if (bar) {
      bar.hidden = checked.length === 0;
      $('[data-bulk-count]', bar).textContent = checked.length;
    }
  }
  document.addEventListener('change', function (e) {
    var table = e.target.closest('[data-table]');
    if (table) {
      if (e.target.matches('[data-check-all]')) {
        $$('[data-row-check]', table).forEach(function (b) { b.checked = e.target.checked; });
      }
      if (e.target.matches('[data-check-all],[data-row-check]')) syncTable(table);
      if (e.target.matches('[data-auto-submit]')) e.target.form.submit();
    }
  });
  $$('[data-table]').forEach(syncTable);

  /* ---------- Checkbox panels (form fields): count, "Select all" per group, filter ---------- */
  function syncChecks(panel) {
    var boxes = $$('.check-option .checkbox', panel);
    var count = $('[data-check-count]', panel);
    if (count) count.textContent = count.dataset.text.replace(':count', boxes.filter(function (b) { return b.checked; }).length).replace(':total', boxes.length);
    $$('[data-check-group]', panel).forEach(function (group) {
      var button = $('[data-check-group-all]', group);
      if (!button) return;
      var all = $$('.check-option .checkbox', group);
      button.textContent = all.length && all.every(function (b) { return b.checked; }) ? button.dataset.clear : button.dataset.select;
    });
  }
  document.addEventListener('change', function (e) {
    var panel = e.target.closest('[data-check-panel]');
    if (panel) syncChecks(panel);
  });
  document.addEventListener('click', function (e) {
    var button = e.target.closest('[data-check-group-all]');
    if (!button) return;
    var group = button.closest('[data-check-group]');
    // Only the options the filter shows: "Select all" after filtering picks the matches.
    var shown = $$('.check-option', group).filter(function (o) { return !o.hidden; }).map(function (o) { return $('.checkbox', o); });
    var on = !shown.every(function (b) { return b.checked; });
    shown.forEach(function (b) { b.checked = on; });
    var panel = button.closest('[data-check-panel]');
    syncChecks(panel);
    var form = panel.closest('form');
    if (form) form.dispatchEvent(new Event('change', { bubbles: true }));
  });
  document.addEventListener('input', function (e) {
    if (!e.target.matches('[data-check-filter]')) return;
    var panel = e.target.closest('[data-check-panel]');
    var term = e.target.value.trim().toLowerCase();
    var any = false;
    $$('[data-check-group]', panel).forEach(function (group) {
      var visible = 0;
      $$('.check-option', group).forEach(function (o) {
        o.hidden = term !== '' && o.dataset.checkText.indexOf(term) < 0;
        if (!o.hidden) visible++;
      });
      group.hidden = visible === 0;
      if (visible) any = true;
    });
    var none = $('[data-check-none]', panel);
    if (none) none.hidden = any;
  });
  // A filter is for finding, never for submitting the form.
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches && e.target.matches('[data-check-filter]')) e.preventDefault();
  });

  /* ---------- Media picker ---------- */
  var pickerCallback = null;
  document.addEventListener('click', function (e) {
    var pick = e.target.closest('[data-media-pick]');
    if (pick) {
      e.preventDefault();
      var field = pick.closest('[data-media-field]');
      pickerCallback = function (item) {
        $('input', field).value = item.path;
        var preview = $('.media-preview', field);
        if (preview) { preview.src = item.url; preview.hidden = false; }
        $('input', field).dispatchEvent(new Event('input', { bubbles: true }));
      };
      openPicker($('input', field).value);
      return;
    }
    var clear = e.target.closest('[data-media-clear]');
    if (clear) {
      var holder = clear.closest('[data-media-field]');
      $('input', holder).value = '';
      var img = $('.media-preview', holder);
      if (img) img.hidden = true;
      return;
    }
    var chosen = e.target.closest('[data-media-choose]');
    if (chosen && pickerCallback) {
      pickerCallback({ path: chosen.getAttribute('data-path'), url: chosen.getAttribute('data-url'), alt: chosen.getAttribute('data-alt') || '' });
      pickerCallback = null;
      restoreOrClose();
    }
  });
  var savedSheet = null;
  function openPicker(current) {
    /* A settings or term form may already be open in the sheet: keep it and bring it back afterwards. */
    savedSheet = sheet.classList.contains('open') ? Array.prototype.slice.call(body.childNodes) : null;
    if (savedSheet) savedSheet = { nodes: savedSheet };
    var url = window.CMS_MEDIA_PICKER + (current ? (window.CMS_MEDIA_PICKER.indexOf('?') < 0 ? '?' : '&') + 'current=' + encodeURIComponent(current) : '');
    var keep = savedSheet;
    if (keep) { var holder = document.createDocumentFragment(); keep.nodes.forEach(function (n) { holder.appendChild(n); }); keep.holder = holder; }
    loadSheet(url, true);
  }
  function restoreOrClose() {
    if (savedSheet && savedSheet.holder) {
      body.innerHTML = '';
      body.appendChild(savedSheet.holder);
      sheet.classList.remove('sheet-wide');
      savedSheet = null;
    } else {
      closeSheet();
    }
  }
  /*
   * Uploads, in the Upload sheet and the image picker: files go up one at a time, each with a
   * progress bar. A file of the wrong type or too large is refused before it is sent, and a file
   * the server refuses shows the reason, without stopping the others.
   */
  function fileSize(bytes) {
    return bytes >= 1048576 ? (bytes / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }
  function uploadRow(list, name) {
    var row = document.createElement('div');
    row.className = 'upload-row';
    row.innerHTML = '<span class="upload-name"></span><span class="upload-state"></span><span class="upload-bar"><span></span></span><span class="upload-error"></span>';
    $('.upload-name', row).textContent = name;
    list.appendChild(row);
    return row;
  }
  function rowState(row, state, text) {
    row.dataset.state = state;
    $('.upload-state', row).textContent = state === 'done' ? (i18n.uploaded || 'Uploaded') : state === 'error' ? (i18n.failed || 'Failed') : (text || '');
    if (state === 'error') $('.upload-error', row).textContent = text || i18n.uploadFailed || 'Upload failed.';
  }
  function uploadOne(box, file, row) {
    return new Promise(function (resolve) {
      var data = new FormData();
      data.append('files[]', file);
      data.append('_token', $('input[name="_token"]', box).value);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', box.dataset.uploadUrl);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.onprogress = function (e) {
        if (!e.lengthComputable) return;
        var pct = Math.round(e.loaded / e.total * 100);
        $('.upload-bar span', row).style.width = pct + '%';
        rowState(row, 'busy', pct < 100 ? pct + '%' : (i18n.processing || 'Processing…'));
      };
      xhr.onload = function () {
        var res = {};
        try { res = JSON.parse(xhr.responseText); } catch (err) { /* an HTML error page */ }
        if (xhr.status === 200 && res.saved && res.saved.length) { rowState(row, 'done'); resolve(res.saved[0]); return; }
        var message = (res.errors && res.errors[0] && res.errors[0].message) || res.message;
        if (xhr.status === 413) message = message || i18n.tooLargeHost;
        else if (xhr.status === 419) message = i18n.sessionExpired;
        else if (xhr.status === 403) message = i18n.noPermission;
        else if (!message || xhr.status >= 500) message = i18n.serverError;
        rowState(row, 'error', message);
        resolve(null);
      };
      xhr.onerror = function () { rowState(row, 'error', i18n.network); resolve(null); };
      rowState(row, 'busy', '0%');
      xhr.send(data);
    });
  }
  function uploadFiles(box, files, onSaved, onDone) {
    var list = $('[data-upload-list]', box);
    var allowed = (box.dataset.allowed || '').split(',').filter(Boolean);
    var max = Number(box.dataset.max) || 0;
    var queue = [], failed = 0, saved = 0;
    Array.prototype.forEach.call(files, function (file) {
      var row = uploadRow(list, file.name);
      var ext = (file.name.split('.').pop() || '').toLowerCase();
      if (allowed.length && allowed.indexOf(ext) < 0) { rowState(row, 'error', (i18n.typeNotAllowed || '').replace(':type', ext).replace(':types', allowed.join(', '))); failed++; return; }
      if (max && file.size > max) { rowState(row, 'error', (i18n.tooLarge || '').replace(':size', fileSize(file.size)).replace(':max', box.dataset.maxLabel)); failed++; return; }
      queue.push({ file: file, row: row });
    });
    (function next() {
      var item = queue.shift();
      if (!item) { if (onDone) onDone(saved, failed); return; }
      uploadOne(box, item.file, item.row).then(function (media) {
        if (media) { saved++; if (onSaved) onSaved(media); } else failed++;
        next();
      });
    })();
  }

  /* Choosing or dropping files. In the picker one good image is used right away; several are added to the grid. */
  function startUpload(box, files) {
    if (!files || !files.length) return;
    var picker = box.matches('[data-picker]');
    var single = files.length === 1;
    uploadFiles(box, files, function (media) {
      if (!picker) return;
      var grid = $('[data-picker-grid]', box);
      var tile = document.createElement('button');
      tile.type = 'button';
      tile.className = 'picker-item is-new';
      tile.setAttribute('data-media-choose', '');
      tile.dataset.path = media.path; tile.dataset.url = media.url; tile.dataset.alt = media.alt || ''; tile.dataset.title = (media.title || '').toLowerCase();
      tile.innerHTML = '<span class="picker-thumb"><img alt="" decoding="async"></span><span class="picker-name"></span><span class="picker-meta"></span>';
      $('img', tile).src = media.url;
      $('.picker-name', tile).textContent = media.title || media.path.split('/').pop();
      $('.picker-meta', tile).textContent = media.width && media.height ? media.width + '×' + media.height : '';
      grid.insertBefore(tile, grid.firstChild);
      var empty = $('[data-picker-empty]', box); if (empty) empty.hidden = true;
      var filter = $('[data-picker-filter]', box); if (filter) filter.hidden = false;
      if (single && pickerCallback) { tile.click(); }
    }, function (saved, failed) {
      if (picker) return;
      // The Upload sheet: all good reloads the list; with errors, they stay readable until Done.
      if (!failed) { location.reload(); return; }
      var done = $('[data-upload-done]', box.parentNode);
      if (done) done.hidden = saved === 0;
    });
  }
  document.addEventListener('change', function (e) {
    if (!e.target.matches('[data-upload-input]')) return;
    startUpload(e.target.closest('[data-uploader]'), e.target.files);
    e.target.value = '';
  });
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-upload-done]')) location.reload();
  });
  ['dragenter', 'dragover'].forEach(function (type) {
    document.addEventListener(type, function (e) {
      var box = e.target.closest && e.target.closest('[data-uploader]');
      if (!box || !e.dataTransfer || Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') < 0) return;
      e.preventDefault();
      box.classList.add('is-dragging');
    });
  });
  document.addEventListener('dragleave', function (e) {
    var box = e.target.closest && e.target.closest('[data-uploader]');
    if (box && !box.contains(e.relatedTarget)) box.classList.remove('is-dragging');
  });
  document.addEventListener('drop', function (e) {
    var box = e.target.closest && e.target.closest('[data-uploader]');
    if (!box || !e.dataTransfer || !e.dataTransfer.files.length) return;
    e.preventDefault();
    box.classList.remove('is-dragging');
    startUpload(box, e.dataTransfer.files);
  });

  /* Searching the picker's images by title, file name and alt text. */
  document.addEventListener('input', function (e) {
    if (!e.target.matches('[data-picker-filter]')) return;
    var box = e.target.closest('[data-picker]');
    var term = e.target.value.trim().toLowerCase(), shown = 0;
    $$('.picker-item', box).forEach(function (tile) {
      tile.hidden = term !== '' && (tile.dataset.title || '').indexOf(term) < 0;
      if (!tile.hidden) shown++;
    });
    $('[data-picker-none]', box).hidden = shown > 0 || !$$('.picker-item', box).length;
  });

  /* ---------- Fragment setup ---------- */
  /* Color fields: the picker and the hex code follow each other; "Default" empties the setting. */
  document.addEventListener('input', function (e) {
    var box = e.target.closest('[data-color-field]');
    if (!box) return;
    var text = $('[data-color-text]', box), picker = $('[data-color-picker]', box);
    if (e.target === picker) { text.value = picker.value; picker.removeAttribute('data-empty'); }
    else if (/^#[0-9a-f]{6}$/i.test(text.value)) { picker.value = text.value.toLowerCase(); picker.removeAttribute('data-empty'); }
    $('[data-color-clear]', box).hidden = text.value === '';
  });
  document.addEventListener('click', function (e) {
    var clear = e.target.closest('[data-color-clear]');
    if (!clear) return;
    var box = clear.closest('[data-color-field]');
    $('[data-color-text]', box).value = '';
    $('[data-color-picker]', box).setAttribute('data-empty', '');
    clear.hidden = true;
  });

  function initFragment(root) {
    initSlug(root);
    initStarters(root);
  }

  /* Starter thumbnails draw a 1280px page; scale it to the card's width, whatever the sheet's size. */
  var starterFit = window.ResizeObserver && new ResizeObserver(function (entries) {
    entries.forEach(function (entry) {
      var width = entry.contentRect.width;
      if (width) entry.target.style.setProperty('--starter-scale', String(width / 1280));
    });
  });
  function initStarters(root) {
    if (starterFit) $$('.starter-thumb', root).forEach(function (box) { starterFit.observe(box); });
  }

  function slugify(text) {
    return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }
  function initSlug(root) {
    $$('[data-slug-from]', root).forEach(function (slug) {
      var source = $('[name="' + slug.getAttribute('data-slug-from') + '"]', slug.form);
      if (!source) return;
      var touched = slug.value !== '';
      slug.addEventListener('input', function () { touched = true; });
      source.addEventListener('input', function () {
        if (!touched) { slug.value = slugify(source.value); slug.dispatchEvent(new Event('input-auto', { bubbles: true })); }
      });
    });
  }
  initFragment(document);

  /* ---------- Rich text editor ---------- */
  var editor = $('[data-editor]');
  if (editor) {
    var area = $('.editor-area', editor), source = $('.editor-source', editor), hidden = $('[data-editor-value]', editor);
    var sourceMode = false, savedRange = null;
    var sync = function () { hidden.value = sourceMode ? source.value : area.innerHTML; hidden.dispatchEvent(new Event('input', { bubbles: true })); };
    var saveRange = function () { var s = window.getSelection(); if (s.rangeCount && area.contains(s.anchorNode)) savedRange = s.getRangeAt(0).cloneRange(); };
    var restoreRange = function () { area.focus(); if (savedRange) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(savedRange); } };
    document.execCommand('defaultParagraphSeparator', false, 'p');
    area.addEventListener('input', sync);
    area.addEventListener('keyup', saveRange);
    area.addEventListener('mouseup', saveRange);
    source.addEventListener('input', sync);
    /* Paste as plain text so foreign styles never enter the content. */
    area.addEventListener('paste', function (e) {
      e.preventDefault();
      document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain'));
    });

    $('.editor-toolbar', editor).addEventListener('mousedown', function (e) {
      if (e.target.closest('[data-cmd]')) e.preventDefault();
    });
    $('.editor-toolbar', editor).addEventListener('click', function (e) {
      var button = e.target.closest('[data-cmd]');
      if (!button) return;
      e.preventDefault();
      var cmd = button.getAttribute('data-cmd'), value = button.getAttribute('data-value');
      if (cmd === 'source') {
        sourceMode = !sourceMode;
        if (sourceMode) source.value = area.innerHTML; else area.innerHTML = source.value;
        area.hidden = sourceMode; source.hidden = !sourceMode;
        sync();
        return;
      }
      if (sourceMode) return;
      if (cmd === 'link') {
        saveRange();
        var row = $('[data-link-row]', editor);
        row.hidden = !row.hidden;
        if (!row.hidden) $('input', row).focus();
        return;
      }
      if (cmd === 'image') {
        saveRange();
        pickerCallback = function (item) {
          restoreRange();
          /* Stored without the domain so content keeps working after moving to another host. */
          var src = new URL(item.url, location.href).pathname;
          var html = '<img src="' + src.replace(/"/g, '&quot;') + '" alt="' + item.alt.replace(/"/g, '&quot;') + '" loading="lazy">';
          document.execCommand('insertHTML', false, html);
          sync();
        };
        savedSheet = null;
        loadSheet(window.CMS_MEDIA_PICKER, true);
        return;
      }
      document.execCommand(cmd, false, value);
      sync();
    });
    var linkRow = $('[data-link-row]', editor);
    var applyLink = function () {
      var url = $('input', linkRow).value.trim();
      linkRow.hidden = true;
      restoreRange();
      if (url) document.execCommand('createLink', false, url);
      $('input', linkRow).value = '';
      sync();
    };
    $('button', linkRow).addEventListener('click', applyLink);
    $('input', linkRow).addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); applyLink(); } });
  }

  /* ---------- SEO panel ---------- */
  var seo = $('[data-seo]');
  if (seo) {
    var form = seo.closest('form');
    var get = function (n) { var el = form.elements[n]; return el ? el.value : ''; };
    var labels = window.CMS_SEO || {};
    var refresh = function () {
      var title = get('meta_title') || (get('title') + ' — ' + seo.getAttribute('data-site'));
      var content = get('content');
      var text = content.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      var desc = get('meta_description') || get('excerpt') || text.slice(0, 160);
      var keyword = get('focus_keyword').trim().toLowerCase();
      $('[data-serp-title]', seo).textContent = title;
      $('[data-serp-desc]', seo).textContent = desc;
      $('[data-serp-url]', seo).textContent = seo.getAttribute('data-base') + get('slug');
      $$('[data-count]', form).forEach(function (el) {
        var name = el.getAttribute('data-count'), max = +el.getAttribute('data-max'), n = get(name).length;
        el.textContent = n + ' / ' + max;
        el.classList.toggle('over', n > max);
      });
      var words = text ? text.split(' ').length : 0;
      var checks = {
        title: title.length >= 30 && title.length <= 60,
        description: get('meta_description').length >= 70 && get('meta_description').length <= 160,
        keyword_title: !!keyword && title.toLowerCase().indexOf(keyword) !== -1,
        keyword_content: !!keyword && text.toLowerCase().indexOf(keyword) !== -1,
        keyword_slug: !!keyword && get('slug').indexOf(slugify(keyword)) !== -1,
        length: words >= 300,
        heading: /<h2/i.test(content),
        image: /<img/i.test(content) || !!get('featured_image'),
        image_alt: (content.match(/<img\b[^>]*>/gi) || []).every(function (tag) { return /\balt="[^"]+"/i.test(tag); })
      };
      var list = $('[data-seo-checks]', seo);
      list.innerHTML = '';
      Object.keys(labels).forEach(function (k) {
        if (!(k in checks)) return;
        var row = document.createElement('div');
        row.className = 'seo-check' + (checks[k] ? ' ok' : '');
        var dot = document.createElement('span'); dot.className = 'seo-dot';
        var label = document.createElement('span'); label.textContent = labels[k];
        row.appendChild(dot); row.appendChild(label);
        list.appendChild(row);
      });
    };
    form.addEventListener('input', refresh);
    form.addEventListener('input-auto', refresh);
    refresh();
  }

  /* ---------- Toasts ---------- */
  $$('.toast').forEach(function (t) { setTimeout(function () { t.remove(); }, 5000); });
})();
