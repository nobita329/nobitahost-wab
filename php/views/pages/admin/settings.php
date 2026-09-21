<?php
/**
 * Ported from views/pages/admin/settings.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<?php if ($saved) { ?><div class="alert alert-success">Settings saved successfully.</div><?php } ?>

<div class="row">
  <div class="col-12">
    <div class="hero-card small">
      <div class="hero-inner">
        <div>
          <span class="badge badge-gold">Admin Only</span>
          <h1>Panel <span class="grad-text">Settings</span></h1>
          <p>Customise branding, background, music, appearance, email and behaviour.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<form method="POST" action="/admin/settings" enctype="multipart/form-data" id="settingsForm">
  <div class="row">

    <div class="col-6 col-md-12">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-sliders"/></svg> General</h2></div>
        <div class="card-body">
          <div class="field"><label>Panel Name</label><input type="text" name="panel_name" value="<?= e($settings->panel_name) ?>" data-live="panelName"></div>
          <div class="field"><label>Panel Tagline</label><input type="text" name="panel_tagline" value="<?= e($settings->panel_tagline) ?>"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-file"/></svg> Logo & Branding</h2></div>
        <div class="card-body">
          <div class="field"><label>Logo Type</label>
            <select name="logo_type" data-live="logoType">
              <option value="emoji" <?= e($settings->logo_type === 'emoji' ? 'selected' : '') ?>>Emoji</option>
              <option value="url" <?= e($settings->logo_type === 'url' ? 'selected' : '') ?>>Image / URL</option>
            </select>
          </div>
          <div class="field" id="logoEmojiField"><label>Logo Emoji</label><input type="text" name="logo_emoji" value="<?= e($settings->logo_emoji) ?>" data-live="logoEmoji"></div>
          <div class="field" id="logoUrlField">
            <label>Logo URL <small>(or upload)</small></label>
            <input type="text" name="logo_url" value="<?= e($settings->logo_url) ?>" placeholder="https://... or /uploads/..." data-live="logoUrl">
            <input type="file" name="logo_file" accept="image/*" class="mt-sm" data-preview="logoPreview">
          </div>
          <div class="preview-row">
            <span>Live preview</span>
            <div class="logo-preview" id="logoPreview"><?= e($settings->logo_type === 'url' && $settings->logo_url ? '<img src="' . $settings->logo_url . '">' : $settings->logo_emoji) ?></div>
          </div>

          <hr>
          <div class="field"><label>Favicon URL <small>(ico/png/svg)</small></label>
            <input type="text" name="favicon_url" value="<?= e($settings->favicon_url) ?>" placeholder="https://... or /uploads/..." data-live="faviconUrl">
            <input type="file" name="favicon_file" accept="image/*,.ico" class="mt-sm" data-preview="faviconPreview">
          </div>
          <div class="preview-row">
            <span>Favicon preview</span>
            <div class="favicon-preview" id="faviconPreview"><?php if ($settings->favicon_url) { ?><img src="<?= e($settings->favicon_url) ?>"><?php } else { ?><img src="/img/favicon.svg"><?php } ?></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-play"/></svg> Panel Music</h2></div>
        <div class="card-body">
          <div class="field"><label>Music Type</label>
            <select name="music_type" data-live="musicType">
              <option value="none" <?= e($settings->music_type === 'none' ? 'selected' : '') ?>>None</option>
              <option value="url" <?= e($settings->music_type === 'url' ? 'selected' : '') ?>>Audio URL / Upload</option>
              <option value="youtube" <?= e($settings->music_type === 'youtube' ? 'selected' : '') ?>>YouTube</option>
            </select>
          </div>
          <div class="field"><label>Music URL <small>(mp3/ogg/wav or YouTube link)</small></label>
            <input type="text" name="music_url" value="<?= e($settings->music_url) ?>" placeholder="https://..." data-live="musicUrl">
            <input type="file" name="music_file" accept="audio/*,.mp3,.wav,.ogg,.m4a" class="mt-sm">
          </div>
          <div class="field"><label>Volume <small>(<span id="volVal"><?= e($settings->music_volume) ?></span>%)</small></label>
            <input type="range" name="music_volume" min="0" max="100" value="<?= e($settings->music_volume) ?>" data-live="musicVolume">
          </div>
          <div class="preview-row"><span>Music preview</span>
            <button type="button" class="btn btn-ghost btn-sm" id="musicTestBtn">Play / Stop</button>
            <audio id="musicTestAudio" src="<?= e($settings->music_url) ?>"></audio>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-chat"/></svg> Discord Widget</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Show on Home</b><small>Display the Discord chat & members widgets on the home page</small></div>
            <label class="switch"><input type="checkbox" name="discord_enabled" <?= e($settings->discord_enabled === 'on' ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>Server ID</label><input type="text" name="discord_server_id" value="<?= e($settings->discord_server_id) ?>" placeholder="1472654686174843012" data-live="discordServer"></div>
          <div class="field"><label>WidgetBot Channel ID <small>(left chat panel)</small></label><input type="text" name="discord_channel" value="<?= e($settings->discord_channel) ?>" placeholder="1525049151627333715"></div>
          <div class="field"><label>Widget Theme</label>
            <select name="discord_theme">
              <option value="dark" <?= e($settings->discord_theme === 'dark' ? 'selected' : '') ?>>Dark</option>
              <option value="light" <?= e($settings->discord_theme === 'light' ? 'selected' : '') ?>>Light</option>
            </select>
          </div>
          <div class="preview-row"><span>Live preview</span>
            <iframe id="discordPreview" src="https://discord.com/widget?id=<?= e($settings->discord_server_id) ?>&theme=<?= e($settings->discord_theme) ?>" width="100%" height="280" allowtransparency="true" frameborder="0" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-12">
      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-eye"/></svg> Panel Background</h2></div>
        <div class="card-body">
          <div class="field"><label>Background Type</label>
            <select name="background_type" data-live="bgType">
              <option value="image" <?= e($settings->background_type !== 'video' ? 'selected' : '') ?>>Image</option>
              <option value="video" <?= e($settings->background_type === 'video' ? 'selected' : '') ?>>Video</option>
            </select>
          </div>
          <div class="field"><label>Background URL <small>(image or video)</small></label>
            <input type="text" name="background_url" value="<?= e($settings->background_url) ?>" placeholder="https://... or /uploads/..." data-live="bgUrl">
            <input type="file" name="background_file" accept="image/*,video/*" class="mt-sm" data-live="bgFile">
          </div>

          <div class="wp-badges">
            <button type="button" class="btn btn-accent" id="wpBrowseBtn"><svg class="ic"><use href="#i-image"/></svg> Browse 4K Wallpapers</button>
            <button type="button" class="btn" id="wpFavBtn"><svg class="ic"><use href="#i-heart"/></svg> Favorites (<span id="wpFavCount"><?= e(nh_count($favs)) ?></span>)</button>
          </div>

          <div class="field mt-sm"><label>4K Wallpapers Random Source <small>(https://4kwallpapers.com)</small></label>
            <select name="background_source" data-live="bgSource">
              <?php foreach ($sources as $src) { ?>
              <option value="<?= e($src->slug) ?>" <?= e($settings->background_source === $src->slug ? 'selected' : '') ?>><?= e($src->label) ?></option>
              <?php } ?>
            </select>
            <?php if ($settings->background_source && $settings->background_source !== 'none') { ?>
            <small class="hint"><a href="https://4kwallpapers.com/<?= e($settings->background_source) ?>/" target="_blank" rel="noopener">Open <?= e($settings->background_source) ?> on 4kwallpapers.com</a></small>
            <?php } ?>
          </div>

          <div class="preview-row"><span>Live preview</span>
            <div class="bg-preview" id="bgPreview">
              <div class="bgp-img" data-preview-img></div>
              <video data-preview-video muted loop playsinline hidden></video>
              <div class="bgp-overlay"></div>
              <div class="bgp-label">NobitaHost</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-sliders"/></svg> Glass & Transparency</h2></div>
        <div class="card-body">
          <div class="field"><label>Theme</label>
            <select name="theme" data-live="theme">
              <option value="dark" <?= e($settings->theme === 'dark' ? 'selected' : '') ?>>Dark</option>
              <option value="light" <?= e($settings->theme === 'light' ? 'selected' : '') ?>>Light</option>
              <option value="rainbow" <?= e($settings->theme === 'rainbow' ? 'selected' : '') ?>>Rainbow</option>
              <option value="neon" <?= e($settings->theme === 'neon' ? 'selected' : '') ?>>Neon Glow</option>
              <option value="sunset" <?= e($settings->theme === 'sunset' ? 'selected' : '') ?>>Sunset</option>
              <option value="ocean" <?= e($settings->theme === 'ocean' ? 'selected' : '') ?>>Ocean</option>
              <option value="nature" <?= e($settings->theme === 'nature' ? 'selected' : '') ?>>Nature</option>
              <option value="candy" <?= e($settings->theme === 'candy' ? 'selected' : '') ?>>Candy</option>
              <option value="fire" <?= e($settings->theme === 'fire' ? 'selected' : '') ?>>Fire</option>
              <option value="galaxy" <?= e($settings->theme === 'galaxy' ? 'selected' : '') ?>>Galaxy</option>
              <option value="luxury" <?= e($settings->theme === 'luxury' ? 'selected' : '') ?>>Luxury</option>
              <option value="pastel" <?= e($settings->theme === 'pastel' ? 'selected' : '') ?>>Pastel</option>
            </select>
          </div>

          <div style="display:flex;gap:6px;flex-wrap:wrap;margin:10px 0 16px">
            <span class="theme-dot" data-theme="dark" title="Dark" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'dark' ? 'var(--accent)' : 'var(--border)') ?>;background:#0a0e1a"></span>
            <span class="theme-dot" data-theme="light" title="Light" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'light' ? 'var(--accent)' : 'var(--border)') ?>;background:#eef1f7"></span>
            <span class="theme-dot" data-theme="rainbow" title="Rainbow" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'rainbow' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#ef4444,#f97316,#eab308,#22c55e,#3b82f6,#8b5cf6)"></span>
            <span class="theme-dot" data-theme="neon" title="Neon Glow" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'neon' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#06d6a0,#ec4899)"></span>
            <span class="theme-dot" data-theme="sunset" title="Sunset" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'sunset' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#f97316,#ec4899,#8b5cf6)"></span>
            <span class="theme-dot" data-theme="ocean" title="Ocean" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'ocean' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#0ea5e9,#14b8a6)"></span>
            <span class="theme-dot" data-theme="nature" title="Nature" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'nature' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#22c55e,#a3a33e)"></span>
            <span class="theme-dot" data-theme="candy" title="Candy" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'candy' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#f472b6,#93c5fd,#c4b5fd)"></span>
            <span class="theme-dot" data-theme="fire" title="Fire" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'fire' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#ef4444,#f97316,#eab308)"></span>
            <span class="theme-dot" data-theme="galaxy" title="Galaxy" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'galaxy' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#8b5cf6,#4f46e5,#3b82f6)"></span>
            <span class="theme-dot" data-theme="luxury" title="Luxury" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'luxury' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#d4a017,#10b981)"></span>
            <span class="theme-dot" data-theme="pastel" title="Pastel" style="width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid <?= e($settings->theme === 'pastel' ? 'var(--accent)' : 'var(--border)') ?>;background:linear-gradient(135deg,#f0a0b0,#c4b5fd,#6ee7b7)"></span>
          </div>

          <div class="range-row">
            <div class="range-top"><div><b>Transparency</b><small>How much the wallpaper shows through</small></div><span class="range-val" id="transVal"><?= e($settings->transparency ?: 100) ?>%</span></div>
            <input type="range" name="transparency" min="0" max="100" step="5" value="<?= e($settings->transparency ?: 100) ?>" data-live="transparency">
          </div>

          <div class="range-row">
            <div class="range-top"><div><b>Blur <small>(Glassmorphism)</small></b><small>Frosted-glass blur strength</small></div><span class="range-val" id="blurVal"><?= e($glass) ?>px</span></div>
            <input type="range" name="panel_blur" min="0" max="40" step="1" value="<?= e($glass) ?>" data-live="panelBlur">
          </div>

          <div class="row">
            <div class="col-6 col-sm-12"><div class="field"><label>Card Radius <small>(px)</small></label><input type="number" name="card_radius" min="0" max="40" value="<?= e($settings->card_radius) ?>" data-live="cardRadius"></div></div>
            <div class="col-6 col-sm-12"><div class="field"><label>Accent Color</label><input type="color" name="accent_color" value="<?= e($settings->accent_color) ?>" data-live="accentColor"></div></div>
          </div>

          <div class="preview-row"><span>Changes save automatically</span>
            <button type="button" class="btn btn-danger btn-sm" id="resetAppearanceBtn">Reset to Default</button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-play"/></svg> YouTube Channel <small>Tutorials</small></h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Show on Tutorials</b><small>Auto-display channel videos & stats on the tutorials page</small></div>
            <label class="switch"><input type="checkbox" name="youtube_enabled" <?= e($settings->youtube_enabled === 'on' ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>Channel <small>(handle, URL or channel ID)</small></label>
            <input type="text" name="youtube_channel" value="<?= e($settings->youtube_channel) ?>" placeholder="@codinghub_studiyo" data-live="ytChannel">
          </div>
          <div class="field"><label>YouTube Data API Key <small>(optional — enables likes, views & all videos)</small></label>
            <input type="text" name="youtube_api_key" value="<?= e($settings->youtube_api_key) ?>" placeholder="AIza..." autocomplete="off">
            <small class="hint">No key? Latest 15 videos auto-load with subscribers count. Add an API key for full stats & all videos.</small>
          </div>
          <div class="preview-row"><span>Channel preview</span>
            <div class="yt-preview">
              <div class="yt-avatar" id="ytAvatar">▶</div>
              <div><b id="ytTitle"><?= e($settings->youtube_channel) ?></b><span class="hint" id="ytHandle"></span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-user"/></svg> Instagram <small>Home social card</small></h2></div>
        <div class="card-body">
          <div class="field"><label>Instagram Handle</label>
            <input type="text" name="instagram_handle" value="<?= e($settings->instagram_handle) ?>" placeholder="@nobita_dev.in">
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-terminal"/></svg> Automated Script</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Show on Home</b><small>Display the automated script section on the home page</small></div>
            <label class="switch"><input type="checkbox" name="script_enabled" <?= e($settings->script_enabled === 'on' ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>Badge Text</label><input type="text" name="script_badge" value="<?= e($settings->script_badge) ?>" placeholder="ALL IN ONE CMD" data-live="scriptBadge"></div>
          <div class="field"><label>Description</label><input type="text" name="script_description" value="<?= e($settings->script_description) ?>" placeholder="Execute the master script to install all dependencies instantly." data-live="scriptDesc"></div>
          <div class="field"><label>Script Command</label><input type="text" name="script_command" value="<?= e($settings->script_command) ?>" placeholder="bash <(curl -s https://example.com)" data-live="scriptCmd"></div>
          <div class="preview-row"><span>Live preview</span>
            <div class="script-preview">
              <div class="cmd"><code id="scriptCmdPreview"><?= e($settings->script_command) ?></code><button type="button" class="btn btn-accent btn-sm" id="scriptCopyPreview">COPY</button></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-mail"/></svg> Email / SMTP</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Enable Email</b><small>Send welcome & password-reset emails</small></div>
            <label class="switch"><input type="checkbox" name="mail_enabled" <?= e($settings->mail_enabled === 'on' ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="row">
            <div class="col-8 col-sm-12"><div class="field"><label>SMTP Host</label><input type="text" name="smtp_host" value="<?= e($settings->smtp_host) ?>" placeholder="smtp.gmail.com"></div></div>
            <div class="col-4 col-sm-12"><div class="field"><label>Port</label><input type="text" name="smtp_port" value="<?= e($settings->smtp_port) ?>"></div></div>
          </div>
          <div class="row">
            <div class="col-6 col-sm-12"><div class="field"><label>SMTP User</label><input type="text" name="smtp_user" value="<?= e($settings->smtp_user) ?>" autocomplete="off"></div></div>
            <div class="col-6 col-sm-12"><div class="field"><label>SMTP Password</label><input type="password" name="smtp_pass" value="<?= e($settings->smtp_pass) ?>" autocomplete="new-password"></div></div>
          </div>
          <div class="switch-row"><div><b>Secure Connection (TLS)</b><small>Enable for port 465</small></div>
            <label class="switch"><input type="checkbox" name="smtp_secure" <?= e($settings->smtp_secure === 'true' ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>From Address</label><input type="text" name="mail_from" value="<?= e($settings->mail_from) ?>"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-shield"/></svg> Behaviour</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Open Registration</b><small>Allow new users to register</small></div>
            <label class="switch"><input type="checkbox" name="register_open" <?= e($settings->register_open === 'on' ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="switch-row"><div><b>Maintenance Mode</b><small>Block non-admin visitors</small></div>
            <label class="switch"><input type="checkbox" name="maintenance" <?= e($settings->maintenance === 'on' ? 'checked' : '') ?>><span></span></label>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2><svg class="ic"><use href="#i-plus"/></svg> Extras</h2></div>
        <div class="card-body">
          <div class="switch-row"><div><b>Cookie Consent Banner</b><small>Show a cookie consent notice for visitors</small></div>
            <label class="switch"><input type="checkbox" name="cookie_banner" <?= e($settings->cookie_banner === 'on' ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="switch-row"><div><b>Anti-Adblock Shield</b><small>Ask visitors to disable their ad blocker</small></div>
            <label class="switch"><input type="checkbox" name="anti_adblock" <?= e($settings->anti_adblock === 'on' ? 'checked' : '') ?>><span></span></label>
          </div>
          <div class="field"><label>Inject Body Code <small>(HTML/script injected on every page)</small></label>
            <textarea name="inject_body_code" rows="4" placeholder="<script>...</script> or any HTML"><?= e($settings->inject_body_code ?: '') ?></textarea>
          </div>
        </div>
      </div>

      <div class="sticky-save">
        <button class="btn btn-accent btn-block btn-lg" type="submit"><svg class="ic"><use href="#i-shield"/></svg> Save All Settings</button>
      </div>
    </div>

  </div>
</form>

<div class="modal" id="wpModal">
  <div class="modal-card wp-modal">
    <div class="modal-head">
      <h3><svg class="ic"><use href="#i-image"/></svg> Browse 4K Wallpapers</h3>
      <button type="button" class="btn btn-ghost btn-sm" data-close>✕</button>
    </div>
    <div class="wp-toolbar">
      <select id="wpCat"><?php foreach ($wpCats as $c) { ?><option value="<?= e($c->slug) ?>" <?= e($c->slug === 'all' ? 'selected' : '') ?>><?= e($c->label) ?></option><?php } ?></select>
      <input id="wpSearch" type="text" placeholder="Search wallpapers…">
      <button type="button" class="btn btn-accent" id="wpSearchBtn">Search</button>
      <button type="button" class="btn" id="wpClearBtn">Reset</button>
    </div>
    <div class="wp-grid" id="wpGrid"><div class="wp-loading"><span class="wp-spin"></span> Loading wallpapers…</div></div>
    <div class="wp-pager">
      <button type="button" class="btn" id="wpPrev">← Prev</button>
      <span id="wpPageInfo"></span>
      <button type="button" class="btn" id="wpNext">Next →</button>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

