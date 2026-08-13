(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {

    /* Auto-save (debounced) */
    var saveTimer = null;
    function scheduleSave(data) {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(function () {
        fetch('/admin/settings/api', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(data)
        }).then(function (r) { return r.json(); })
          .then(function (j) {
            if (j && j.ok) window.toast('Settings saved', 'success');
            else window.toast('Save failed: ' + (j && j.error ? j.error : ''), 'error');
          })
          .catch(function () { window.toast('Save failed', 'error'); });
      }, 700);
    }

    /* Logo */
    var logoType = document.querySelector('[data-live="logoType"]');
    var logoEmoji = document.querySelector('[data-live="logoEmoji"]');
    var logoUrl = document.querySelector('[data-live="logoUrl"]');
    var logoPreview = document.getElementById('logoPreview');
    var logoEmojiField = document.getElementById('logoEmojiField');
    var logoUrlField = document.getElementById('logoUrlField');
    var logoFile = document.querySelector('[name="logo_file"]');

    function syncLogoFields() {
      var urlMode = logoType && logoType.value === 'url';
      if (logoEmojiField) logoEmojiField.style.display = urlMode ? 'none' : 'block';
      if (logoUrlField) logoUrlField.style.display = urlMode ? 'block' : 'none';
    }
    function renderLogo() {
      if (!logoPreview) return;
      if (logoType && logoType.value === 'url' && logoUrl && logoUrl.value) {
        logoPreview.innerHTML = '<img src="' + logoUrl.value + '">';
      } else if (logoEmoji && logoEmoji.value) {
        logoPreview.textContent = logoEmoji.value;
      }
    }
    if (logoType) { logoType.addEventListener('change', function () { syncLogoFields(); renderLogo(); }); }
    if (logoEmoji) logoEmoji.addEventListener('input', renderLogo);
    if (logoUrl) logoUrl.addEventListener('input', renderLogo);
    if (logoFile) logoFile.addEventListener('change', function () {
      var f = logoFile.files[0];
      if (!f) return;
      var r = new FileReader();
      r.onload = function () { if (logoPreview) logoPreview.innerHTML = '<img src="' + r.result + '">'; };
      r.readAsDataURL(f);
    });
    syncLogoFields();

    /* Favicon */
    var faviconUrl = document.querySelector('[data-live="faviconUrl"]');
    var faviconPreview = document.getElementById('faviconPreview');
    if (faviconUrl) faviconUrl.addEventListener('input', function () {
      if (!faviconPreview) return;
      faviconPreview.innerHTML = faviconUrl.value ? '<img src="' + faviconUrl.value + '">' : '<img src="/img/favicon.svg">';
    });

    /* Background */
    var bgUrl = document.querySelector('[data-live="bgUrl"]');
    var bgType = document.querySelector('[data-live="bgType"]');
    var bgSource = document.querySelector('[data-live="bgSource"]');
    var bgFile = document.querySelector('[data-live="bgFile"]');
    var bgPreviewImg = document.querySelector('[data-preview-img]');
    var bgPreviewVideo = document.querySelector('[data-preview-video]');

    function renderBg(url, type) {
      var isVideo = type === 'video' || (url && /\.(mp4|webm|ogg|m4v|mov)(\?|$)/i.test(url));
      if (bgPreviewVideo) {
        bgPreviewVideo.hidden = !isVideo;
        if (isVideo && url) { bgPreviewVideo.src = url; bgPreviewVideo.load(); }
        else if (bgPreviewVideo.src) bgPreviewVideo.removeAttribute('src');
      }
      if (bgPreviewImg) {
        if (isVideo) {
          bgPreviewImg.style.backgroundImage = 'none';
        } else if (url) {
          bgPreviewImg.style.backgroundImage = 'url("' + url + '")';
          bgPreviewImg.style.backgroundSize = 'cover';
          bgPreviewImg.style.backgroundPosition = 'center';
        } else {
          bgPreviewImg.style.backgroundImage = 'none';
        }
      }
    }
    function currentBgType() { return bgType ? bgType.value : 'image'; }
    if (bgUrl) bgUrl.addEventListener('input', function () { renderBg(bgUrl.value, currentBgType()); });
    if (bgType) bgType.addEventListener('change', function () { renderBg(bgUrl.value, bgType.value); });
    if (bgSource) bgSource.addEventListener('change', function () {
      if (bgSource.value && bgSource.value !== 'none') {
        renderBg('https://picsum.photos/seed/' + encodeURIComponent(bgSource.value) + '-nobitahost/600/300', 'image');
      } else if (bgUrl) renderBg(bgUrl.value, currentBgType());
    });
    if (bgFile) bgFile.addEventListener('change', function () {
      var f = bgFile.files[0];
      if (!f) return;
      var r = new FileReader();
      r.onload = function () {
        renderBg(r.result, f.type.indexOf('video') > -1 ? 'video' : 'image');
      };
      r.readAsDataURL(f);
    });

    /* Glass & Transparency */
    var theme = document.querySelector('[data-live="theme"]');
    if (theme) {
      theme.addEventListener('change', function () {
        document.documentElement.setAttribute('data-theme', theme.value);
        scheduleSave({ theme: theme.value });
      });
    }

    var trans = document.querySelector('[data-live="transparency"]');
    var transVal = document.getElementById('transVal');
    function applyTransparency(v) {
      var t = Math.min(100, Math.max(0, parseInt(v, 10) || 100));
      var alpha = ((100 - t) / 100 * 0.55).toFixed(3);
      document.documentElement.style.setProperty('--overlay', alpha);
      if (transVal) transVal.textContent = t + '%';
    }
    if (trans) {
      trans.addEventListener('input', function () {
        applyTransparency(trans.value);
        scheduleSave({ transparency: trans.value });
      });
    }

    var blur = document.querySelector('[data-live="panelBlur"]');
    var blurVal = document.getElementById('blurVal');
    function applyBlur(v) {
      var b = Math.min(40, Math.max(0, parseInt(v, 10) || 0));
      document.documentElement.style.setProperty('--glass', b + 'px');
      if (blurVal) blurVal.textContent = b + 'px';
    }
    if (blur) {
      blur.addEventListener('input', function () {
        applyBlur(blur.value);
        scheduleSave({ panel_blur: blur.value });
      });
    }

    /* Accent + radius (live + auto-save) */
    var accent = document.querySelector('[data-live="accentColor"]');
    if (accent) accent.addEventListener('input', function () {
      document.documentElement.style.setProperty('--accent', accent.value);
      scheduleSave({ accent_color: accent.value });
    });
    var radius = document.querySelector('[data-live="cardRadius"]');
    if (radius) radius.addEventListener('input', function () {
      document.documentElement.style.setProperty('--card-radius', radius.value + 'px');
      scheduleSave({ card_radius: radius.value });
    });

    /* Reset to default */
    var resetBtn = document.getElementById('resetAppearanceBtn');
    if (resetBtn) resetBtn.addEventListener('click', function () {
      fetch('/admin/settings/reset', { method: 'POST', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && j.ok) { window.toast('Defaults restored', 'success'); setTimeout(function () { location.reload(); }, 600); }
          else window.toast('Reset failed', 'error');
        })
        .catch(function () { window.toast('Reset failed', 'error'); });
    });

    /* Music test */
    var musicUrl = document.querySelector('[data-live="musicUrl"]');
    var musicTest = document.getElementById('musicTestBtn');
    var musicTestAudio = document.getElementById('musicTestAudio');
    if (musicTest && musicTestAudio) {
      musicTest.addEventListener('click', function () {
        if (musicUrl && musicUrl.value && musicTestAudio.src !== musicUrl.value) {
          musicTestAudio.src = musicUrl.value;
        }
        if (musicTestAudio.paused) {
          musicTestAudio.play().then(function () { musicTest.textContent = 'Stop'; });
        } else {
          musicTestAudio.pause();
          musicTestAudio.currentTime = 0;
          musicTest.textContent = 'Play / Stop';
        }
      });
      musicTestAudio.addEventListener('ended', function () { musicTest.textContent = 'Play / Stop'; });
    }

    /* Volume label */
    var vol = document.querySelector('[data-live="musicVolume"]');
    var volVal = document.getElementById('volVal');
    if (vol && volVal) vol.addEventListener('input', function () { volVal.textContent = vol.value; });

    /* Automated script preview */
    var scriptCmd = document.querySelector('[data-live="scriptCmd"]');
    var scriptCmdPreview = document.getElementById('scriptCmdPreview');
    if (scriptCmd && scriptCmdPreview) {
      scriptCmd.addEventListener('input', function () { scriptCmdPreview.textContent = scriptCmd.value; });
    }
    var scriptCopy = document.getElementById('scriptCopyPreview');
    if (scriptCopy) {
      scriptCopy.addEventListener('click', function () {
        var text = scriptCmdPreview ? scriptCmdPreview.textContent : scriptCmd.value;
        var done = function () {
          var old = scriptCopy.textContent;
          scriptCopy.textContent = 'Copied to clipboard!';
          setTimeout(function () { scriptCopy.textContent = old; }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () { /* noop */ });
        }
      });
    }

    /* Discord preview */
    var discordServer = document.querySelector('[data-live="discordServer"]');
    var discordTheme = document.querySelector('[name="discord_theme"]');
    var discordPreview = document.getElementById('discordPreview');
    function renderDiscord() {
      if (!discordPreview) return;
      var id = discordServer ? discordServer.value : '';
      var theme2 = discordTheme ? discordTheme.value : 'dark';
      discordPreview.src = 'https://discord.com/widget?id=' + encodeURIComponent(id) + '&theme=' + encodeURIComponent(theme2);
    }
    if (discordServer) discordServer.addEventListener('input', renderDiscord);
    if (discordTheme) discordTheme.addEventListener('change', renderDiscord);

    /* YouTube preview */
    var ytChannel = document.querySelector('[data-live="ytChannel"]');
    var ytTitle = document.getElementById('ytTitle');
    var ytHandle = document.getElementById('ytHandle');
    if (ytChannel && ytTitle) {
      ytChannel.addEventListener('input', function () {
        var val = ytChannel.value.trim();
        ytTitle.textContent = val || '—';
        if (ytHandle) {
          var m = val.match(/@([\w.-]+)/);
          ytHandle.textContent = m ? 'youtube.com/@' + m[1] : '';
        }
      });
    }

    /* ===== Wallpaper browser ===== */
    var wpModal = document.getElementById('wpModal');
    var wpGrid = document.getElementById('wpGrid');
    var wpCat = document.getElementById('wpCat');
    var wpSearch = document.getElementById('wpSearch');
    var wpSearchBtn = document.getElementById('wpSearchBtn');
    var wpClearBtn = document.getElementById('wpClearBtn');
    var wpPrev = document.getElementById('wpPrev');
    var wpNext = document.getElementById('wpNext');
    var wpPageInfo = document.getElementById('wpPageInfo');
    var wpFavBtn = document.getElementById('wpFavBtn');
    var wpFavCount = document.getElementById('wpFavCount');
    var wpBrowseBtn = document.getElementById('wpBrowseBtn');
    var wpFavs = {};
    var wpState = { category: 'all', page: 1, q: '', favOnly: false, totalPages: null, hasNext: false };

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function updateFavCount() {
      var n = Object.keys(wpFavs).length;
      if (wpFavCount) wpFavCount.textContent = n;
    }

    function openWp(mode) {
      if (!wpModal) return;
      wpModal.classList.add('open');
      if (mode === 'favs') {
        wpState.favOnly = true;
        renderWpFavs();
      } else {
        wpState.favOnly = false;
        if (wpCat) wpCat.value = wpState.category;
        loadWp();
      }
    }

    function loadWp() {
      if (!wpGrid) return;
      wpGrid.innerHTML = '<div class="wp-loading"><span class="wp-spin"></span> Loading wallpapers…</div>';
      var params = new URLSearchParams({ category: wpState.category, page: String(wpState.page) });
      if (wpState.q) params.set('q', wpState.q);
      fetch('/admin/wallpapers?' + params.toString(), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j || !j.ok) { wpGrid.innerHTML = '<div class="wp-empty">' + esc(j && j.error ? j.error : 'Failed to load wallpapers') + '</div>'; return; }
          wpState.totalPages = j.totalPages && j.totalPages > 0 ? j.totalPages : null;
          wpState.hasNext = !!j.hasNext;
          renderWpGrid(j.items || []);
        })
        .catch(function () { wpGrid.innerHTML = '<div class="wp-empty">Failed to load wallpapers.</div>'; });
    }

    function renderWpGrid(items) {
      if (!items.length) { wpGrid.innerHTML = '<div class="wp-empty">No wallpapers found.</div>'; }
      else {
        wpGrid.innerHTML = items.map(function (it) {
          var isFav = !!wpFavs[it.id];
          return '<div class="wp-item' + (isFav ? ' fav' : '') + '" data-id="' + esc(it.id) + '">' +
            '<img src="' + esc(it.thumb) + '" alt="' + esc(it.title) + '" loading="lazy">' +
            '<div class="wp-actions">' +
            '<button type="button" class="wp-fav' + (isFav ? ' on' : '') + '" title="' + (isFav ? 'Remove favorite' : 'Add favorite') + '">' + (isFav ? '♥' : '♡') + '</button>' +
            '<button type="button" class="wp-apply">Apply</button>' +
            '<a class="wp-dl" href="' + esc(it.full) + '" target="_blank" rel="noopener" title="Open full image">⬇</a>' +
            '</div>' +
            '<span class="wp-title">' + esc(it.title) + '</span>' +
            '</div>';
        }).join('');
      }
      if (wpPageInfo) wpPageInfo.textContent = wpState.totalPages ? 'Page ' + wpState.page + ' of ' + wpState.totalPages : 'Page ' + wpState.page;
      if (wpPrev) wpPrev.disabled = wpState.page <= 1;
      if (wpNext) wpNext.disabled = wpState.favOnly || !wpState.hasNext;
      bindWpItems();
    }

    function renderWpFavs() {
      if (!wpGrid) return;
      var items = Object.keys(wpFavs).map(function (k) { return wpFavs[k]; });
      if (!items.length) { wpGrid.innerHTML = '<div class="wp-empty">No favorites yet. Tap ♡ on any wallpaper.</div>'; }
      else {
        wpGrid.innerHTML = items.map(function (it) {
          return '<div class="wp-item fav" data-id="' + esc(it.id) + '">' +
            '<img src="' + esc(it.thumb) + '" alt="' + esc(it.title) + '" loading="lazy">' +
            '<div class="wp-actions">' +
            '<button type="button" class="wp-fav on" title="Remove favorite">♥</button>' +
            '<button type="button" class="wp-apply">Apply</button>' +
            '<a class="wp-dl" href="' + esc(it.full) + '" target="_blank" rel="noopener" title="Open full image">⬇</a>' +
            '</div>' +
            '<span class="wp-title">' + esc(it.title) + '</span>' +
            '</div>';
        }).join('');
      }
      if (wpPageInfo) wpPageInfo.textContent = wpState.favOnly ? items.length + ' favorite(s)' : '';
      if (wpPrev) wpPrev.disabled = true;
      if (wpNext) wpNext.disabled = true;
      bindWpItems();
    }

    function bindWpItems() {
      if (!wpGrid) return;
      wpGrid.querySelectorAll('.wp-fav').forEach(function (btn) {
        btn.addEventListener('click', function () { toggleFav(btn); });
      });
      wpGrid.querySelectorAll('.wp-apply').forEach(function (btn) {
        btn.addEventListener('click', function () { applyWp(btn); });
      });
    }

    function currentItem(btn) {
      var el = btn.closest('.wp-item');
      var id = el.getAttribute('data-id');
      var item = wpFavs[id] || {
        id: id,
        title: el.querySelector('.wp-title').textContent,
        thumb: el.querySelector('img').src,
        full: el.querySelector('.wp-dl').href
      };
      return item;
    }

    function toggleFav(btn) {
      var el = btn.closest('.wp-item');
      var id = el.getAttribute('data-id');
      var item = currentItem(btn);
      var adding = !wpFavs[id];
      fetch('/admin/wallpapers/fav', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: adding ? 'add' : 'remove', item: item })
      }).then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && j.ok) {
            wpFavs = {};
            (j.favs || []).forEach(function (f) { wpFavs[f.id] = f; });
            updateFavCount();
            btn.classList.toggle('on', adding);
            btn.textContent = adding ? '♥' : '♡';
            el.classList.toggle('fav', adding);
            if (wpState.favOnly && !adding) el.remove();
            if (wpState.favOnly) {
              var left = wpGrid.querySelectorAll('.wp-item').length;
              if (!left) wpGrid.innerHTML = '<div class="wp-empty">No favorites yet. Tap ♡ on any wallpaper.</div>';
              if (wpPageInfo) wpPageInfo.textContent = left + ' favorite(s)';
            }
            window.toast(adding ? 'Added to favorites' : 'Removed from favorites', 'success');
          }
        })
        .catch(function () { window.toast('Favorite update failed', 'error'); });
    }

    function applyWp(btn) {
      var item = currentItem(btn);
      if (bgUrl) bgUrl.value = item.full;
      if (bgType) bgType.value = 'image';
      if (bgSource) bgSource.value = 'none';
      renderBg(item.full, 'image');
      scheduleSave({ background_url: item.full, background_type: 'image', background_source: 'none' });
      window.toast('Wallpaper applied — auto saved', 'success');
    }

    if (wpBrowseBtn) wpBrowseBtn.addEventListener('click', function () { openWp('browse'); });
    if (wpFavBtn) wpFavBtn.addEventListener('click', function () { openWp('favs'); });
    if (wpCat) wpCat.addEventListener('change', function () { wpState.category = wpCat.value; wpState.page = 1; loadWp(); });
    if (wpSearch) wpSearch.addEventListener('keydown', function (e) { if (e.key === 'Enter') runWpSearch(); });
    if (wpSearchBtn) wpSearchBtn.addEventListener('click', runWpSearch);
    if (wpClearBtn) wpClearBtn.addEventListener('click', function () {
      wpState.q = '';
      wpState.category = 'all';
      wpState.page = 1;
      if (wpSearch) wpSearch.value = '';
      if (wpCat) wpCat.value = 'all';
      loadWp();
    });
    function runWpSearch() {
      wpState.q = (wpSearch && wpSearch.value ? wpSearch.value : '').trim();
      wpState.page = 1;
      loadWp();
    }
    if (wpPrev) wpPrev.addEventListener('click', function () {
      if (wpState.favOnly || wpState.page <= 1) return;
      wpState.page -= 1;
      loadWp();
    });
    if (wpNext) wpNext.addEventListener('click', function () {
      if (wpState.favOnly || !wpState.hasNext) return;
      wpState.page += 1;
      loadWp();
    });

    fetch('/admin/wallpapers?fav=1', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok) {
          wpFavs = {};
          (j.favs || []).forEach(function (f) { wpFavs[f.id] = f; });
          updateFavCount();
        }
      })
      .catch(function () { /* noop */ });
  });
})();
