/* ============================================================================
   CasaOS shell behaviour — widgets, dock, menus, modals, toasts, theme
   ============================================================================ */
(function () {
  'use strict';
  var NH = (window.NH = window.NH || {});

  /* ---------------- theme ---------------- */
  var THEME_KEY = 'nh-theme';
  function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem(THEME_KEY, t); } catch (e) {}
    var btn = document.getElementById('csThemeToggle');
    if (btn) {
      btn.innerHTML = t === 'light'
        ? '<svg class="ic"><use href="#i-moon"/></svg>'
        : '<svg class="ic"><use href="#i-sun"/></svg>';
    }
  }
  NH.theme = {
    get: function () {
      try { return localStorage.getItem(THEME_KEY) || document.documentElement.getAttribute('data-theme') || 'dark'; }
      catch (e) { return 'dark'; }
    },
    toggle: function () { applyTheme(NH.theme.get() === 'light' ? 'dark' : 'light'); }
  };
  var stored = null;
  try { stored = localStorage.getItem(THEME_KEY); } catch (e) {}
  if (stored) applyTheme(stored);

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    /* ---------------- dock (mobile) ---------------- */
    var dockBtn = document.getElementById('csDockToggle');
    var backdrop = document.querySelector('.cs-dock-backdrop');
    function closeDock() { document.body.classList.remove('cs-dock-open'); }
    if (dockBtn) dockBtn.addEventListener('click', function () { document.body.classList.toggle('cs-dock-open'); });
    if (backdrop) backdrop.addEventListener('click', closeDock);
    document.querySelectorAll('.cs-dock-item').forEach(function (a) { a.addEventListener('click', closeDock); });

    /* ---------------- menus ---------------- */
    document.querySelectorAll('[data-cs-menu]').forEach(function (trigger) {
      var menu = document.querySelector(trigger.getAttribute('data-cs-menu'));
      if (!menu) return;
      trigger.addEventListener('click', function (ev) {
        ev.preventDefault(); ev.stopPropagation();
        var open = menu.classList.contains('open');
        document.querySelectorAll('.cs-menu.open').forEach(function (m) { m.classList.remove('open'); });
        if (!open) menu.classList.add('open');
      });
    });
    document.addEventListener('click', function () {
      document.querySelectorAll('.cs-menu.open').forEach(function (m) { m.classList.remove('open'); });
    });

    /* ---------------- theme toggle ---------------- */
    var tBtn = document.getElementById('csThemeToggle');
    if (tBtn) tBtn.addEventListener('click', function () { NH.theme.toggle(); });

    /* ---------------- modals ---------------- */
    document.addEventListener('click', function (ev) {
      var opener = ev.target.closest('[data-cs-open]');
      if (opener) {
        var m = document.getElementById(opener.getAttribute('data-cs-open'));
        if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
      }
      var closer = ev.target.closest('[data-cs-close]');
      if (closer) {
        var box = closer.closest('.cs-modal-backdrop');
        if (box) { box.classList.remove('open'); document.body.style.overflow = ''; }
      }
      if (ev.target.classList && ev.target.classList.contains('cs-modal-backdrop')) {
        ev.target.classList.remove('open'); document.body.style.overflow = '';
      }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') {
        document.querySelectorAll('.cs-modal-backdrop.open').forEach(function (m) { m.classList.remove('open'); });
        document.body.style.overflow = '';
        closeDock();
      }
    });

    /* ---------------- clock ---------------- */
    var hh = document.getElementById('csClockTime');
    var dd = document.getElementById('csClockDate');
    function tick() {
      var d = new Date();
      if (hh) {
        hh.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      }
      if (dd) dd.textContent = d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });
    }
    tick(); setInterval(tick, 10000);

    /* ---------------- toasts ---------------- */
    var wrap = document.getElementById('csToasts') || (function () {
      var el = document.createElement('div');
      el.id = 'csToasts'; el.className = 'cs-toasts';
      document.body.appendChild(el); return el;
    })();
    NH.toast = function (msg, type) {
      var el = document.createElement('div');
      el.className = 'cs-toast ' + (type || 'info');
      el.textContent = msg;
      wrap.appendChild(el);
      setTimeout(function () { el.style.opacity = '0'; el.style.transform = 'translateY(6px)'; }, 2600);
      setTimeout(function () { el.remove(); }, 3000);
    };

    /* ---------------- flash messages from PHP ---------------- */
    var flashBox = document.getElementById('csFlash');
    if (flashBox) {
      try {
        var items = JSON.parse(flashBox.getAttribute('data-flash') || '[]');
        items.forEach(function (f) { NH.toast(f.message, f.type === 'success' ? 'ok' : (f.type === 'error' ? 'err' : 'info')); });
      } catch (e) {}
    }

    /* ---------------- app grid filter ---------------- */
    var search = document.getElementById('csAppSearch');
    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        document.querySelectorAll('.cs-app').forEach(function (app) {
          var name = (app.getAttribute('data-name') || app.textContent || '').toLowerCase();
          app.style.display = !q || name.indexOf(q) !== -1 ? '' : 'none';
        });
      });
    }

    /* ---------------- system widgets ---------------- */
    var cpu = document.getElementById('csCpu');
    var mem = document.getElementById('csMem');
    var disk = document.getElementById('csDisk');
    var net = document.getElementById('csNet');
    var temp = document.getElementById('csTemp');
    var storageChip = document.getElementById('csStorageChip');
    if (cpu || mem || disk || net) {
      var setMeter = function (barEl, pct) {
        if (!barEl) return;
        var i = barEl.querySelector('i') || barEl;
        i.style.width = Math.max(0, Math.min(100, pct)) + '%';
        i.classList.toggle('warn', pct >= 70 && pct < 90);
        i.classList.toggle('crit', pct >= 90);
      };
      var poll = function () {
        fetch('/api/system', { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (!d || !d.success) return;
            var s = d.data || d;
            if (cpu) { cpu.querySelector('b').textContent = (s.cpu ? s.cpu.usage : 0) + '%'; setMeter(cpu.querySelector('.cs-meter'), s.cpu ? s.cpu.usage : 0); }
            if (mem) { mem.querySelector('b').textContent = (s.mem ? s.mem.usedStr : '—'); setMeter(mem.querySelector('.cs-meter'), s.mem ? s.mem.usage : 0); }
            if (disk) { disk.querySelector('b').textContent = (s.disk ? s.disk.usedStr : '—'); setMeter(disk.querySelector('.cs-meter'), s.disk ? s.disk.usage : 0); }
            if (net) { net.querySelector('b').textContent = '↓' + (s.network ? s.network.rxRateStr : '0 B/s') + ' ↑' + (s.network ? s.network.txRateStr : '0 B/s'); }
            if (temp) temp.querySelector('b').textContent = s.health ? s.health.tempStr : 'N/A';
            if (storageChip) {
              var pct = s.disk ? s.disk.usage : 0;
              storageChip.querySelector('b').textContent = pct + '%';
              setMeter(storageChip.querySelector('.cs-meter'), pct);
            }
            document.dispatchEvent(new CustomEvent('nh:system', { detail: s }));
          })
          .catch(function () {});
      };
      poll();
      setInterval(poll, 5000);
    }
  });
})();
