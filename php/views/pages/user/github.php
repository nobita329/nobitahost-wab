<?php
/**
 * Ported from views/pages/user/github.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-github"/></svg> GitHub</h2></div>
      <div class="card-body content-area"><?= $page->content ?></div>
    </div>
  </div>
</div>

<?php if ($isAdmin) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-pencil"/></svg> Manage GitHub Users</h2>
        <button class="btn btn-accent btn-sm" type="button" id="githubAddBtn"><svg class="ic"><use href="#i-plus"/></svg> Add GitHub User</button>
      </div>
      <div class="card-body">
        <?php if ($query->saved) { ?><div class="alert alert-success">GitHub user saved.</div><?php } ?>
        <?php if ($query->deleted) { ?><div class="alert alert-success">GitHub user deleted.</div><?php } ?>
        <?php if ($query->error === 'username') { ?><div class="alert alert-error">Username is required.</div><?php } ?>
        <form method="POST" action="/github/add" class="form" id="githubAddForm" hidden>
          <div class="form-row">
            <div class="field"><label>GitHub Username</label><input type="text" name="username" required placeholder="e.g. nobita"></div>
            <div class="field"><label>Profile URL</label><input type="url" name="url" placeholder="https://github.com/…"></div>
          </div>
          <div class="field"><label>Note <small>(optional)</small></label><input type="text" name="note" placeholder="e.g. Founder & Lead Developer"></div>
          <button class="btn btn-accent" type="submit">Save GitHub User</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<?php if ($isAdmin) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-github"/></svg> GitHub API Settings</h2></div>
      <div class="card-body">
        <?php if ($query->apikey) { ?><div class="alert alert-success">GitHub API settings saved.</div><?php } ?>
        <form method="POST" action="/github/api-settings" class="form">
          <div class="form-row">
            <div class="field"><label>Main GitHub Username <small>(auto-detect repos)</small></label>
              <input type="text" name="github_username" value="<?= e($ghConfigured ? $settings->github_username : '') ?>" placeholder="e.g. nobita329"></div>
            <div class="field"><label>Personal Access Token <small>(optional, higher rate limit)</small></label>
              <input type="password" name="github_token" placeholder="ghp_… (leave blank to keep current)" autocomplete="off"></div>
          </div>
          <button class="btn btn-accent" type="submit">Save API Settings</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<div class="row">
  <?php foreach (($github ?: []) as $g) { ?>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="github-card-wrap">
      <a class="github-card" href="<?= e($g->url ?: 'https://github.com/' . $g->username) ?>" target="_blank" rel="noopener">
        <img class="github-avatar" src="https://github.com/<?= e($g->username) ?>.png?size=120" alt="<?= e($g->username) ?>" loading="lazy" onerror="this.outerHTML='<span class=&quot;github-avatar gh-fallback&quot;><svg class=&quot;ic&quot;><use href=&quot;#i-github&quot;/></svg></span>'">
        <div class="github-meta">
          <h3>@<?= e($g->username) ?></h3>
          <?php if ($g->note) { ?><p><?= e($g->note) ?></p><?php } ?>
          <span class="github-follow">Follow <svg class="ic"><use href="#i-chevron-right"/></svg></span>
        </div>
      </a>
      <?php if ($isAdmin) { ?>
      <div class="link-actions">
        <button class="icon-btn" type="button" data-toggle-edit="<?= e($g->id) ?>" title="Edit"><svg class="ic"><use href="#i-pencil"/></svg></button>
        <form method="POST" action="/github/<?= e($g->id) ?>/delete" onsubmit="return confirm('Delete this GitHub user?')">
          <button class="icon-btn danger" type="submit" title="Delete"><svg class="ic"><use href="#i-trash"/></svg></button>
        </form>
      </div>
      <form method="POST" action="/github/<?= e($g->id) ?>/edit" class="link-edit" id="githubEdit-<?= e($g->id) ?>" hidden>
        <div class="field"><label>Username</label><input type="text" name="username" value="<?= e($g->username) ?>"></div>
        <div class="field"><label>Profile URL</label><input type="url" name="url" value="<?= e($g->url) ?>"></div>
        <div class="field"><label>Note</label><input type="text" name="note" value="<?= e($g->note) ?>"></div>
        <button class="btn btn-accent btn-sm" type="submit">Save</button>
        <button class="btn btn-ghost btn-sm" type="button" data-toggle-edit="<?= e($g->id) ?>">Cancel</button>
      </form>
      <?php } ?>
    </div>
  </div>
  <?php } ?>
</div>

<?php if (!$github ?: !nh_count($github)) { ?>
<div class="card"><div class="empty">No GitHub users yet. <?php if ($isAdmin) { ?>Click <b>Add GitHub User</b> above to add one.<?php } else { ?>Check back soon.<?php } ?></div></div>
<?php } ?>

<?php if ($ghConfigured) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-github"/></svg> GitHub Stats <span class="hint">live</span></h2></div>
      <div class="card-body">
        <?php if ($stats) { ?>
        <div class="gh-stats">
          <div class="gh-stat"><span>⭐ Stars</span><b><?= e($stats->stars) ?></b></div>
          <div class="gh-stat"><span>⑂ Forks</span><b><?= e($stats->forks) ?></b></div>
          <div class="gh-stat"><span>👀 Watchers</span><b><?= e($stats->watchers) ?></b></div>
          <div class="gh-stat"><span>⚠️ Open Issues</span><b><?= e($stats->issues) ?></b></div>
          <div class="gh-stat"><span>📁 Repos</span><b><?= e($stats->repos) ?></b></div>
          <div class="gh-stat"><span>👥 Followers</span><b><?= e($stats->followers) ?></b></div>
        </div>
        <?php } else if ($statsError) { ?>
        <div class="alert alert-error">Could not fetch GitHub stats — <?= e($statsError) ?></div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-github"/></svg> Repositories <span class="hint">auto-detected</span></h2></div>
      <div class="card-body">
        <?php foreach (($reposByUser ?: []) as $u) { ?>
        <?php if ($u->error) { ?>
        <div class="alert alert-error">Could not fetch repos for <b>@<?= e($u->username) ?></b> — <?= e($u->error) ?></div>
        <?php } else if (nh_count($u->repos)) { ?>
        <div class="repo-user">
          <img class="repo-avatar" src="https://github.com/<?= e($u->username) ?>.png?size=60" alt="" loading="lazy">
          <div><b>@<?= e($u->username) ?></b><span class="hint"><?= e(nh_count($u->repos)) ?> repositories</span></div>
        </div>
        <div class="repo-grid">
          <?php foreach ($u->repos as $r) { ?>
          <a class="repo-card" href="<?= e($r->html_url) ?>" target="_blank" rel="noopener">
            <h3><svg class="ic"><use href="#i-folder"/></svg> <?= e($r->name) ?></h3>
            <?php if ($r->description) { ?><p><?= e($r->description) ?></p><?php } ?>
            <div class="repo-meta">
              <?php if ($r->language) { ?><span class="badge"><?= e($r->language) ?></span><?php } ?>
              <span>⭐ <?= e($r->stars) ?></span>
              <span>⑂ <?= e($r->forks) ?></span>
            </div>
          </a>
          <?php } ?>
        </div>
        <?php } ?>
        <?php } ?>
        <?php
          $noRepos = true;
          foreach ((array) ($reposByUser ?? []) as $ru) {
            if (nh_count($ru->repos ?? []) || !empty($ru->error)) { $noRepos = false; break; }
          }
        ?>
        <?php if ($noRepos) { ?>
        <div class="empty">No repositories found.</div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: anonymous function rewritten — check `use (...)` captures -->
