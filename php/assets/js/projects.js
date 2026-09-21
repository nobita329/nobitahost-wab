(function () {
  'use strict';

  var addBtn = document.getElementById('projectAddBtn');
  var addForm = document.getElementById('projectAddForm');
  if (addBtn && addForm) {
    addBtn.addEventListener('click', function () {
      var hidden = addForm.hasAttribute('hidden');
      addForm.toggleAttribute('hidden');
      if (!hidden) addForm.querySelector('input[name="name"]').focus();
    });
  }

  document.querySelectorAll('[data-toggle-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = document.getElementById('projectEdit-' + btn.dataset.toggleEdit);
      if (form) form.toggleAttribute('hidden');
    });
  });

  var overlay = document.getElementById('previewOverlay');
  var frame = document.getElementById('previewFrame');
  var wrap = document.getElementById('previewWrap');
  var grip = document.getElementById('previewResize');
  var pTitle = document.getElementById('previewTitle');
  if (overlay && frame) {
    function fitHeight() {
      try {
        var doc = frame.contentDocument;
        if (doc && doc.body) {
          var h = Math.max(doc.body.scrollHeight, doc.documentElement ? doc.documentElement.scrollHeight : 0);
          frame.style.height = Math.max(h, 200) + 'px';
        }
      } catch (e) { /* cross-origin */ }
    }

    if (grip) {
      grip.addEventListener('mousedown', function (e) {
        e.preventDefault();
        var startX = e.clientX;
        var startW = frame.getBoundingClientRect().width;
        var maxW = wrap.parentElement.clientWidth;
        function onMove(ev) {
          var w = startW + (ev.clientX - startX);
          w = Math.max(320, Math.min(w, maxW));
          frame.style.width = w + 'px';
        }
        function onUp() {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
      });
    }

    document.querySelectorAll('.project-preview').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var srcWrap = btn.closest('.project-card-wrap');
        var src = srcWrap.querySelector('.project-html-src');
        frame.srcdoc = src ? src.value : '';
        pTitle.textContent = srcWrap.querySelector('.project-card h3').textContent;
        frame.style.width = '100%';
        frame.style.height = '200px';
        overlay.hidden = false;
        document.body.classList.add('modal-open');
        setTimeout(fitHeight, 60);
      });
    });
    frame.addEventListener('load', fitHeight);

    function closePreview() {
      overlay.hidden = true;
      frame.srcdoc = '';
      document.body.classList.remove('modal-open');
    }
    document.getElementById('previewClose').addEventListener('click', closePreview);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closePreview(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePreview(); });
  }
})();
