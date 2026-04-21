<?php
require_once 'includes/functions.php';
requireLogin();
if (!isAdmin()) { header('Location: dashboard.php'); exit; }
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: settings.php'); exit; }

$pdo = getDB();

// ── Handle bulk actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'mark_overdue') {
        $affected = $pdo->exec("UPDATE bills SET status='Overdue' WHERE status='Unpaid' AND due_date < CURDATE()");
        setFlash("Marked $affected bills as Overdue.");
    } elseif ($action === 'apply_penalty') {
        $penaltyAmt = (float)post('penalty_amount');
        if ($penaltyAmt > 0) {
            $affected = $pdo->exec(
                "UPDATE bills SET penalty=penalty+$penaltyAmt, total=total+$penaltyAmt
                 WHERE status='Overdue'"
            );
            setFlash("Applied ₱{$penaltyAmt} penalty to $affected overdue bills.");
        }
    } elseif ($action === 'bulk_due_date') {
        $newDue   = post('new_due_date');
        $affected = 0;
        if ($newDue) {
            $affected = $pdo->exec("UPDATE bills SET due_date='$newDue' WHERE status='Unpaid'");
        }
        setFlash("Updated due date for $affected unpaid bills.");
    } elseif ($action === 'purge_paid') {
        $months = max(1,(int)post('purge_months'));
        $cutoff = date('Y-m-d', strtotime("-$months months"));
        $affected = $pdo->exec("DELETE FROM bills WHERE status='Paid' AND due_date < '$cutoff'");
        setFlash("Purged $affected old paid bills (before $cutoff).");
    }

    header('Location: settings.php');
    exit;
}

