<?php
/**
 * Ported from views/partials/social-comment-item.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<div class="pc-item <?= e($depth > 0 ? 'pc-reply' : '') ?>">
  <img class="avatar sm" src="<?= e($c->profile_pic ?: '/img/avatar.svg') ?>" alt="">
  <div class="pc-main">
    <div class="pc-bubble">
      <div class="pc-head">
        <b class="pc-name"><?= e($c->username) ?></b>
        <?php if ($c->role === 'admin') { ?><span class="badge gold">admin</span><?php } ?>
        <span class="pc-time"><?= e($c->time_ago) ?></span>
        <?php if ($c->is_edited) { ?><span class="pc-edited">edited</span><?php } ?>
      </div>
      <div class="pc-content"><?= e($c->content) ?></div>
    </div>
    <div class="pc-actions">
      <?php foreach ($REACTION_TYPES as $t) {
           $r = null;
           for ($i = 0; $i < nh_count($c->reactions); $i++) if ($c->reactions[$i].type === $t) $r = $c->reactions[$i];
           $label = $RX_EMOJI[$t] + ($r ? ' ' . $r->count : '');
           if ($user) { ?>
        <form method="POST" action="/profile/comment/<?= e($c->id) ?>/react" class="inline">
          <input type="hidden" name="back" value="<?= e($pcBack) ?>">
          <input type="hidden" name="type" value="<?= e($t) ?>">
          <button type="submit" class="pc-react <?= e($r && $r->mine ? 'mine' : '') ?>"><?= e($label) ?></button>
        </form>
        <?php } else if ($r) { ?>
        <span class="pc-react static"><?= e($label) ?></span>
        <?php } ?>
      <?php } ?>
      <?php if ($user && $depth === 0) { ?>
      <details class="pc-details">
        <summary>Reply</summary>
        <form method="POST" action="/profile/<?= e($c->profile_user_id) ?>/comment" class="pc-inline-form">
          <input type="hidden" name="back" value="<?= e($pcBack) ?>">
          <input type="hidden" name="parent_id" value="<?= e($c->id) ?>">
          <textarea name="content" rows="2" maxlength="1000" minlength="3" placeholder="Reply to <?= e($c->username) ?>…" required></textarea>
          <button class="btn btn-sm btn-accent" type="submit">Reply</button>
        </form>
      </details>
      <?php } ?>
      <?php if ($user && $user->id === $c->author_id) { ?>
      <details class="pc-details">
        <summary>Edit</summary>
        <form method="POST" action="/profile/comment/<?= e($c->id) ?>/edit" class="pc-inline-form">
          <input type="hidden" name="back" value="<?= e($pcBack) ?>">
          <textarea name="content" rows="2" maxlength="1000" minlength="3" required><?= e($c->content) ?></textarea>
          <button class="btn btn-sm btn-accent" type="submit">Save</button>
        </form>
      </details>
      <?php } ?>
      <?php if ($user && ($user->id === $c->author_id ?: $user->role === 'admin' ?: $user->id === $c->profile_user_id)) { ?>
      <form method="POST" action="/profile/comment/<?= e($c->id) ?>/delete" class="inline" data-confirm="Delete this comment?">
        <input type="hidden" name="back" value="<?= e($pcBack) ?>">
        <button type="submit" class="pc-react danger" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
      </form>
      <?php } ?>
    </div>
    <?php foreach ($c->replies as $r) { ?>
      <?php partial('partials/social-comment-item', [ 'c' => $r, 'depth' => $depth + 1, 'pcBack' => $pcBack, 'profileUser' => $profileUser, 'RX_EMOJI' => $RX_EMOJI, 'REACTION_TYPES' => $REACTION_TYPES ]); ?>
    <?php } ?>
  </div>
</div>

<!-- EJS2PHP notes: arrow function left as-is -->
