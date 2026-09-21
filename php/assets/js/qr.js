/* ============================================================================
   Client-side QR rendering for the PHP edition.
   The Node build rendered the 2FA QR server-side (node-qrcode). The PHP edition
   ships the otpauth:// URI and draws it here with the vendored MIT-licensed
   qrcode-generator (Kazuhiko Arase) — no network, no server dependency.
   Usage: <img class="js-qr" data-qr="otpauth://totp/...">
   ============================================================================ */
(function () {
  'use strict';

  function render(el) {
    var text = el.getAttribute('data-qr') || '';
    if (!text || typeof window.qrcode !== 'function') return;
    try {
      var qr = window.qrcode(0, 'M');
      qr.addData(text);
      qr.make();
      var n = qr.getModuleCount();
      var cell = Math.max(3, Math.floor(228 / n));
      var px = n * cell;
      var canvas = document.createElement('canvas');
      canvas.width = px; canvas.height = px;
      var ctx = canvas.getContext('2d');
      if (!ctx) return;
      ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, px, px);
      ctx.fillStyle = '#111111';
      for (var r = 0; r < n; r++) {
        for (var c = 0; c < n; c++) {
          if (qr.isDark(r, c)) ctx.fillRect(c * cell, r * cell, cell, cell);
        }
      }
      el.src = canvas.toDataURL('image/png');
      el.removeAttribute('data-qr');
    } catch (e) {
      el.alt = 'Scan failed — enter the secret manually';
    }
  }

  function run() {
    var list = document.querySelectorAll('img.js-qr[data-qr], canvas.js-qr[data-qr]');
    Array.prototype.forEach.call(list, render);
  }

  if (document.readyState !== 'loading') run();
  else document.addEventListener('DOMContentLoaded', run);
  window.NH = window.NH || {};
  window.NH.renderQr = run;
})();
