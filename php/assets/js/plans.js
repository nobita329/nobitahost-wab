(function () {
  'use strict';

  var addBtn = document.getElementById('couponAddBtn');
  var addForm = document.getElementById('couponAddForm');
  if (addBtn && addForm) {
    addBtn.addEventListener('click', function () {
      addForm.toggleAttribute('hidden');
    });
  }
})();
