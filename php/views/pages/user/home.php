<?php
/**
 * Ported from views/pages/user/home.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-12">
    <div class="hero-card">
      <div class="hero-inner">
        <div>
          <span class="badge badge-accent">Welcome back<?= e($user ? ', ' . $user->username : '') ?> 🎉</span>
          <h1><?= $settings->panel_name ?> <span class="grad-text">Dashboard</span></h1>
          <p>Everything you need — <b>card based</b>, fast and fully customisable from <b>Settings</b>.</p>
        </div>
        <div class="hero-art">
          <span class="brand-logo brand-emoji xl"><?= e($settings->logo_emoji) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card">
      <div class="stat-icon accent1"><svg class="ic"><use href="#i-users"/></svg></div>
      <div class="stat-meta"><b><?= e($stats->team) ?></b><span>Team Members</span></div>
    </div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card">
      <div class="stat-icon accent2"><svg class="ic"><use href="#i-play"/></svg></div>
      <div class="stat-meta"><b><?= e($stats->tutorials) ?></b><span>Tutorials</span></div>
    </div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card">
      <div class="stat-icon accent3"><svg class="ic"><use href="#i-clock"/></svg></div>
      <div class="stat-meta"><b><?= e($stats->activity) ?></b><span>Activities</span></div>
    </div>
  </div>
  <div class="col-3 col-md-6 col-sm-12">
    <div class="card stat-card">
      <div class="stat-icon accent4"><svg class="ic"><use href="#i-shield"/></svg></div>
      <div class="stat-meta"><b><?= e($stats->online ? 'Online' : 'Guest') ?></b><span>Status</span></div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card combo-card">
      <div class="card-body combo-body">
        <div class="combo-side">
          <div class="combo-sec">
            <div class="combo-label">⏰ Live Digital Clock</div>
            <div class="clock-time" aria-label="Current time">
              <span id="clockHH">--</span><span class="clock-sep">:</span><span id="clockMM">--</span><span class="clock-sep">:</span><span id="clockSS">--</span>
            </div>
            <div class="clock-meta">
              <span class="clock-ampm" id="clockAMPM">--</span>
              <span class="clock-day" id="clockDay">—</span>
            </div>
          </div>
          <div class="combo-sec">
            <div class="combo-label">🌤 Weather · All India</div>
            <div class="weather-big">
              <span class="weather-emoji" id="weatherEmoji">⏳</span>
              <span class="weather-temp" id="weatherTemp">--°C</span>
            </div>
            <div class="weather-cond" id="weatherCond">Fetching weather…</div>
            <div class="weather-mini" id="weatherMini">💧 --% · 💨 -- km/h</div>
            <div class="weather-loc"><svg class="ic"><use href="#i-map-pin"/></svg> <span id="weatherLoc">Auto Detect · India</span></div>
          </div>
        </div>
        <div class="combo-side">
          <div class="combo-sec">
            <div class="combo-label">📅 Calendar</div>
            <div class="cal-date" id="calDate">—</div>
            <div class="cal-nav">
              <button type="button" class="icon-btn" id="calPrev" aria-label="Previous month"><svg class="ic"><use href="#i-chevron-left"/></svg></button>
              <span class="cal-month" id="calMonth">—</span>
              <button type="button" class="icon-btn" id="calNext" aria-label="Next month"><svg class="ic"><use href="#i-chevron-right"/></svg></button>
            </div>
            <div class="cal-grid" id="calGrid"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($settings->script_enabled === 'on' && $settings->script_command) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-terminal"/></svg> Automated Script</h2></div>
      <div class="card-body">
        <div class="script-head">
          <span class="badge badge-accent"><?= e($settings->script_badge ?: 'All In One CMD') ?></span>
          <span class="hint"><?= e($settings->script_description ?: 'Execute the master script to install all dependencies instantly.') ?></span>
        </div>
        <div class="cmd">
          <code><?= e($settings->script_command) ?></code>
          <button type="button" class="btn btn-accent btn-sm" data-copy="<?= e($settings->script_command) ?>" data-copied="Copied to clipboard!">COPY</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<?php if ($settings->discord_enabled === 'on' && $settings->discord_server_id) { ?>
<div class="row">
  <div class="col-6 col-md-12">
    <div class="card discord-card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-chat"/></svg> Discord Chat</h2></div>
      <div class="card-body discord-body">
        <widgetbot server="<?= e($settings->discord_server_id) ?>" channel="<?= e($settings->discord_channel ?: '') ?>" width="100%" height="100%" class="flex-1"></widgetbot>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-12">
    <div class="card discord-card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-users"/></svg> Server Members</h2></div>
      <div class="card-body discord-body">
        <iframe src="https://discord.com/widget?id=<?= e($settings->discord_server_id) ?>&theme=<?= e($settings->discord_theme ?: 'dark') ?>" width="100%" height="100%" allowtransparency="true" frameborder="0" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<div class="row">
  <div class="col-8 col-md-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-home"/></svg> Welcome Section</h2>
      </div>
      <div class="card-body">
        <div class="social-grid">
          <?php if ($social->youtube) { ?>
          <a class="social-card sc-yt" href="<?= e($social->youtube->url) ?>" target="_blank" rel="noopener">
            <span class="sc-left"><span class="sc-icon"><svg class="ic"><use href="#i-play"/></svg></span><span class="sc-name">YouTube</span></span>
            <span class="sc-meta"><b><?= e($social->youtube->subs != null ? nh_num($social->youtube->subs) : '—') ?></b><small>Subscribers</small></span>
          </a>
          <?php } ?>
          <?php if ($social->instagram) { ?>
          <a class="social-card sc-ig" href="<?= e($social->instagram->url) ?>" target="_blank" rel="noopener">
            <span class="sc-left"><span class="sc-icon"><svg class="ic"><use href="#i-user"/></svg></span><span class="sc-name">Instagram</span></span>
            <span class="sc-meta"><b><?= e($social->instagram->handle) ?></b><small>Handle</small></span>
          </a>
          <?php } ?>
          <?php if ($social->github) { ?>
          <a class="social-card sc-gh" href="<?= e($social->github->url) ?>" target="_blank" rel="noopener">
            <span class="sc-left"><span class="sc-icon"><svg class="ic"><use href="#i-github"/></svg></span><span class="sc-name">GitHub</span></span>
            <span class="sc-meta"><b><?= e($social->github->repos) ?></b><small>Repos</small></span>
          </a>
          <?php } ?>
          <?php if ($social->discord && $social->discord->online != null) { ?>
          <a class="social-card sc-dc" href="https://discord.com" target="_blank" rel="noopener">
            <span class="sc-left"><span class="sc-icon"><svg class="ic"><use href="#i-chat"/></svg></span><span class="sc-name">Discord</span></span>
            <span class="sc-meta"><b><?= e($social->discord->online) ?></b><small>Members Online</small></span>
          </a>
          <?php } ?>
        </div>
        <div class="content-area"><?= $page->content ?></div>
      </div>
    </div>
  </div>
  <div class="col-4 col-md-12">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-info"/></svg> Quick Actions</h2></div>
      <div class="card-body">
        <a class="list-action" href="/tutorials"><svg class="ic"><use href="#i-book"/></svg> Browse Tutorials <span>→</span></a>
        <a class="list-action" href="/team"><svg class="ic"><use href="#i-users"/></svg> Manage Team <span>→</span></a>
        <a class="list-action" href="/analytics"><svg class="ic"><use href="#i-chart"/></svg> View Analytics <span>→</span></a>
        <a class="list-action" href="/command"><svg class="ic"><use href="#i-terminal"/></svg> Command Center <span>→</span></a>
        <?php if ($user && $user->role === 'admin') { ?>
        <a class="list-action" href="/admin/settings"><svg class="ic"><use href="#i-sliders"/></svg> Panel Settings <span>→</span></a>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<?php if ($blogPosts && nh_count($blogPosts)) { ?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-head">
        <h2><svg class="ic"><use href="#i-book"/></svg> From the blog</h2>
        <a class="btn btn-sm" href="/blog">See more →</a>
      </div>
      <div class="card-body blog-widget">
        <?php foreach ($blogPosts as $p) { ?>
        <a class="bw-item" href="/blog/<?= e($p->slug) ?>">
          <span class="bw-title"><b><?= e($p->title) ?></b><small><?= e($p->excerpt) ?></small></span>
          <span class="hint bw-date"><svg class="ic"><use href="#i-calendar"/></svg> <?= e(nh_locale_date($p->published_at, 'M j')) ?></span>
        </a>
        <?php } ?>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>


<!-- EJS2PHP notes: arrow function left as-is -->
