(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    setupTheme();
    setupSidebar();
    setupDropdown();
    setupModals();
    setupCopyButtons();
    setupConfirms();
    setupPassToggles();
    setupTerminal();
    setupAlerts();
    setupLiveName();
  });

  function setupTheme() {
    var root = document.documentElement;
    var btn = document.getElementById('themeToggle');
    var lastDark = 'dark';
    try {
      var saved = localStorage.getItem('lucent-mode');
      if (saved && saved !== 'light') lastDark = saved;
    } catch (e) {}

    function current() { return root.getAttribute('data-theme') || 'dark'; }
    function syncIcon() {
      if (!btn) return;
      var dark = current() !== 'light';
      btn.innerHTML = '<svg class="ic"><use href="#i-' + (dark ? 'sun' : 'moon') + '"/></svg>';
    }
    syncIcon();
    if (!btn) return;
    btn.addEventListener('click', function () {
      var next = current() === 'light' ? lastDark : 'light';
      if (current() !== 'light') lastDark = current();
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('lucent-mode', next); } catch (e) {}
      syncIcon();
    });
  }

  function setupSidebar() {
    var toggle = document.getElementById('sidebarToggle');
    if (toggle) toggle.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
    var backdrop = document.getElementById('sidebarBackdrop');
    if (backdrop) backdrop.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
  }

  function setupDropdown() {
    var chip = document.getElementById('userChip');
    var dd = document.getElementById('userDropdown');
    if (!chip || !dd) return;
    chip.addEventListener('click', function (e) {
      e.stopPropagation();
      chip.classList.toggle('open');
      dd.classList.toggle('open');
    });
    document.addEventListener('click', function () { chip.classList.remove('open'); dd.classList.remove('open'); });
  }

  function setupModals() {
    document.querySelectorAll('[data-modal]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-modal');
        var modal = document.querySelector(id);
        if (modal) modal.classList.add('open');
      });
    });
    document.querySelectorAll('.modal [data-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { btn.closest('.modal').classList.remove('open'); });
    });
    document.querySelectorAll('.modal').forEach(function (m) {
      m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('open'); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') document.querySelectorAll('.modal.open').forEach(function (m) { m.classList.remove('open'); });
    });
  }

  function setupCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy');
        var copiedMsg = btn.getAttribute('data-copied') || 'Copied!';
        var done = function () {
          var old = btn.textContent;
          btn.textContent = copiedMsg;
          setTimeout(function () { btn.textContent = old; }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
        } else { fallbackCopy(text, done); }
      });
    });
    var copySecret = document.getElementById('copySecret');
    if (copySecret) {
      copySecret.addEventListener('click', function () {
        var secret = copySecret.parentElement.querySelector('.secret');
        if (navigator.clipboard) navigator.clipboard.writeText(secret.textContent);
        toast('Secret copied', 'success');
      });
    }
  }

  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) { toast('Copy failed', 'error'); }
    document.body.removeChild(ta);
  }

  function setupConfirms() {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!confirm(form.getAttribute('data-confirm'))) e.preventDefault();
      });
    });
  }

  function setupPassToggles() {
    document.querySelectorAll('.pass-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = btn.parentElement.querySelector('input');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
      });
    });
  }

  function setupTerminal() {
    var body = document.getElementById('termBody');
    var input = document.getElementById('termCmd');
    var run = document.getElementById('termRun');
    var out = document.getElementById('termOut');
    if (!body || !input || !run || !out) return;

    var commands = {
      'npm run build': ['✔ settings seeded', '✔ content pages seeded', '✔ admin ready', '✅ NobitaHost is ready.'],
      'npm run createuser': ['? Username: admin', '? Email: admin@example.com', '? Password: ********', '✔ User created (#1)'],
      'pm2 start ecosystem.config.js': ['[PM2] Starting nobitahost-web ... online', '[PM2] Starting nobitahost-api  ... online'],
      'pm2 logs nobitahost-web': ['[NobitaHost] Web Panel  -> http://localhost:3001', '[NobitaHost] API Panel   -> http://localhost:3002'],
      'help': ['Available: npm run build, npm run createuser, pm2 start ecosystem.config.js, pm2 logs nobitahost-web', 'Also: hello, whoami, clear'],
      'whoami': ['nobita@host — Administrator'],
      'hello': ['Hello, nobita! Welcome to NobitaHost 🎉']
    };
    var hint = ['This is a demo terminal. Try: npm run build, createuser, pm2, help', 'Demo commands: help, hello, whoami, clear'];

    run.addEventListener('click', exec);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') exec(); });

    function exec() {
      var cmd = input.value.trim();
      if (!cmd) return;
      input.value = '';
      var line = body.querySelector('p');
      if (line) line.querySelector('#termInput').textContent = cmd;
      var text = commands[cmd] || hint;
      if (cmd === 'clear') {
        body.innerHTML = '<p><span class="t-prompt">nobita@host:~$</span> <span id="termInput"></span><span class="t-cursor">▍</span></p>' +
          '<p class="t-out" id="termOut"></p>';
        return;
      }
      out.textContent = text.join('\n');
    }
  }

  function setupAlerts() {
    document.querySelectorAll('.alert').forEach(function (a) {
      var t = setTimeout(function () { a.style.transition = 'opacity .4s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 420); }, 6000);
      a.addEventListener('click', function () { clearTimeout(t); a.remove(); });
    });
  }

  function setupLiveName() {
    var inp = document.querySelector('[data-live="panelName"]');
    var brand = document.querySelector('.brand-text b');
    var authBrand = document.querySelector('.auth-brand h1');
    if (inp) {
      inp.addEventListener('input', function () {
        if (brand) brand.textContent = inp.value;
        if (authBrand) authBrand.textContent = inp.value;
      });
    }
  }

  window.toast = function (msg, type) {
    var wrap = document.getElementById('toasts');
    if (!wrap) return;
    var el = document.createElement('div');
    el.className = 'toast ' + (type || '');
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 400); }, 2800);
  };
})();