// Stats summary
$stats = [
    'unpaid_bills'   => (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='Unpaid'")->fetchColumn(),
    'overdue_bills'  => (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='Overdue'")->fetchColumn(),
    'active_cust'    => (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE status='Active'")->fetchColumn(),
    'total_bills'    => (int)$pdo->query("SELECT COUNT(*) FROM bills")->fetchColumn(),
    'total_payments' => (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
    'total_readings' => (int)$pdo->query("SELECT COUNT(*) FROM readings")->fetchColumn(),
];

require_once 'includes/header.php';
renderHeader('System Settings', 'settings');
?>

<div style="max-width:900px;margin:0 auto">

  <!-- Database Stats -->
  <div class="card mb-3">
    <div class="card-title">📊 Database Overview</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px">
      <?php
      $labels = ['unpaid_bills'=>'Unpaid Bills','overdue_bills'=>'Overdue Bills','active_cust'=>'Active Customers','total_bills'=>'Total Bills','total_payments'=>'Total Payments','total_readings'=>'Total Readings'];
      $colors = ['unpaid_bills'=>'warning','overdue_bills'=>'danger','active_cust'=>'success','total_bills'=>'accent','total_payments'=>'success','total_readings'=>'info'];
      foreach ($stats as $k => $v):
      ?>
      <div style="background:var(--surface-alt);border-radius:10px;padding:14px;text-align:center">
        <div style="font-size:22px;font-weight:900;color:var(--<?= $colors[$k] ?>)"><?= $v ?></div>
        <div class="fs-xs text-muted" style="margin-top:4px"><?= $labels[$k] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Bulk Actions -->
  <div class="two-col mb-3">

    <!-- Mark Overdue -->
    <div class="card">
      <div class="card-title">🔴 Mark Overdue Bills</div>
      <p class="fs-sm text-muted mb-2">Automatically mark all unpaid bills past their due date as Overdue.</p>
      <div class="info-box">Currently <strong><?= $stats['unpaid_bills'] ?></strong> unpaid bills. After running, past-due bills will be marked Overdue.</div>
      <form method="POST">
        <input type="hidden" name="action" value="mark_overdue">
        <button type="submit" class="btn btn-danger" onclick="return confirm('Mark all past-due unpaid bills as Overdue?')">
          🔴 Run Now
        </button>
      </form>
    </div>

    <!-- Apply Penalty -->
    <div class="card">
      <div class="card-title">💸 Apply Penalty to Overdue Bills</div>
      <p class="fs-sm text-muted mb-2">Add a flat penalty amount to all currently overdue bills.</p>
      <div class="danger-box">⚠️ This will add the penalty to <strong><?= $stats['overdue_bills'] ?></strong> overdue bills. This cannot be undone.</div>
      <form method="POST">
        <input type="hidden" name="action" value="apply_penalty">
        <div class="form-group">
          <label class="form-label">Penalty Amount (₱)</label>
          <input type="number" name="penalty_amount" class="form-control" min="1" step="0.01" placeholder="e.g. 50.00" required>
        </div>
        <button type="submit" class="btn btn-danger" onclick="return confirm('Apply penalty to all overdue bills?')">
          💸 Apply Penalty
        </button>
      </form>
    </div>

    <!-- Bulk Due Date -->
    <div class="card">
      <div class="card-title">📅 Bulk Update Due Dates</div>
      <p class="fs-sm text-muted mb-2">Set a new due date for all currently unpaid bills.</p>
      <form method="POST">
        <input type="hidden" name="action" value="bulk_due_date">
        <div class="form-group">
          <label class="form-label">New Due Date</label>
          <input type="date" name="new_due_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Update due date for all unpaid bills?')">
          📅 Update Due Dates
        </button>
      </form>
    </div>

    <!-- Purge Old Paid Bills -->
    <div class="card">
      <div class="card-title">🗑️ Archive Old Paid Bills</div>
      <p class="fs-sm text-muted mb-2">Permanently delete paid bills older than N months to keep the database clean.</p>
      <div class="danger-box">⚠️ <strong>Irreversible!</strong> Deleted bills cannot be recovered. Export a report first.</div>
      <form method="POST">
        <input type="hidden" name="action" value="purge_paid">
        <div class="form-group">
          <label class="form-label">Delete paid bills older than (months)</label>
          <input type="number" name="purge_months" class="form-control" min="3" value="12" required>
        </div>
        <button type="submit" class="btn btn-danger" onclick="return confirm('PERMANENTLY DELETE old paid bills? This cannot be undone.')">
          🗑️ Purge Records
        </button>
      </form>
    </div>
  </div>

  <!-- Billing Rate Config (display only) -->
  <div class="card mb-3">
    <div class="card-title">💧 Current Billing Rate Configuration</div>
    <p class="fs-sm text-muted mb-2">To change rates, edit <code>config/db.php</code> on the server.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
      <div style="background:var(--info-bg);border-radius:10px;padding:16px">
        <div class="fs-xs text-muted">Base Monthly Charge</div>
        <div style="font-size:24px;font-weight:900;color:var(--info);margin-top:4px"><?= fmt(BASE_CHARGE) ?></div>
      </div>
      <div style="background:var(--info-bg);border-radius:10px;padding:16px">
        <div class="fs-xs text-muted">Rate per Cubic Meter</div>
        <div style="font-size:24px;font-weight:900;color:var(--info);margin-top:4px"><?= fmt(RATE_PER_CUBIC) ?>/m³</div>
      </div>
      <div style="background:var(--success-bg);border-radius:10px;padding:16px">
        <div class="fs-xs text-muted">Example: 20 m³ usage</div>
        <div style="font-size:24px;font-weight:900;color:var(--success);margin-top:4px"><?= fmt(BASE_CHARGE + 20*RATE_PER_CUBIC) ?></div>
      </div>
    </div>
  </div>

  <!-- Quick Links -->
  <div class="card">
    <div class="card-title">🔗 Quick Admin Links</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="notifications.php" class="btn btn-outline">🔔 Notifications</a>
      <a href="reports.php?export=billing"     class="btn btn-outline">⬇️ Export Billing CSV</a>
      <a href="reports.php?export=collections" class="btn btn-outline">⬇️ Export Collections CSV</a>
      <a href="reports.php?export=unpaid"      class="btn btn-outline">⬇️ Export Unpaid CSV</a>
      <a href="users.php"                       class="btn btn-outline">👥 Manage Users</a>
    </div>
  </div>

</div>

<?php renderFooter(); ?>
