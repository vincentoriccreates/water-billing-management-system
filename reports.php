<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: reports.php'); exit; }

$pdo = getDB();

// ── CSV Export ────────────────────────────────────────────────────────────────
if (get('export')) {
    $type = get('export');
    $rows = [];
    $filename = 'report.csv';

    if ($type === 'billing') {
        $filename = 'billing_summary_' . date('Ymd') . '.csv';
        $rows = $pdo->query(
            "SELECT b.id AS 'Bill ID', c.name AS 'Customer', c.id AS 'Account',
             b.billing_month AS 'Month', b.consumption AS 'Consumption (m3)',
             b.base_charge AS 'Base Charge', b.penalty AS 'Penalty',
             b.total AS 'Total', b.status AS 'Status', b.due_date AS 'Due Date'
             FROM bills b JOIN customers c ON b.customer_id=c.id ORDER BY b.created_at DESC"
        )->fetchAll();
    } elseif ($type === 'collections') {
        $filename = 'collections_' . date('Ymd') . '.csv';
        $rows = $pdo->query(
            "SELECT p.receipt_no AS 'Receipt', c.name AS 'Customer', b.billing_month AS 'Month',
             p.amount AS 'Amount', p.method AS 'Method', p.payment_date AS 'Date', p.notes AS 'Notes'
             FROM payments p JOIN customers c ON p.customer_id=c.id JOIN bills b ON p.bill_id=b.id
             ORDER BY p.payment_date DESC"
        )->fetchAll();
    } elseif ($type === 'unpaid') {
        $filename = 'unpaid_accounts_' . date('Ymd') . '.csv';
        $rows = $pdo->query(
            "SELECT c.id AS 'Account', c.name AS 'Customer', c.contact AS 'Contact',
             b.billing_month AS 'Month', b.total AS 'Amount Due',
             b.status AS 'Status', b.due_date AS 'Due Date'
             FROM bills b JOIN customers c ON b.customer_id=c.id
             WHERE b.status!='Paid' ORDER BY b.due_date"
        )->fetchAll();
    } elseif ($type === 'usage') {
        $filename = 'usage_report_' . date('Ymd') . '.csv';
        $rows = $pdo->query(
            "SELECT c.id AS 'Account', c.name AS 'Customer', c.meter_no AS 'Meter',
             r.reading_date AS 'Date', r.previous_reading AS 'Previous',
             r.current_reading AS 'Current', r.consumption AS 'Consumption (m3)'
             FROM readings r JOIN customers c ON r.customer_id=c.id ORDER BY r.reading_date DESC"
        )->fetchAll();
    }

    if ($rows) {
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    }
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalBilled    = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills")->fetchColumn();
$totalCollected = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$totalUnpaid    = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills WHERE status!='Paid'")->fetchColumn();
$avgConsumption = (float)$pdo->query("SELECT COALESCE(AVG(consumption),0) FROM readings")->fetchColumn();

// Tab data
$tab = get('tab', 'summary');

$bills       = $pdo->query("SELECT b.*,c.name AS cname FROM bills b JOIN customers c ON b.customer_id=c.id ORDER BY b.created_at DESC")->fetchAll();
$payments    = $pdo->query("SELECT p.*,c.name AS cname,b.billing_month FROM payments p JOIN customers c ON p.customer_id=c.id JOIN bills b ON p.bill_id=b.id ORDER BY p.payment_date DESC")->fetchAll();
$unpaidBills = $pdo->query("SELECT b.*,c.name AS cname,c.contact FROM bills b JOIN customers c ON b.customer_id=c.id WHERE b.status!='Paid' ORDER BY b.due_date")->fetchAll();
$usageReport = $pdo->query("SELECT r.*,c.name AS cname,c.meter_no FROM readings r JOIN customers c ON r.customer_id=c.id ORDER BY r.reading_date DESC LIMIT 50")->fetchAll();

require_once 'includes/header.php';
renderHeader('Reports', 'reports');
?>

<!-- Summary Cards -->
<div class="stat-grid mb-3">
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--info-bg)">💵</div>
    <div><div class="stat-val text-accent"><?= fmt($totalBilled) ?></div><div class="stat-label">Total Billed</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--success-bg)">✅</div>
    <div><div class="stat-val text-success"><?= fmt($totalCollected) ?></div><div class="stat-label">Total Collected</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--danger-bg)">⚠️</div>
    <div><div class="stat-val text-danger"><?= fmt($totalUnpaid) ?></div><div class="stat-label">Total Unpaid</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--info-bg)">💧</div>
    <div><div class="stat-val" style="color:var(--info)"><?= round($avgConsumption,1) ?> m³</div><div class="stat-label">Avg Consumption</div></div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <a href="reports.php?tab=summary"     class="tab-btn <?= $tab==='summary'?'active':'' ?>">📋 Summary</a>
  <a href="reports.php?tab=collections" class="tab-btn <?= $tab==='collections'?'active':'' ?>">💰 Collections</a>
  <a href="reports.php?tab=unpaid"      class="tab-btn <?= $tab==='unpaid'?'active':'' ?>">⚠️ Unpaid</a>
  <a href="reports.php?tab=usage"       class="tab-btn <?= $tab==='usage'?'active':'' ?>">💧 Usage</a>
