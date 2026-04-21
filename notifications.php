<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: notifications.php'); exit; }

$pdo = getDB();

// ── Auto-mark overdue bills ───────────────────────────────────────────────────
$pdo->query("UPDATE bills SET status='Overdue' WHERE status='Unpaid' AND due_date < CURDATE()");

// ── Fetch notification categories ────────────────────────────────────────────
// Overdue bills
$overdue = $pdo->query(
    "SELECT b.*,c.name AS cname,c.contact,c.address,
     DATEDIFF(CURDATE(),b.due_date) AS days_overdue
     FROM bills b JOIN customers c ON b.customer_id=c.id
     WHERE b.status='Overdue' ORDER BY b.due_date ASC"
)->fetchAll();

// Bills due within 7 days
$dueSoon = $pdo->query(
    "SELECT b.*,c.name AS cname,c.contact,
     DATEDIFF(b.due_date,CURDATE()) AS days_left
     FROM bills b JOIN customers c ON b.customer_id=c.id
     WHERE b.status='Unpaid' AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
     ORDER BY b.due_date ASC"
)->fetchAll();

// Disconnected customers with unpaid bills
$disconnectedUnpaid = $pdo->query(
    "SELECT c.*,COUNT(b.id) AS unpaid_count, SUM(b.total) AS unpaid_total
     FROM customers c JOIN bills b ON c.id=b.customer_id
     WHERE c.status='Disconnected' AND b.status!='Paid'
     GROUP BY c.id ORDER BY unpaid_total DESC"
)->fetchAll();

// High consumption alerts (> 40 m³)
$highUsage = $pdo->query(
    "SELECT r.*,c.name AS cname,c.contact
     FROM readings r JOIN customers c ON r.customer_id=c.id
     WHERE r.consumption > 40
     ORDER BY r.reading_date DESC LIMIT 20"
)->fetchAll();

// No reading this month
$noReadingThisMonth = $pdo->query(
    "SELECT c.id,c.name,c.meter_no,c.contact
     FROM customers c
     WHERE c.status='Active'
     AND c.id NOT IN (
       SELECT DISTINCT customer_id FROM readings
       WHERE MONTH(reading_date)=MONTH(CURDATE()) AND YEAR(reading_date)=YEAR(CURDATE())
     )
     ORDER BY c.name"
)->fetchAll();

// Summary counts
$totalAlerts = count($overdue) + count($dueSoon) + count($highUsage) + count($noReadingThisMonth);

require_once 'includes/header.php';
renderHeader('Notifications', 'notifications');
?>

<!-- Summary Banner -->
<div class="card mb-3" style="background:linear-gradient(135deg,var(--accent-dark),var(--accent));color:#fff;border:none">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="font-size:36px">🔔</div>
    <div style="flex:1">
      <div style="font-size:20px;font-weight:900"><?= $totalAlerts ?> Active Notification<?= $totalAlerts!=1?'s':'' ?></div>
      <div style="font-size:13px;opacity:.85;margin-top:3px">Last checked: <?= date('F j, Y g:i A') ?></div>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <div style="text-align:center;background:rgba(255,255,255,.15);border-radius:10px;padding:10px 16px">
        <div style="font-size:22px;font-weight:900"><?= count($overdue) ?></div>
        <div style="font-size:11px;opacity:.85">Overdue</div>
      </div>
      <div style="text-align:center;background:rgba(255,255,255,.15);border-radius:10px;padding:10px 16px">
        <div style="font-size:22px;font-weight:900"><?= count($dueSoon) ?></div>
        <div style="font-size:11px;opacity:.85">Due Soon</div>
      </div>
      <div style="text-align:center;background:rgba(255,255,255,.15);border-radius:10px;padding:10px 16px">
        <div style="font-size:22px;font-weight:900"><?= count($noReadingThisMonth) ?></div>
        <div style="font-size:11px;opacity:.85">No Reading</div>
      </div>
    </div>
  </div>
</div>

<?php if ($totalAlerts === 0): ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:48px;margin-bottom:12px">🎉</div>
  <div style="font-size:18px;font-weight:700;color:var(--success)">All clear! No pending notifications.</div>
  <div style="color:var(--muted);font-size:13px;margin-top:6px">All bills are up to date and readings are recorded.</div>
</div>
<?php else: ?>

