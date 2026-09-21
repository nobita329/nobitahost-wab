<?php
/**
 * Ported from views/partials/social-comments.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php
$query = isset($query) ? nh_obj($query) : nh_obj([]);
$locals = nh_obj(['query' => $query]);
$RX_EMOJI = [ 'like' => '👍', 'love' => '❤️', 'laugh' => '😂' ];
$REACTION_TYPES = ['like', 'love', 'laugh'];
$pcBack = isset($back) ? $back : '/profile'; ?>
<div class="card pc-card" id="comments">
  <div class="card-head"><h2><svg class="ic"><use href="#i-chat"/></svg> Comments <span class="pc-count"><?= e($commentsTotal) ?></span></h2></div>
  <div class="card-body">
    <?php if ($user) { ?>
    <form method="POST" action="/profile/<?= e($profileUser->id) ?>/comment" class="pc-form">
      <input type="hidden" name="back" value="<?= e($pcBack) ?>">
      <img class="avatar sm" src="<?= e($user->profile_pic ?: '/img/avatar.svg') ?>" alt="">
      <div class="pc-form-main">
        <textarea name="content" rows="3" maxlength="1000" minlength="3" placeholder="Write a comment…" required></textarea>
        <div class="pc-form-actions"><button class="btn btn-accent btn-sm" type="submit">Post Comment</button></div>
      </div>
    </form>
    <?php if ($locals->query && $locals->query->social_error === 'len') { ?><p class="pc-error">Comment must be 3–1000 characters.</p><?php } ?>
    <?php } else { ?>
    <p class="hint center"><a href="/login">Login</a> to join the conversation.</p>
    <?php } ?>

    <div class="pc-list">
      <?php if (!nh_count($comments)) { ?>
        <p class="pc-empty">No comments yet. Be the first!</p>
      <?php } ?>
      <?php foreach ($comments as $c) { ?>
        <?php partial('partials/social-comment-item', [ 'c' => $c, 'depth' => 0, 'pcBack' => $pcBack, 'profileUser' => $profileUser, 'RX_EMOJI' => $RX_EMOJI, 'REACTION_TYPES' => $REACTION_TYPES ]); ?>
      <?php } ?>
    </div>
  </div>
</div>

<!-- EJS2PHP notes: arrow function left as-is -->
