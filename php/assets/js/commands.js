(function () {
  var DATA = window.__CMD_DATA || [];
  var CATS = [
    { key: 'all', label: 'All Commands' },
    { key: 'useful', label: 'Useful' },
    { key: 'linux', label: 'Linux' },
    { key: 'vps', label: 'VPS' },
    { key: 'docker', label: 'Docker' },
    { key: 'git', label: 'Git' },
    { key: 'networking', label: 'Networking' },
    { key: 'system', label: 'System' },
    { key: 'auto', label: 'Auto' },
    { key: 'review', label: 'Review' },
    { key: 'suggestion', label: 'Suggestion' }
  ];
  var PAGE_SIZE = 100;
  var activeCat = 'all';
  var searchTerm = '';
  var rendered = 0;
  var filtered = [];

  var tabsEl = document.getElementById('cmdTabs');
  var listEl = document.getElementById('cmdList');
  var searchEl = document.getElementById('cmdSearch');
  var loadEl = document.getElementById('cmdLoadWrap');
  var loadBtn = document.getElementById('cmdLoad');
  var totalEl = document.getElementById('cmdTotal');

  if (!tabsEl || !listEl) return;

  function countFor(key) {
    if (key === 'all') return DATA.length;
    var n = 0;
    for (var i = 0; i < DATA.length; i++) if (DATA[i].cat === key) n++;
    return n;
  }

  if (totalEl) totalEl.textContent = DATA.length.toLocaleString() + '+ commands';

  function renderTabs() {
    tabsEl.innerHTML = '';
    CATS.forEach(function (cat) {
      var btn = document.createElement('button');
      btn.className = 'cmd-tab' + (cat.key === activeCat ? ' active' : '');
      btn.type = 'button';
      btn.dataset.cat = cat.key;
      var span = document.createElement('span');
      span.textContent = cat.label;
      var badge = document.createElement('em');
      badge.textContent = countFor(cat.key).toLocaleString();
      btn.appendChild(span);
      btn.appendChild(badge);
      btn.addEventListener('click', function () {
        activeCat = cat.key;
        rendered = 0;
        filtered = null;
        renderTabs();
        renderList(true);
      });
      tabsEl.appendChild(btn);
    });
  }

  function applyFilter() {
    var term = searchTerm.toLowerCase().trim();
    var all = [];
    for (var i = 0; i < DATA.length; i++) {
      var c = DATA[i];
      if (activeCat !== 'all' && c.cat !== activeCat) continue;
      if (term) {
        if (c.cmd.toLowerCase().indexOf(term) === -1 && (c.desc || '').toLowerCase().indexOf(term) === -1) continue;
      }
      all.push(c);
    }
    return all;
  }

  function renderList(reset) {
    if (reset || filtered === null) filtered = applyFilter();
    var remaining = filtered.length - rendered;
    var toShow = Math.min(PAGE_SIZE, remaining);
    var frag = document.createDocumentFragment();
    for (var i = rendered; i < rendered + toShow; i++) {
      frag.appendChild(buildItem(filtered[i]));
    }
    rendered += toShow;
    listEl.appendChild(frag);
    var left = filtered.length - rendered;
    if (left > 0) {
      loadBtn.textContent = 'Load more commands (' + left.toLocaleString() + ' left)';
      loadEl.style.display = '';
    } else {
      loadEl.style.display = 'none';
    }
    if (!filtered.length) {
      listEl.innerHTML = '<div class="empty">No commands match your search.</div>';
    }
  }

  function buildItem(c) {
    var item = document.createElement('div');
    item.className = 'cmd-item';
    var code = document.createElement('code');
    code.textContent = c.cmd;
    var meta = document.createElement('div');
    meta.className = 'cmd-meta';
    if (c.desc) {
      var d = document.createElement('span');
      d.className = 'cmd-desc';
      d.textContent = c.desc;
      meta.appendChild(d);
    }
    var cat = document.createElement('span');
    cat.className = 'badge cmd-cat';
    cat.textContent = c.cat;
    meta.appendChild(cat);
    var copy = document.createElement('button');
    copy.type = 'button';
    copy.className = 'icon-btn cmd-copy';
    copy.dataset.cc = c.cmd;
    copy.title = 'Copy command';
    copy.innerHTML = '<svg class="ic"><use href="#i-copy"/></svg>';
    item.appendChild(copy);
    item.appendChild(code);
    item.appendChild(meta);
    return item;
  }

  var debounce;
  searchEl.addEventListener('input', function () {
    clearTimeout(debounce);
    debounce = setTimeout(function () {
      searchTerm = searchEl.value;
      rendered = 0;
      filtered = null;
      listEl.innerHTML = '';
      renderList();
    }, 150);
  });

  loadBtn.addEventListener('click', function () {
    renderList();
  });

  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(ta);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-cc]') : null;
    if (!btn || !listEl.contains(btn)) return;
    var text = btn.getAttribute('data-cc');
    var old = btn.innerHTML;
    var done = function () {
      btn.innerHTML = '<span style="font-size:12px">✓</span>';
      setTimeout(function () { btn.innerHTML = old; }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
    } else { fallbackCopy(text, done); }
  });

  renderTabs();
  renderList(true);
})();
