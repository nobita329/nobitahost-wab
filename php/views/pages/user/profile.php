<?php
/**
 * Ported from views/pages/user/profile.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php $saved = $query->saved; $pw = $query->password; $tfaOn = $query->{'2fa'} === 'on'; $tfaOff = $query->{'2fa'} === 'off'; ?>
<?php if ($saved) { ?><div class="alert alert-success">Profile updated successfully.</div><?php } ?>
<?php if ($pw) { ?><div class="alert alert-success">Password changed successfully.</div><?php } ?>
<?php if ($tfaOn) { ?><div class="alert alert-success">Two-factor authentication enabled.</div><?php } ?>
<?php if ($tfaOff) { ?><div class="alert alert-info">Two-factor authentication disabled.</div><?php } ?>
<?php if ($query->error === 'current') { ?><div class="alert alert-error">Current password is incorrect.</div><?php } ?>
<?php if ($query->error === 'short') { ?><div class="alert alert-error">New password must be at least 6 characters.</div><?php } ?>
<?php if ($query->error === 'match') { ?><div class="alert alert-error">Passwords do not match.</div><?php } ?>
<?php if ($query->error === '2fa-code' ?: $query->error === '2fa-invalid') { ?><div class="alert alert-error">Invalid 2FA verification code.</div><?php } ?>

<div class="row">
  <div class="col-4 col-md-12">
    <div class="card profile-card">
      <div class="profile-top">
        <img class="avatar xl" src="<?= e($user->profile_pic ?: '/img/avatar.svg') ?>" alt="">
        <h3><?= e($user->username) ?></h3>
        <p><?= e($user->email) ?></p>
        <span class="badge <?= e($user->role === 'admin' ? 'gold' : '') ?>"><?= e($user->role) ?></span>
        <span class="badge <?= e($user->status === 'active' ? 'green' : 'red') ?>"><?= e($user->status) ?></span>
      </div>
      <div class="profile-stats">
        <div><b><?= e($user->two_factor_enabled ? 'On' : 'Off') ?></b><span>2FA</span></div>
        <div><b>#<?= e($user->id) ?></b><span>User ID</span></div>
        <div><b><?= e(nh_slice($user->created_at, 0, 10)) ?></b><span>Joined</span></div>
      </div>
      <div class="card-body">
        <p class="bio-text"><?= e($user->bio ?: 'No bio yet.') ?></p>
      </div>
    </div>
  </div>

  <div class="col-8 col-md-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-pencil"/></svg> Edit Profile</h2></div>
      <div class="card-body">
        <form method="POST" action="/profile" enctype="multipart/form-data" class="form">
          <input type="hidden" name="existing_pic" value="<?= e($user->profile_pic) ?>">
          <div class="row">
            <div class="col-6 col-sm-12">
              <div class="field"><label>Profile Picture</label><input type="file" name="profile_pic" accept="image/*"></div>
            </div>
            <div class="col-6 col-sm-12">
              <div class="field"><label>Bio</label>
                <textarea name="bio" rows="4" placeholder="Tell us about yourself"><?= e($user->bio ?: '') ?></textarea>
              </div>
            </div>
          </div>
          <button class="btn btn-accent" type="submit">Save Profile</button>
        </form>
      </div>
    </div>

    <?php if ($user->is_demo) { ?>
    <div class="card">
      <div class="card-body">
        <div class="alert alert-info"><svg class="ic"><use href="#i-shield"/></svg> This is the <b>demo account</b>. Password is locked and cannot be changed.</div>
      </div>
    </div>
    <?php } else { ?>
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Change Password</h2></div>
      <div class="card-body">
        <form method="POST" action="/profile/password" class="form">
          <div class="row">
            <div class="col-4 col-sm-12"><div class="field"><label>Current Password</label><input type="password" name="current" required></div></div>
            <div class="col-4 col-sm-12"><div class="field"><label>New Password</label><input type="password" name="password" required></div></div>
            <div class="col-4 col-sm-12"><div class="field"><label>Confirm</label><input type="password" name="confirm" required></div></div>
          </div>
          <button class="btn btn-accent" type="submit">Update Password</button>
        </form>
      </div>
    </div>
    <?php } ?>

    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-lock"/></svg> Two-Factor Authentication</h2></div>
      <div class="card-body">
        <?php if ($user->two_factor_enabled) { ?>
          <p>2FA is <b class="green-text">enabled</b> on your account.</p>
          <form method="POST" action="/profile/2fa/disable" class="inline" data-confirm="Disable 2FA?">
            <button class="btn btn-danger">Disable 2FA</button>
          </form>
        <?php } else { ?>
          <p>Add an extra layer of security to your account with an authenticator app (Google Authenticator, Authy, etc).</p>
          <a class="btn btn-accent" href="/profile/2fa/setup">Setup 2FA</a>
        <?php } ?>
      </div>
    </div>
    <div class="card" id="cloudflare">
      <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Cloudflare</h2></div>
      <div class="card-body">
        <?php if ($query->cfsaved) { ?><div class="alert alert-success">Token added.</div><?php } ?>
        <?php if ($query->cfdeleted) { ?><div class="alert alert-success">Token removed.</div><?php } ?>
        <?php if ($query->cflimit) { ?><div class="alert alert-error">Limit reached — max 5 tokens.</div><?php } ?>
        <?php if ($query->cferror) { ?><div class="alert alert-error">Something went wrong.</div><?php } ?>
        <p>Add your Cloudflare API token to use Cloudflare-powered features. Tokens are stored securely and always shown masked.</p>
        <?php if (nh_count($cfTokens)) { ?>
        <div class="table-card">
          <table>
            <thead><tr><th>Label</th><th>Token</th><th>Added</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($cfTokens as $t) { ?>
              <tr>
                <td><?= e($t->label ?: '—') ?></td>
                <td class="mono"><?= e($t->token) ?></td>
                <td><?= e(nh_slice($t->created_at, 0, 10)) ?></td>
                <td class="ta-r">
                  <form method="POST" action="/profile/cf-token/<?= e($t->id) ?>/delete" class="inline" data-confirm="Remove this token?">
                    <button class="icon-btn danger" title="Remove"><svg class="ic"><use href="#i-trash"/></svg></button>
                  </form>
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <?php } else { ?>
        <p class="hint center">No tokens yet.</p>
        <?php } ?>
        <form method="POST" action="/profile/cf-token" class="row cf-add-row">
          <div class="col-4 col-md-6 col-sm-12"><input type="text" name="label" maxlength="60" placeholder="Label (e.g. Personal Zone)"></div>
          <div class="col-6 col-md-6 col-sm-12"><input type="password" name="token" maxlength="200" placeholder="Paste API token" required></div>
          <div class="col-2 col-md-12 col-sm-12"><button class="btn btn-accent btn-block" type="submit"><svg class="ic"><use href="#i-plus"/></svg> Add</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php partial('partials/social-comments', [ 'profileUser' => $user, 'comments' => $comments, 'commentsTotal' => $commentsTotal, 'back' => '/profile' ]); ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: arrow function left as-is -->
