<?php
/**
 * Ported from views/pages/user/member-profile.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="card profile-card member-card">
      <div class="profile-top">
        <img class="avatar xl" src="<?= e($member->profile_pic ?: '/img/avatar.svg') ?>" alt="">
        <h3><?= e($member->username) ?></h3>
        <p><?= e($member->email) ?></p>
        <span class="badge <?= e($member->role === 'admin' ? 'gold' : '') ?>"><?= e($member->role) ?></span>
        <span class="badge <?= e($member->status === 'active' ? 'green' : 'red') ?>"><?= e($member->status) ?></span>
      </div>
      <div class="profile-stats">
        <div><b>#<?= e($member->id) ?></b><span>User ID</span></div>
        <div><b><?= e($member->last_login ? nh_slice(nh_replace($member->last_login, 'T', ' '), 0, 16) : '—') ?></b><span>Last Login</span></div>
        <div><b><?= e(nh_slice($member->created_at, 0, 10)) ?></b><span>Joined</span></div>
      </div>
      <div class="card-body">
        <p class="bio-text"><?= e($member->bio ?: 'No bio yet.') ?></p>
      </div>
    </div>
  </div>
</div>

<?php partial('partials/social-comments', [ 'profileUser' => $member, 'comments' => $comments, 'commentsTotal' => $commentsTotal, 'back' => '/team/member/' . $member->id ]); ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: arrow function left as-is -->
