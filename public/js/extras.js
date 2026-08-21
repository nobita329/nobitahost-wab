(function () {
  'use strict';

  /* ---------- SimpleToast: server flash messages as toasts ---------- */
  var FLASH = {
    saved: 'Settings saved', created: 'Created successfully', deleted: 'Deleted successfully',
    edited: 'Changes saved', password: 'Password changed', suspend: 'Updated', resume: 'Updated',
    yes: 'Thanks for your feedback!', no: 'Thanks — we will improve it.', same: 'Feedback already recorded'
  };
  try {
    var q = new URLSearchParams(location.search);
    var shown = false;
    FLASH && Object.keys(FLASH).forEach(function (k) {
      if (!shown && q.has(k) && q.get(k) !== '0') {
        var v = q.get(k);
        var msg = v && v !== '1' ? FLASH[k] + ' (' + v + ')' : FLASH[k];
        if (typeof window.toast === 'function') window.toast(msg, k === 'deleted' ? 'error' : 'success');
        shown = true;
      }
    });
    if (shown) {
      q.delete('saved'); q.delete('created'); q.delete('deleted'); q.delete('edited');
      q.delete('password'); q.delete('suspend'); q.delete('resume'); q.delete('fb');
      var clean = location.pathname + (q.toString() ? '?' + q.toString() : '');
      history.replaceState(null, '', clean);
    }
  } catch (e) {}

  function extras() { return (window.__NH_EXTRAS || {}); }

  /* ---------- CookieBanner ---------- */
  function initCookieBanner() {
    if (!extras().cookieBanner) return;
    var ok = false;
    try { ok = localStorage.getItem('nh-cookie-consent') !== null; } catch (e) {}
    if (ok) return;
    var bar = document.createElement('div');
    bar.className = 'cookie-banner';
    bar.innerHTML =
      '<div class="cb-text"><b>We use cookies</b><span>We use cookies to improve your experience, analyse traffic and keep things running smoothly.</span></div>' +
      '<div class="cb-actions">' +
      '<button type="button" class="btn btn-sm" id="cbDecline">Decline</button>' +
      '<button type="button" class="btn btn-accent btn-sm" id="cbAccept">Accept all</button>' +
      '</div>';
    document.body.appendChild(bar);
    requestAnimationFrame(function () { bar.classList.add('show'); });
    function close(val) {
      try { localStorage.setItem('nh-cookie-consent', val); } catch (e) {}
      bar.classList.remove('show');
      setTimeout(function () { bar.remove(); }, 350);
      if (typeof window.toast === 'function') window.toast(val === 'all' ? 'Cookies accepted' : 'Cookies declined', 'success');
    }
    bar.querySelector('#cbAccept').addEventListener('click', function () { close('all'); });
    bar.querySelector('#cbDecline').addEventListener('click', function () { close('essential'); });
  }

  /* ---------- AntiAdblock ---------- */
  function initAntiAdblock() {
    if (!extras().antiAdblock) return;
    function blocked() {
      var bait = document.createElement('div');
      bait.className = 'adsbox ad-banner ad-slot pub_300x250';
      bait.style.cssText = 'position:absolute;left:-9999px;top:-9999px;height:60px;width:320px;';
      bait.innerHTML = '&nbsp;';
      document.body.appendChild(bait);
      var isBlocked = bait.offsetHeight === 0 || bait.offsetParent === null || getComputedStyle(bait).display === 'none';
      bait.remove();
      return isBlocked;
    }
    window.addEventListener('load', function () {
      setTimeout(function () {
        if (!blocked()) return;
        if (document.getElementById('adblockShield')) return;
        var ov = document.createElement('div');
        ov.id = 'adblockShield';
        ov.className = 'ab-shield';
        ov.innerHTML =
          '<div class="ab-card">' +
          '<div class="ab-icon"><svg width="34" height="34" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="m5.5 5.5 13 13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>' +
          '<h3>Ad blocker detected</h3>' +
          '<p>This panel stays free thanks to ads. Please disable your ad blocker and reload the page to continue.</p>' +
          '<div class="ab-actions">' +
          '<button type="button" class="btn btn-accent" id="abReload">Reload page</button>' +
          '<button type="button" class="btn btn-ghost btn-sm" id="abClose">Continue anyway</button>' +
          '</div></div>';
        document.body.appendChild(ov);
        requestAnimationFrame(function () { ov.classList.add('show'); });
        ov.querySelector('#abReload').addEventListener('click', function () { location.reload(); });
        ov.querySelector('#abClose').addEventListener('click', function () {
          ov.classList.remove('show');
          setTimeout(function () { ov.remove(); }, 300);
        });
      }, 800);
    });
  }

  initCookieBanner();
  initAntiAdblock();
})();
