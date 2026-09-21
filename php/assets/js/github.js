(function () {
  'use strict';

  var addBtn = document.getElementById('githubAddBtn');
  var addForm = document.getElementById('githubAddForm');
  if (addBtn && addForm) {
    addBtn.addEventListener('click', function () {
      var hidden = addForm.hasAttribute('hidden');
      addForm.toggleAttribute('hidden');
      if (!hidden) addForm.querySelector('input[name="username"]').focus();
    });
  }

  document.querySelectorAll('[data-toggle-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = document.getElementById('githubEdit-' + btn.dataset.toggleEdit);
      if (form) form.toggleAttribute('hidden');
    });
  });
})();
