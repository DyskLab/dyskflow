/* Builder pages: a video or map loads only when the visitor presses its button, so no third-party request happens before. */
document.addEventListener('click', function (e) {
  var button = e.target.closest('.b-video-play, .b-map-load');
  if (!button) return;
  var box = button.closest('[data-b-video]');
  var src = box && box.getAttribute('data-b-video');
  if (!src || !/^https:\/\/(www\.youtube-nocookie\.com\/|player\.vimeo\.com\/|www\.google\.com\/maps\?)/.test(src)) return;
  var frame = document.createElement('iframe');
  frame.src = src;
  frame.title = box.getAttribute('data-b-title') || '';
  if (!box.classList.contains('b-map')) frame.allow = 'autoplay; fullscreen; picture-in-picture';
  frame.allowFullscreen = true;
  box.replaceChildren(frame);
  frame.focus();
});

/*
 * Interactive widgets. Each part looks for its own markup, so a page without it pays nothing.
 * Everything works without this file: tabs show the first tab, carousels scroll, forms post.
 */
(function () {
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var all = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var seen = function (el, fn) {
    if (!('IntersectionObserver' in window)) { fn(); return; }
    var io = new IntersectionObserver(function (entries) {
      if (entries.some(function (e) { return e.isIntersecting; })) { io.disconnect(); fn(); }
    }, { threshold: 0.3 });
    io.observe(el);
  };

  /* Tabs, with arrow keys between them. */
  all('[data-b-tabs]').forEach(function (box) {
    var tabs = all('[role="tab"]', box);
    function show(tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) tab.focus();
    }
    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { show(tab); });
      tab.addEventListener('keydown', function (e) {
        var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
        if (e.key === 'Home') step = -i; else if (e.key === 'End') step = tabs.length - 1 - i;
        if (step === undefined) return;
        e.preventDefault();
        show(tabs[(i + step + tabs.length) % tabs.length], true);
      });
    });
  });

  /* Counters count up once they are seen. */
  all('[data-b-counter]').forEach(function (box) {
    var seconds = Number(box.getAttribute('data-b-counter')) || 0;
    var nums = all('[data-b-count]', box);
    if (reduced || !seconds) return;
    nums.forEach(function (n) { n.textContent = '0'; });
    seen(box, function () {
      var start = performance.now();
      (function tick(now) {
        var p = Math.min(1, (now - start) / (seconds * 1000));
        var eased = 1 - Math.pow(1 - p, 3);
        nums.forEach(function (n) { n.textContent = Math.round(Number(n.getAttribute('data-b-count')) * eased).toLocaleString('id-ID'); });
        if (p < 1) requestAnimationFrame(tick);
      })(start);
    });
  });

  /* Progress bars fill once they are seen. */
  all('[data-b-progress]').forEach(function (box) {
    if (reduced) return;
    box.classList.add('is-waiting');
    seen(box, function () { box.classList.add('is-shown'); requestAnimationFrame(function () { box.classList.remove('is-waiting'); }); });
  });

  /* Countdown to a Unix time. */
  all('[data-b-countdown]').forEach(function (box) {
    var end = Number(box.getAttribute('data-b-countdown')) * 1000;
    var units = box.querySelector('.b-countdown-units');
    var ended = box.querySelector('.b-countdown-ended');
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
      var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
      var values = { days: Math.floor(left / 86400), hours: Math.floor(left % 86400 / 3600), minutes: Math.floor(left % 3600 / 60), seconds: left % 60 };
      all('[data-b-unit]', box).forEach(function (el) { el.textContent = pad(values[el.getAttribute('data-b-unit')]); });
      if (left === 0) {
        if (ended) { ended.hidden = false; units.hidden = true; }
        return;
      }
      setTimeout(tick, 1000 - Date.now() % 1000);
    }
    tick();
  });

  /* Animated headline: the next word every few seconds. */
  all('[data-b-rotate]').forEach(function (head) {
    var words = all('.b-animhead-word', head);
    if (reduced || words.length < 2) return;
    var i = 0;
    var typing = head.getAttribute('data-b-rotate') === 'typing';
    var box = head.querySelector('.b-animhead-words');
    // The words share one grid cell: size it to the word on show, so a short word leaves no gap.
    function fit() { if (!typing && box) box.style.width = Math.ceil(words[i].scrollWidth) + 'px'; }
    fit();
    window.addEventListener('resize', fit);
    setInterval(function () {
      if (document.hidden) return;
      words[i].classList.remove('is-on');
      i = (i + 1) % words.length;
      words[i].classList.add('is-on');
      fit();
    }, typing ? 3200 : 2500);
  });

  /* Carousels: arrows, dots and autoplay on top of a scroll-snapping row. */
  all('[data-b-carousel]').forEach(function (box) {
    var track = box.querySelector('.b-carousel-track');
    var slides = all('.b-carousel-slide', track);
    var prev = box.querySelector('[data-b-prev]');
    var next = box.querySelector('[data-b-next]');
    var dots = box.querySelector('[data-b-dots]');
    var autoplay = Number(box.getAttribute('data-autoplay')) || 0;
    if (!track || !slides.length) return;
    function width() { return slides[0].getBoundingClientRect().width + (parseFloat(getComputedStyle(track).columnGap) || 0); }
    function perView() { return Math.max(1, Math.round(track.clientWidth / width())); }
    function pages() { return Math.max(1, slides.length - perView() + 1); }
    function index() { return Math.round(track.scrollLeft / width()); }
    function go(i) {
      var n = pages();
      i = (i % n + n) % n;
      track.scrollTo({ left: i * width(), behavior: reduced ? 'auto' : 'smooth' });
    }
    function update() {
      var i = index(), n = pages();
      if (prev) prev.disabled = !autoplay && i <= 0;
      if (next) next.disabled = !autoplay && i >= n - 1;
      if (dots) {
        if (dots.children.length !== n) {
          dots.innerHTML = '';
          for (var d = 0; d < n; d++) {
            var b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('aria-label', (d + 1) + ' / ' + n);
            b.addEventListener('click', go.bind(null, d));
            dots.appendChild(b);
          }
          dots.hidden = n < 2;
        }
        all('button', dots).forEach(function (b, d) { b.setAttribute('aria-current', String(d === i)); });
      }
    }
    if (prev) prev.addEventListener('click', function () { go(index() - 1); });
    if (next) next.addEventListener('click', function () { go(index() + 1); });
    track.addEventListener('scroll', function () { clearTimeout(track._t); track._t = setTimeout(update, 60); }, { passive: true });
    track.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); go(index() + 1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(index() - 1); }
    });
    window.addEventListener('resize', update);
    update();
    if (autoplay && !reduced && pages() > 1) {
      var paused = false;
      ['mouseenter', 'focusin', 'touchstart'].forEach(function (ev) { box.addEventListener(ev, function () { paused = true; }, { passive: true }); });
      ['mouseleave', 'focusout'].forEach(function (ev) { box.addEventListener(ev, function () { paused = false; }); });
      setInterval(function () { if (!paused && !document.hidden) go(index() + 1); }, autoplay * 1000);
    }
  });

  /* Table of contents from the headings on the page. */
  all('[data-b-toc]').forEach(function (nav) {
    var levels = { h2: 'h2', 'h2-h3': 'h2,h3', 'h2-h4': 'h2,h3,h4' }[nav.getAttribute('data-b-toc')] || 'h2,h3';
    var list = nav.querySelector('[data-b-toc-list]');
    var scope = document.querySelector('main') || document.body;
    var heads = all(levels, scope).filter(function (h) { return !nav.contains(h) && h.textContent.trim() && !h.closest('.b-toc, .b-tabs-panel[hidden], .b-flip-back'); });
    if (!heads.length) { nav.querySelector('.b-toc-empty').hidden = false; return; }
    var used = {};
    heads.forEach(function (h) {
      if (!h.id) {
        var base = h.textContent.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section';
        var id = base, n = 2;
        while (document.getElementById(id) || used[id]) id = base + '-' + n++;
        used[id] = true;
        h.id = id;
      }
      var li = document.createElement('li');
      if (h.tagName !== 'H2') li.className = 'b-toc-sub';
      var a = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = h.textContent.trim();
      li.appendChild(a);
      list.appendChild(li);
    });
  });

  /* Reading progress bar. */
  var bars = all('[data-b-readbar]');
  if (bars.length && !document.body.classList.contains('b-preview')) {
    var read = function () {
      var max = document.documentElement.scrollHeight - innerHeight;
      var p = max > 0 ? Math.min(1, scrollY / max) : 0;
      bars.forEach(function (b) { b.style.setProperty('--b-read', p); });
    };
    addEventListener('scroll', read, { passive: true });
    read();
  }

  /* Portfolio filter. */
  all('[data-b-portfolio]').forEach(function (box) {
    box.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-b-filter]');
      if (!btn) return;
      var cat = btn.getAttribute('data-b-filter');
      all('[data-b-filter]', box).forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
      all('[data-b-cats]', box).forEach(function (item) { item.hidden = cat !== '' && (' ' + item.getAttribute('data-b-cats') + ' ').indexOf(' ' + cat + ' ') < 0; });
    });
  });

  /* Clicks: alerts, flip boxes on touch, hotspots, copy buttons, the playlist. */
  document.addEventListener('click', function (e) {
    var el;
    if ((el = e.target.closest('[data-b-alert-close]'))) { el.closest('[data-b-alert]').remove(); return; }
    if ((el = e.target.closest('[data-b-hotspot]'))) {
      var point = el.parentNode, open = !point.classList.contains('is-open');
      all('.b-hotspot-point.is-open').forEach(function (p) { p.classList.remove('is-open'); p.firstElementChild.setAttribute('aria-expanded', 'false'); });
      point.classList.toggle('is-open', open);
      el.setAttribute('aria-expanded', String(open));
      return;
    }
    if ((el = e.target.closest('[data-b-flip]')) && !e.target.closest('a')) { el.classList.toggle('is-flipped'); }
    if ((el = e.target.closest('[data-b-copy-code]'))) {
      copy(el.closest('.b-code').querySelector('code').innerText.replace(/\n$/, ''), el);
      return;
    }
    if ((el = e.target.closest('[data-b-copy-link]'))) { copy(location.href.split('#')[0], el); return; }
    if ((el = e.target.closest('[data-b-play]'))) {
      var list = el.closest('[data-b-playlist]');
      var src = el.getAttribute('data-b-play');
      if (!/^https:\/\/(www\.youtube-nocookie\.com\/|player\.vimeo\.com\/)/.test(src)) return;
      all('[data-b-play]', list).forEach(function (b) { b.setAttribute('aria-current', String(b === el)); });
      var box = list.querySelector('[data-b-video]');
      var frame = document.createElement('iframe');
      frame.src = src;
      frame.title = el.getAttribute('data-title') || '';
      frame.allow = 'autoplay; fullscreen; picture-in-picture';
      frame.allowFullscreen = true;
      box.setAttribute('data-b-video', src);
      box.replaceChildren(frame);
    }
  });
  function copy(text, button) {
    var done = function () {
      var label = button.innerHTML;
      button.textContent = button.getAttribute('data-copied') || '✓';
      setTimeout(function () { button.innerHTML = label; }, 1600);
    };
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () {});
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') all('.b-hotspot-point.is-open').forEach(function (p) { p.classList.remove('is-open'); });
  });

  /* Forms: send without leaving the page and show the answer, or the errors under each field. */
  all('[data-b-form]').forEach(function (form) {
    var status = form.querySelector('.b-form-status');
    var button = form.querySelector('.b-form-submit');
    form.addEventListener('submit', function (e) {
      if (!window.fetch || !window.FormData) return;
      e.preventDefault();
      all('.b-form-field', form).forEach(function (f) { f.classList.remove('has-error'); var p = f.querySelector('.b-form-error'); p.hidden = true; p.textContent = ''; });
      status.hidden = true;
      status.classList.remove('is-error');
      button.setAttribute('aria-busy', 'true');
      var label = button.textContent;
      button.textContent = form.getAttribute('data-sending') || label;
      fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
          if (res.ok) {
            if (res.data.redirect) { location.href = res.data.redirect; return; }
            status.textContent = res.data.message;
            status.hidden = false;
            form.classList.add('is-sent');
            status.focus && status.setAttribute('tabindex', '-1');
            status.focus();
            return;
          }
          var errors = res.data.errors || {};
          var first = null;
          Object.keys(errors).forEach(function (name) {
            var input = form.querySelector('[name="' + name + '"]');
            var field = input && input.closest('.b-form-field');
            if (!field) return;
            field.classList.add('has-error');
            var p = field.querySelector('.b-form-error');
            p.textContent = errors[name][0];
            p.hidden = false;
            first = first || input;
          });
          if (!first) { status.textContent = res.data.message || form.getAttribute('data-failed'); status.classList.add('is-error'); status.hidden = false; }
          else first.focus();
        })
        .catch(function () { status.textContent = form.getAttribute('data-failed'); status.classList.add('is-error'); status.hidden = false; })
        .then(function () { button.removeAttribute('aria-busy'); button.textContent = label; });
    });
  });
})();