</div>

<!-- Content -->
<div class="card">
  <?php if ($tab === 'summary'): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div class="card-title" style="margin:0">Monthly Billing Summary</div>
    <a href="reports.php?export=billing" class="btn btn-success btn-sm">⬇️ Export CSV</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Bill ID</th><th>Customer</th><th>Month</th><th>Consumption</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($bills as $b): ?>
        <tr>
          <td><code><?= h($b['id']) ?></code></td>
          <td class="fw-bold"><?= h($b['cname']) ?></td>
          <td class="fs-sm"><?= h($b['billing_month']) ?></td>
          <td style="color:var(--info)"><?= h($b['consumption']) ?> m³</td>
          <td class="fw-bolder"><?= fmt($b['total']) ?></td>
          <td><span class="badge badge-<?= strtolower(h($b['status'])) ?>"><?= h($b['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php elseif ($tab === 'collections'): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div class="card-title" style="margin:0">Payment Collections</div>
    <a href="reports.php?export=collections" class="btn btn-success btn-sm">⬇️ Export CSV</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Receipt</th><th>Customer</th><th>Month</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><code style="background:var(--success-bg);color:var(--success)"><?= h($p['receipt_no']) ?></code></td>
          <td class="fw-bold"><?= h($p['cname']) ?></td>
          <td class="fs-sm"><?= h($p['billing_month']) ?></td>
          <td class="fw-bolder text-success"><?= fmt($p['amount']) ?></td>
          <td><span class="badge badge-staff"><?= h($p['method']) ?></span></td>
          <td class="fs-sm"><?= h($p['payment_date']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php elseif ($tab === 'unpaid'): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div class="card-title text-danger" style="margin:0">⚠️ Unpaid Accounts</div>
    <a href="reports.php?export=unpaid" class="btn btn-success btn-sm">⬇️ Export CSV</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Customer</th><th>Contact</th><th>Month</th><th>Amount Due</th><th>Due Date</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($unpaidBills as $b): ?>
        <tr>
          <td class="fw-bold"><?= h($b['cname']) ?></td>
          <td class="fs-sm"><?= h($b['contact']) ?></td>
          <td class="fs-sm"><?= h($b['billing_month']) ?></td>
          <td class="fw-bolder text-danger"><?= fmt($b['total']) ?></td>
          <td class="fs-sm"><?= h($b['due_date']) ?></td>
          <td><span class="badge badge-<?= strtolower(h($b['status'])) ?>"><?= h($b['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($unpaidBills)): ?>
        <tr><td colspan="6" style="text-align:center;padding:24px" class="text-success fw-bold">🎉 All bills are paid!</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php elseif ($tab === 'usage'): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div class="card-title" style="margin:0">Customer Water Usage</div>
    <a href="reports.php?export=usage" class="btn btn-success btn-sm">⬇️ Export CSV</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Customer</th><th>Meter</th><th>Date</th><th>Previous</th><th>Current</th><th>Consumption</th></tr></thead>
      <tbody>
        <?php foreach ($usageReport as $r): ?>
        <tr>
          <td class="fw-bold"><?= h($r['cname']) ?></td>
          <td><code><?= h($r['meter_no']) ?></code></td>
          <td class="fs-sm"><?= h($r['reading_date']) ?></td>
          <td><?= fmtNum($r['previous_reading']) ?></td>
          <td><?= fmtNum($r['current_reading']) ?></td>
          <td class="fw-bold <?= $r['consumption']>30?'text-warning':'text-success' ?>"><?= h($r['consumption']) ?> m³</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php renderFooter(); ?>
