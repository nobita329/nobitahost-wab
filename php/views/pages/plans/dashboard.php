<?php
/**
 * Ported from views/pages/plans/dashboard.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="row">
  <div class="col-3"><div class="stat-card"><div class="stat-icon blue"><svg class="ic"><use href="#i-folder"/></svg></div><div class="stat-meta"><h3><?= e($stats->totalPlans) ?></h3><p>Total Plans</p></div></div></div>
  <div class="col-3"><div class="stat-card"><div class="stat-icon green"><svg class="ic"><use href="#i-shield"/></svg></div><div class="stat-meta"><h3><?= e($stats->activePlans) ?></h3><p>Active Plans</p></div></div></div>
  <div class="col-3"><div class="stat-card"><div class="stat-icon gold"><svg class="ic"><use href="#i-users"/></svg></div><div class="stat-meta"><h3><?= e($stats->totalSubscribers) ?></h3><p>Total Subscribers</p></div></div></div>
  <div class="col-3"><div class="stat-card"><div class="stat-icon purple"><svg class="ic"><use href="#i-chart"/></svg></div><div class="stat-meta"><h3><?= e($settings->plan_currency ?: '$') ?><?= e(number_format((float) $stats->monthlyRevenue, 2)) ?></h3><p>Monthly Revenue</p></div></div></div>
</div>

<div class="row" style="margin-top:16px">
  <div class="col-6">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-chart"/></svg> Recent Transactions</h2></div>
      <div class="card-body">
        <?php if (nh_count($recentTransactions)) { ?>
        <table>
          <thead><tr><th>User</th><th>Plan</th><th>Amount</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($recentTransactions as $t) { ?>
            <tr>
              <td><?= e($t->username ?: '-') ?></td>
              <td><span class="badge"><?= e($t->plan_name) ?></span></td>
              <td><?= e($settings->plan_currency ?: '$') ?><?= e(number_format((float) $t->total, 2)) ?></td>
              <td><small style="color:var(--muted)"><?= e($t->created_at) ?></small></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
        <?php } else { ?><div class="empty">No transactions yet.</div><?php } ?>
      </div>
    </div>
  </div>
  <div class="col-6">
    <div class="card">
      <div class="card-head"><h2><svg class="ic"><use href="#i-info"/></svg> Quick Stats</h2></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:12px">
          <div style="display:flex;justify-content:space-between"><span>Active Subscribers</span><b><?= e($stats->activeSubscribers) ?></b></div>
          <div style="display:flex;justify-content:space-between"><span>Expired Subscriptions</span><b><?= e($stats->expiredSubscriptions) ?></b></div>
          <div style="display:flex;justify-content:space-between"><span>Total Coupons</span><b><?= e($stats->totalCoupons) ?></b></div>
          <div style="display:flex;justify-content:space-between"><span>Active Coupons</span><b><?= e($stats->activeCoupons) ?></b></div>
          <div style="display:flex;justify-content:space-between"><span>Total Revenue</span><b><?= e($settings->plan_currency ?: '$') ?><?= e(number_format((float) $stats->totalRevenue, 2)) ?></b></div>
        </div>
        <div style="margin-top:16px;display:flex;gap:8px">
          <a class="btn btn-accent btn-sm" href="/plans/add">Add Plan</a>
          <a class="btn btn-ghost btn-sm" href="/plans/all">View All Plans</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

