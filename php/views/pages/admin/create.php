<?php
/**
 * Ported from views/pages/admin/create.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-6 col-md-10 col-sm-12" style="margin:0 auto">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-user-plus"/></svg> Create New User</h2></div>
      <div class="card-body">
        <?php if ($error) { ?><div class="alert alert-error"><?= e($error) ?></div><?php } ?>
        <?php if ($success) { ?><div class="alert alert-success"><?= e($success) ?></div><?php } ?>
        <form method="POST" action="/admin/create" class="form">
          <div class="row">
            <div class="col-6 col-sm-12"><div class="field"><label>Username</label><input type="text" name="username" required autofocus></div></div>
            <div class="col-6 col-sm-12"><div class="field"><label>Email</label><input type="email" name="email" required></div></div>
          </div>
          <div class="row">
            <div class="col-6 col-sm-12"><div class="field"><label>Password</label><input type="password" name="password" placeholder="Min 6 characters" required></div></div>
            <div class="col-6 col-sm-12"><div class="field"><label>Role</label>
              <select name="role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select>
            </div></div>
          </div>
          <button class="btn btn-accent" type="submit"><svg class="ic"><use href="#i-user-plus"/></svg> Create User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

