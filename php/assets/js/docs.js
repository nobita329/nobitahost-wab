(function () {
  'use strict';

  var addBtn = document.getElementById('docAddBtn');
  var addForm = document.getElementById('docAddForm');
  if (addBtn && addForm) {
    addBtn.addEventListener('click', function () {
      var hidden = addForm.hasAttribute('hidden');
      addForm.toggleAttribute('hidden');
      if (!hidden) addForm.querySelector('input[name="title"]').focus();
    });
  }

  document.querySelectorAll('[data-toggle-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = document.getElementById('docEdit-' + btn.dataset.toggleEdit);
      if (row) row.hidden = !row.hidden;
    });
  });

  var checkAll = document.getElementById('checkAll');
  var checks = document.querySelectorAll('.doc-check');
  var bulkBtn = document.getElementById('bulkDeleteBtn');
  var bulkIds = document.getElementById('bulkIds');

  function updateBulk() {
    var selected = [];
    checks.forEach(function (c) { if (c.checked) selected.push(c.value); });
    if (bulkIds) bulkIds.value = selected.join(',');
    if (bulkBtn) bulkBtn.disabled = selected.length === 0;
  }

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      checks.forEach(function (c) { c.checked = checkAll.checked; });
      updateBulk();
    });
  }
  checks.forEach(function (c) {
    c.addEventListener('change', function () {
      if (checkAll) checkAll.checked = checks.length === document.querySelectorAll('.doc-check:checked').length;
      updateBulk();
    });
  });
})();
