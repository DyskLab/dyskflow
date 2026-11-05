document.addEventListener('click', function (e) {
  if (!e.target.closest('[data-theme-toggle]')) return;
  var dark = document.documentElement.classList.toggle('dark');
  try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (err) {}
  var themeColor = document.querySelector('meta[name="theme-color"]');
  if (themeColor) themeColor.content = dark ? '#141210' : '#fbf9f7';
});

/* Runs after load: jumping to #comments would otherwise clear the focus again. */
window.addEventListener('load', function () {
  setTimeout(function () {
    var firstInvalid = document.querySelector('[aria-invalid="true"]');
    if (firstInvalid) firstInvalid.focus();
  }, 0);
});

document.addEventListener('submit', function (e) {
  var button = e.submitter;
  if (!button || (e.target.getAttribute('method') || '').toLowerCase() !== 'post') return;
  setTimeout(function () {
    if (e.defaultPrevented) return;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
  }, 0);
});
window.addEventListener('pageshow', function () {
  document.querySelectorAll('[aria-busy="true"]').forEach(function (button) { button.disabled = false; button.removeAttribute('aria-busy'); });
});
