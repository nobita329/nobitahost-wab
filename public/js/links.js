(function () {
  'use strict';

  var addBtn = document.getElementById('linkAddBtn');
  var addForm = document.getElementById('linkAddForm');
  if (addBtn && addForm) {
    addBtn.addEventListener('click', function () {
      var hidden = addForm.hasAttribute('hidden');
      addForm.toggleAttribute('hidden');
      if (!hidden) addForm.querySelector('input[name="title"]').focus();
    });
  }

  function syncPicker(targetId, value) {
    var target = document.getElementById(targetId);
    if (!target) return;
    target.value = value;
    var picker = document.querySelector('.icon-picker[data-target="' + targetId + '"]');
    if (!picker) return;
    picker.querySelectorAll('.icon-opt').forEach(function (btn) {
      btn.classList.toggle('active', btn.dataset.icon === value);
    });
  }

  document.querySelectorAll('.icon-picker').forEach(function (picker) {
    var targetId = picker.dataset.target;
    picker.querySelectorAll('.icon-opt').forEach(function (btn) {
      btn.addEventListener('click', function () {
        syncPicker(targetId, btn.dataset.icon);
      });
    });
  });

  document.querySelectorAll('[data-toggle-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = document.getElementById('linkEdit-' + btn.dataset.toggleEdit);
      if (form) form.toggleAttribute('hidden');
    });
  });

  document.querySelectorAll('.link-edit').forEach(function (form) {
    var targetId = form.querySelector('input[type="hidden"][name="icon"]').id;
    syncPicker(targetId, document.getElementById(targetId).value);
  });
})();
