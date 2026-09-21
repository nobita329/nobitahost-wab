<?php
/**
 * Ported from views/pages/admin/users.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="card table-card">
  <div class="card-head">
    <h2><svg class="ic"><use href="#i-users"/></svg> All Users</h2>
    <div class="head-actions">
      <form method="GET" action="/admin/users" class="search-form">
        <svg class="ic"><use href="#i-search"/></svg>
        <input type="text" name="q" placeholder="Search users..." value="<?= e($q ?: '') ?>">
      </form>
      <a class="btn btn-accent btn-sm" href="/admin/create"><svg class="ic"><use href="#i-user-plus"/></svg> Create User</a>
    </div>
  </div>
  <table>
    <thead>
      <tr><th>User</th><th>Role</th><th>Status</th><th>Owner</th><th>Last Login</th><th>Created</th><th class="ta-r">Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u) { ?>
      <tr>
        <td>
          <div class="t-user">
            <img class="avatar" src="<?= e($u->profile_pic ?: '/img/avatar.svg') ?>" alt="">
            <div><b><?= e($u->username) ?></b><small><?= e($u->email) ?></small></div>
          </div>
        </td>
        <td><span class="badge <?= e($u->role === 'admin' ? 'gold' : '') ?>"><?= e($u->role) ?></span></td>
        <td><span class="badge <?= e($u->status === 'active' ? 'green' : 'red') ?>"><?= e($u->status) ?></span></td>
        <td><?= e($u->owner ?: '—') ?></td>
        <td><?= e($u->last_login ? nh_slice(nh_replace($u->last_login, 'T', ' '), 0, 16) : '—') ?></td>
        <td><?= e(nh_slice(nh_replace($u->created_at, 'T', ' '), 0, 10)) ?></td>
        <td class="ta-r">
          <div class="row-actions">
            <button class="icon-btn" data-modal="#modalEdit<?= e($u->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></button>
            <?php if ($u->id !== $user->id) { ?>
            <form method="POST" action="/admin/users/<?= e($u->id) ?>/suspend" class="inline">
              <button class="icon-btn <?= e($u->status === 'suspended' ? 'ok' : 'warn') ?>" title="<?= e($u->status === 'suspended' ? 'Activate' : 'Suspend') ?>"><svg class="ic"><use href="#i-ban"/></svg></button>
            </form>
            <form method="POST" action="/admin/users/<?= e($u->id) ?>/delete" class="inline" data-confirm="Delete <?= e($u->username) ?>? This cannot be undone.">
              <button class="icon-btn danger" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
            </form>
            <?php } ?>
          </div>
        </td>
      </tr>
      <?php } ?>
      <?php if (!nh_count($users)) { ?>
      <tr><td colspan="7"><div class="empty">No users found.</div></td></tr>
      <?php } ?>
    </tbody>
  </table>
</div>

<?php foreach ($users as $u) { ?>
<div class="modal" id="modalEdit<?= e($u->id) ?>">
  <div class="modal-card">
    <div class="modal-head"><h3>Edit <?= e($u->username) ?></h3><button class="icon-btn" data-close=""><svg class="ic"><use href="#i-x"/></svg></button></div>
    <form method="POST" action="/admin/users/<?= e($u->id) ?>/edit" class="form">
      <div class="field"><label>Username</label><input type="text" name="username" value="<?= e($u->username) ?>" required></div>
      <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($u->email) ?>" required></div>
      <div class="field"><label>Role</label>
        <select name="role">
          <option value="user" <?= e($u->role === 'user' ? 'selected' : '') ?>>User</option>
          <option value="admin" <?= e($u->role === 'admin' ? 'selected' : '') ?>>Admin</option>
        </select>
      </div>
      <button class="btn btn-accent btn-block" type="submit">Save Changes</button>
    </form>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