<!-- OVERDUE BILLS -->
<?php if (!empty($overdue)): ?>
<div class="card mb-3">
  <div class="card-title text-danger">🚨 Overdue Bills (<?= count($overdue) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Customer</th><th>Contact</th><th>Month</th><th>Amount</th><th>Days Overdue</th><th>Due Date</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($overdue as $b): ?>
        <tr>
          <td>
            <div class="fw-bold"><?= h($b['cname']) ?></div>
            <div class="fs-xs text-muted"><?= h($b['customer_id']) ?></div>
          </td>
          <td class="fs-sm"><?= h($b['contact']) ?></td>
          <td class="fs-sm"><?= h($b['billing_month']) ?></td>
          <td class="fw-bolder text-danger"><?= fmt($b['total']) ?></td>
          <td>
            <span style="background:var(--danger-bg);color:var(--danger);border-radius:6px;padding:2px 10px;font-size:12px;font-weight:700">
              <?= $b['days_overdue'] ?> days
            </span>
          </td>
          <td class="fs-sm text-danger"><?= h($b['due_date']) ?></td>
          <td>
            <a href="billing.php?view=<?= h($b['id']) ?>" class="btn btn-sm btn-info-soft">View Bill</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- DUE SOON -->
<?php if (!empty($dueSoon)): ?>
<div class="card mb-3">
  <div class="card-title text-warning">⏰ Bills Due Within 7 Days (<?= count($dueSoon) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Customer</th><th>Contact</th><th>Month</th><th>Amount</th><th>Days Left</th><th>Due Date</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($dueSoon as $b): ?>
        <tr>
          <td class="fw-bold"><?= h($b['cname']) ?></td>
          <td class="fs-sm"><?= h($b['contact']) ?></td>
          <td class="fs-sm"><?= h($b['billing_month']) ?></td>
          <td class="fw-bolder"><?= fmt($b['total']) ?></td>
          <td>
            <span style="background:var(--warning-bg);color:var(--warning);border-radius:6px;padding:2px 10px;font-size:12px;font-weight:700">
              <?= $b['days_left'] === 0 ? 'Today!' : $b['days_left'].' day'.($b['days_left']!=1?'s':'') ?>
            </span>
          </td>
          <td class="fs-sm"><?= h($b['due_date']) ?></td>
          <td>
            <a href="payments.php" class="btn btn-sm btn-success-soft">Record Payment</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- NO READING THIS MONTH -->
<?php if (!empty($noReadingThisMonth)): ?>
<div class="card mb-3">
  <div class="card-title" style="color:var(--info)">📟 No Reading This Month — <?= date('F Y') ?> (<?= count($noReadingThisMonth) ?>)</div>
  <p class="fs-sm text-muted mb-2">The following active customers have no meter reading recorded for the current month.</p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Customer</th><th>Meter No</th><th>Contact</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($noReadingThisMonth as $c): ?>
        <tr>
          <td class="fw-bold"><?= h($c['name']) ?></td>
          <td><code><?= h($c['meter_no']) ?></code></td>
          <td class="fs-sm"><?= h($c['contact']) ?></td>
          <td><a href="readings.php?customer=<?= h($c['id']) ?>" class="btn btn-sm btn-info-soft">Add Reading</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- HIGH CONSUMPTION -->
<?php if (!empty($highUsage)): ?>
<div class="card mb-3">
  <div class="card-title text-warning">💧 High Consumption Alerts — &gt;40 m³ (<?= count($highUsage) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Customer</th><th>Contact</th><th>Reading Date</th><th>Consumption</th><th>Previous</th><th>Current</th></tr>
      </thead>
      <tbody>
        <?php foreach ($highUsage as $r): ?>
        <tr>
          <td class="fw-bold"><?= h($r['cname']) ?></td>
          <td class="fs-sm"><?= h($r['contact']) ?></td>
          <td class="fs-sm"><?= h($r['reading_date']) ?></td>
          <td><span class="fw-bolder text-danger"><?= fmtNum($r['consumption']) ?> m³</span></td>
          <td><?= fmtNum($r['previous_reading']) ?></td>
          <td><?= fmtNum($r['current_reading']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- DISCONNECTED WITH UNPAID -->
<?php if (!empty($disconnectedUnpaid)): ?>
<div class="card mb-3">
  <div class="card-title text-danger">🔌 Disconnected Customers with Unpaid Bills (<?= count($disconnectedUnpaid) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Customer</th><th>Contact</th><th>Unpaid Bills</th><th>Total Outstanding</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($disconnectedUnpaid as $c): ?>
        <tr>
          <td class="fw-bold"><?= h($c['name']) ?></td>
          <td class="fs-sm"><?= h($c['contact']) ?></td>
          <td style="text-align:center"><?= $c['unpaid_count'] ?></td>
          <td class="fw-bolder text-danger"><?= fmt($c['unpaid_total']) ?></td>
          <td>
            <a href="billing.php?search=<?= urlencode($c['name']) ?>" class="btn btn-sm btn-danger-soft">View Bills</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php renderFooter(); ?>
