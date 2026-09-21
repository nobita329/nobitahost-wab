<?php
/**
 * Ported from views/pages/user/team.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($query->created) { ?><div class="alert alert-success">Team member created.</div><?php } ?>
<?php if ($query->edited) { ?><div class="alert alert-success">Team member updated.</div><?php } ?>
<?php if ($query->deleted) { ?><div class="alert alert-success">Team member deleted.</div><?php } ?>
<?php if ($query->error === 'role') { ?><div class="alert alert-error">Role name is required.</div><?php } ?>
<?php if ($query->error === 'roleexists') { ?><div class="alert alert-error">That role already exists.</div><?php } ?>

<?php if ($user) { ?>
<div class="row">
  <div class="col-12">
    <div class="card profile-card me-card">
      <div class="profile-top">
        <span class="me-badge"><svg class="ic"><use href="#i-user"/></svg> This is you</span>
        <img class="avatar xl" src="<?= e($user->profile_pic ?: '/img/avatar.svg') ?>" alt="">
        <h3><?= e($user->username) ?></h3>
        <p><?= e($user->email) ?></p>
        <?php $myRoleName = $user->custom_role ?: ($user->role === 'admin' ? 'Admin' : 'Member'); ?>
        <?php $myRoleColor = $user->custom_color ?: ($user->role === 'admin' ? '#f59e0b' : '#8b5cf6'); ?>
        <span class="badge" style="background: <?= e($myRoleColor) ?>22; color: <?= e($myRoleColor) ?>; border: 1px solid <?= e($myRoleColor) ?>55;"><?= e($myRoleName) ?></span>
        <span class="badge <?= e($user->status === 'active' ? 'green' : 'red') ?>"><?= e($user->status) ?></span>
      </div>
      <div class="profile-stats">
        <div><b><?= e($user->two_factor_enabled ? 'On' : 'Off') ?></b><span>2FA</span></div>
        <div><b>#<?= e($user->id) ?></b><span>User ID</span></div>
        <div><b><?= e(nh_slice($user->created_at, 0, 10)) ?></b><span>Joined</span></div>
        <div><b><?= e(nh_count($members)) ?></b><span>Members</span></div>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-users"/></svg> Team Members</h2>
        <?php if ($isAdmin) { ?>
        <div class="head-actions">
          <button class="btn btn-accent btn-sm" data-modal="#modalTeam"><svg class="ic"><use href="#i-user-plus"/></svg> New Team Member</button>
        </div>
        <?php } ?>
      </div>
      <div class="card-body content-area"><?= $page->content ?></div>
    </div>
  </div>
</div>

<?php if ($members && nh_count($members)) { ?>
<div class="row">
  <div class="col-12">
    <div class="card table-card">
      <table>
        <thead>
          <tr><th>User</th><th>Role</th><th>Status</th><th>Last Login</th><th>Created</th><th class="ta-r">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m) { ?>
          <tr>
            <td>
              <div class="t-user">
                <img class="avatar" src="<?= e($m->profile_pic ?: '/img/avatar.svg') ?>" alt="">
                <div><b><?= e($m->username) ?></b><small><?= e($m->email) ?></small></div>
              </div>
            </td>
            <td>
              <?php $roleName = $m->custom_role ?: ($m->role === 'admin' ? 'Admin' : 'Member'); ?>
              <?php $roleColor = $m->custom_color ?: ($m->role === 'admin' ? '#f59e0b' : '#8b5cf6'); ?>
              <span class="badge" style="background: <?= e($roleColor) ?>22; color: <?= e($roleColor) ?>; border: 1px solid <?= e($roleColor) ?>55;"><?= e($roleName) ?></span>
            </td>
            <td><span class="badge <?= e($m->status === 'active' ? 'green' : 'red') ?>"><?= e($m->status) ?></span></td>
            <td><?= e($m->last_login ? nh_slice(nh_replace($m->last_login, 'T', ' '), 0, 16) : '—') ?></td>
            <td><?= e(nh_slice(nh_replace($m->created_at, 'T', ' '), 0, 10)) ?></td>
            <td class="ta-r">
              <div class="row-actions">
                <a class="icon-btn" href="/team/member/<?= e($m->id) ?>" title="Profile"><svg class="ic"><use href="#i-eye"/></svg></a>
                <?php if ($isAdmin) { ?>
                <button class="icon-btn" data-modal="#modalEdit<?= e($m->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></button>
                <?php if ($m->id !== $user->id) { ?>
                <form method="POST" action="/team/<?= e($m->id) ?>/suspend" class="inline">
                  <button class="icon-btn <?= e($m->status === 'suspended' ? 'ok' : 'warn') ?>" title="<?= e($m->status === 'suspended' ? 'Activate' : 'Suspend') ?>"><svg class="ic"><use href="#i-ban"/></svg></button>
                </form>
                <form method="POST" action="/team/<?= e($m->id) ?>/delete" class="inline" data-confirm="Delete <?= e($m->username) ?>?">
                  <button class="icon-btn danger" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
                </form>
                <?php } ?>
                <?php } ?>
              </div>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php } else { ?>
<div class="card"><div class="empty">No team members yet.</div></div>
<?php } ?>

<?php if ($isAdmin) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Manage Roles</h2></div>
      <div class="card-body">
        <form method="POST" action="/team/roles/create" class="form form-row">
          <div class="field"><label>New Role Name</label><input type="text" name="name" placeholder="e.g. CEO, Designer" required></div>
          <div class="field"><label>Color</label><input type="color" name="color" value="#3b82f6" style="height:42px;padding:4px"></div>
          <div class="field" style="display:flex;align-items:flex-end"><button class="btn btn-accent" type="submit">Add Role</button></div>
        </form>
        <div class="role-list">
          <?php foreach ($roles as $r) { ?>
          <div class="role-item">
            <span class="badge" style="background: <?= e($r->color) ?>22; color: <?= e($r->color) ?>; border: 1px solid <?= e($r->color) ?>55;"><?= e($r->name) ?></span>
            <div class="row-actions">
              <button class="icon-btn" data-modal="#modalRole<?= e($r->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></button>
              <form method="POST" action="/team/roles/<?= e($r->id) ?>/delete" class="inline" data-confirm="Delete role <?= e($r->name) ?>? Members with it lose the role.">
                <button class="icon-btn danger" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
              </form>
            </div>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="modalTeam">
  <div class="modal-card">
    <div class="modal-head"><h3>Add Team Member</h3><button class="icon-btn" data-close=""><svg class="ic"><use href="#i-x"/></svg></button></div>
    <form method="POST" action="/team/create" class="form">
      <div class="field"><label>Username</label><input type="text" name="username" required autocomplete="off"></div>
      <div class="field"><label>Email</label><input type="email" name="email" required></div>
      <div class="field"><label>Password</label><input type="password" name="password" placeholder="Min 6 characters" required></div>
      <div class="field"><label>Role</label>
        <select name="custom_role_id">
          <option value="">Default (Member)</option>
          <?php foreach ($roles as $r) { ?><option value="<?= e($r->id) ?>"><?= e($r->name) ?></option><?php } ?>
        </select>
      </div>
      <button class="btn btn-accent btn-block" type="submit">Create Member</button>
    </form>
  </div>
</div>

<?php foreach ($members as $m) { ?>
<div class="modal" id="modalEdit<?= e($m->id) ?>">
  <div class="modal-card">
    <div class="modal-head"><h3>Edit <?= e($m->username) ?></h3><button class="icon-btn" data-close=""><svg class="ic"><use href="#i-x"/></svg></button></div>
    <form method="POST" action="/team/<?= e($m->id) ?>/edit" class="form">
      <div class="field"><label>Username</label><input type="text" name="username" value="<?= e($m->username) ?>" required></div>
      <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($m->email) ?>" required></div>
      <div class="field"><label>Role</label>
        <select name="custom_role_id">
          <option value="">Default (Member)</option>
          <?php foreach ($roles as $r) { ?><option value="<?= e($r->id) ?>" <?= e($m->custom_role_id === $r->id ? 'selected' : '') ?>><?= e($r->name) ?></option><?php } ?>
        </select>
      </div>
      <button class="btn btn-accent btn-block" type="submit">Save Changes</button>
    </form>
  </div>
</div>
<?php } ?>

<?php foreach ($roles as $r) { ?>
<div class="modal" id="modalRole<?= e($r->id) ?>">
  <div class="modal-card">
    <div class="modal-head"><h3>Edit Role</h3><button class="icon-btn" data-close=""><svg class="ic"><use href="#i-x"/></svg></button></div>
    <form method="POST" action="/team/roles/<?= e($r->id) ?>/edit" class="form">
      <div class="field"><label>Role Name</label><input type="text" name="name" value="<?= e($r->name) ?>" required></div>
      <div class="field"><label>Color</label><input type="color" name="color" value="<?= e($r->color) ?>" style="height:42px;padding:4px;width:100%"></div>
      <button class="btn btn-accent btn-block" type="submit">Save Role</button>
    </form>
  </div>
</div>
<?php } ?>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

