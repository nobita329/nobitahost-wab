<?php
/**
 * Ported from views/pages/plans/transactions.ejs (EJS → PHP) by php/tools/ejs2php.mjs.
 * Layout + shell come from views/layouts/app.php (CasaOS UI).
 */
?>
<?php /* EJS2PHP: layout include 'partials/head' handled by the PHP layout */ ?>


<div class="card table-card">
  <div class="card-head">
    <h2><svg class="ic"><use href="#i-chart"/></svg> Transactions <small class="badge"><?= e($total) ?></small></h2>
    <div class="head-actions">
      <form method="GET" action="/plans/transactions" class="search-form">
        <input type="text" name="q" placeholder="Search transactions..." value="<?= e($q ?: '') ?>">
      </form>
    </div>
  </div>
  <div class="card-body">
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Plan</th>
          <th>Amount</th>
          <th>Discount</th>
          <th>Tax</th>
          <th>Total</th>
          <th>Method</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t) { ?>
        <tr>
          <td><b><?= e($t->username ?: '-') ?></b></td>
          <td><span class="badge"><?= e($t->plan_name) ?></span></td>
          <td><?= e($settings->plan_currency ?: '$') ?><?= e(number_format((float) $t->amount, 2)) ?></td>
          <td><?php if ($t->discount > 0) { ?><span class="badge green">-<?= e($settings->plan_currency ?: '$') ?><?= e(number_format((float) $t->discount, 2)) ?></span><?php } else { ?>-<?php } ?></td>
          <td><?= e($t->tax > 0 ? ($settings->plan_currency ?: '$') + number_format((float) $t->tax, 2) : '-') ?></td>
          <td><b><?= e($settings->plan_currency ?: '$') ?><?= e(number_format((float) $t->total, 2)) ?></b></td>
          <td><small><?= e($t->payment_method ?: '-') ?></small></td>
          <td><span class="badge <?= e($t->status === 'completed' ? 'green' : ($t->status === 'refunded' ? 'gold' : 'red')) ?>"><?= e($t->status) ?></span></td>
          <td><small style="color:var(--muted)"><?= e($t->created_at) ?></small></td>
        </tr>
        <?php } ?>
        <?php if (!nh_count($transactions)) { ?>
        <tr><td colspan="9" class="empty">No transactions yet.</td></tr>
        <?php } ?>
      </tbody>
    </table>
    </div>
    <?php if ($totalPages > 1) { ?>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:16px">
      <?php if ($page > 1) { ?><a class="btn btn-ghost btn-sm" href="/plans/transactions?page=<?= e($page - 1) ?>&q=<?= e($q ?: '') ?>">Prev</a><?php } ?>
      <span style="line-height:32px;color:var(--muted)">Page <?= e($page) ?> of <?= e($totalPages) ?></span>
      <?php if ($page < $totalPages) { ?><a class="btn btn-ghost btn-sm" href="/plans/transactions?page=<?= e($page + 1) ?>&q=<?= e($q ?: '') ?>">Next</a><?php } ?>
    </div>
    <?php } ?>
  </div>
</div>

<?php /* EJS2PHP: layout include 'partials/footer' handled by the PHP layout */ ?>

