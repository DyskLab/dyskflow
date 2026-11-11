/* Design > Files editor: CodeMirror 5 from public/vendor, saved with fetch. No build step. */
(function () {
  'use strict';
  var root = document.querySelector('[data-code-editor]');
  if (!root || !window.CodeMirror) return;

  var $ = function (s) { return root.querySelector(s); };
  var t = JSON.parse(root.getAttribute('data-i18n') || '{}');
  var path = root.getAttribute('data-path');
  var hash = root.getAttribute('data-hash');
  var language = root.getAttribute('data-language');
  var draftKey = 'cms-draft:' + path;
  var tokenInput = document.querySelector('#post-form input[name=_token]');
  var mac = navigator.platform.indexOf('Mac') === 0;
  $('[data-code-kbd]').textContent = mac ? '⌘ S' : 'Ctrl S';

  /* Blade on top of PHP + HTML: echoes, comments and @directives. */
  CodeMirror.defineMode('blade', function (config) {
    return CodeMirror.overlayMode(CodeMirror.getMode(config, 'application/x-httpd-php'), {
      startState: function () { return { comment: false }; },
      token: function (stream, state) {
        if (state.comment) {
          if (stream.skipTo('--}}')) { stream.match('--}}'); state.comment = false; } else stream.skipToEnd();
          return 'comment';
        }
        if (stream.match('{{--')) { state.comment = true; return 'comment'; }
        if (stream.match(/^(\{\{|\{!!|\}\}|!!\})/)) return 'blade-echo';
        /* A directive follows anything but a word character, so name@example.com stays plain. */
        if (!/[\w.@]/.test(stream.string.charAt(stream.pos - 1) || ' ') && stream.match(/^@[A-Za-z_]+/)) return 'blade-directive';
        stream.next();
        while (!stream.eol() && !stream.match(/^(\{\{|\{!!|\}\}|!!\}|@[A-Za-z_])/, false)) stream.next();
        return null;
      }
    });
  });

  var modes = {
    blade: 'blade', php: 'application/x-httpd-php', css: 'css', js: 'javascript',
    json: { name: 'javascript', json: true }, xml: 'xml', markdown: null, text: null
  };
  var wide = language === 'blade' || language === 'php';

  var cm = CodeMirror.fromTextArea($('textarea'), {
    mode: modes[language] || null,
    lineNumbers: true,
    indentUnit: wide ? 4 : 2,
    tabSize: wide ? 4 : 2,
    indentWithTabs: false,
    matchBrackets: true,
    autoCloseBrackets: true,
    styleActiveLine: true,
    lineWrapping: false,
    viewportMargin: 50,
    extraKeys: {
      'Ctrl-S': function () { save(false); },
      'Cmd-S': function () { save(false); },
      'Ctrl-G': gotoLine,
      'Cmd-G': gotoLine,
      Tab: function (editor) {
        if (editor.somethingSelected()) editor.execCommand('indentMore');
        else editor.replaceSelection(Array(editor.getOption('indentUnit') + 1).join(' '), 'end');
      },
      'Shift-Tab': 'indentLess'
    }
  });

  var clean = cm.changeGeneration();
  var saving = false;
  var state = $('[data-code-state]');
  var message = $('[data-code-message]');

  function setState(text) { state.textContent = text || ''; }
  function isDirty() { return !cm.isClean(clean); }
  function hideMessage() { message.hidden = true; $('[data-code-conflict]').hidden = true; $('[data-code-confirm]').hidden = true; }
  function showMessage(text, kind) {
    $('[data-code-message-text]').textContent = text;
    message.className = 'alert code-message ' + (kind === 'warning' ? 'alert-warning' : 'alert-danger');
    message.hidden = false;
  }

  /* Changes kept across the password confirmation, which leaves the page. */
  try {
    var draft = JSON.parse(sessionStorage.getItem(draftKey) || 'null');
    sessionStorage.removeItem(draftKey);
    if (draft && draft.hash === hash && typeof draft.content === 'string' && draft.content !== cm.getValue()) {
      cm.setValue(draft.content);
      setState(t.restored);
    }
  } catch (err) {}

  cm.on('change', function () { if (isDirty()) setState(t.unsaved); });
  cm.on('cursorActivity', function () {
    var c = cm.getCursor();
    $('[data-code-cursor]').textContent = (t.line || 'Line') + ' ' + (c.line + 1) + ', ' + (t.column || 'Col') + ' ' + (c.ch + 1);
  });
  window.addEventListener('beforeunload', function (e) {
    if (isDirty()) { e.preventDefault(); e.returnValue = ''; }
  });

  function gotoLine(editor) {
    editor.openDialog('<span>' + (t.goto || 'Go to line:') + '</span> <input type="text" inputmode="numeric" style="width:6em">', function (value) {
      var line = parseInt(value, 10);
      if (!isNaN(line)) { editor.setCursor(Math.max(0, line - 1), 0); editor.scrollIntoView(null, 120); editor.focus(); }
    });
  }

  function save(force) {
    if (saving) return;
    saving = true;
    hideMessage();
    setState(t.saving);
    var generation = cm.changeGeneration();
    var content = cm.getValue();
    var button = $('[data-code-save]');
    button.disabled = true;

    fetch(root.getAttribute('data-save-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tokenInput ? tokenInput.value : '' },
      body: JSON.stringify({ path: path, content: content, hash: hash, force: !!force })
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) { return { status: response.status, data: data }; });
    }).then(function (result) {
      var data = result.data || {};
      if (result.status === 200) {
        hash = data.hash;
        clean = generation;
        setState(isDirty() ? t.unsaved : (data.message || t.saved));
        if (data.size) $('[data-code-size]').textContent = data.size;
      } else if (result.status === 409) {
        setState(t.unsaved);
        showMessage(data.message, 'warning');
        $('[data-code-conflict]').hidden = false;
      } else if (result.status === 423 && data.confirm) {
        setState(t.unsaved);
        try { sessionStorage.setItem(draftKey, JSON.stringify({ hash: hash, content: content })); } catch (err) {}
        showMessage(data.message, 'warning');
        var link = $('[data-code-confirm]');
        link.href = data.confirm;
        link.hidden = false;
      } else if (result.status === 419) {
        setState(t.unsaved);
        showMessage(t.expired);
      } else {
        setState(t.unsaved);
        var first = data.errors ? data.errors[Object.keys(data.errors)[0]][0] : null;
        showMessage(data.message && !first ? data.message : (first || data.message || t.failed));
      }
    }).catch(function () {
      setState(t.unsaved);
      showMessage(t.failed);
    }).then(function () {
      saving = false;
      button.disabled = false;
    });
  }

  $('[data-code-save]').addEventListener('click', function () { save(false); });
  $('[data-code-overwrite]').addEventListener('click', function () { save(true); });
  $('[data-code-reload]').addEventListener('click', function () { clean = cm.changeGeneration(); cm.markClean(); location.reload(); });
  $('[data-code-confirm]').addEventListener('click', function () { clean = cm.changeGeneration(); cm.markClean(); });
  cm.focus();
})();
