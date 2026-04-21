<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: customer_detail.php?id='.get('id')); exit; }

$id  = get('id');
if (!$id) { header('Location: customers.php'); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) { header('Location: customers.php'); exit; }

// All readings for this customer
$readings = $pdo->prepare("SELECT * FROM readings WHERE customer_id=? ORDER BY reading_date DESC");
$readings->execute([$id]);
$readings = $readings->fetchAll();

// All bills with paid amounts
$bills = $pdo->prepare(
    "SELECT b.*,
     COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id=b.id),0) AS paid_amt
     FROM bills b WHERE b.customer_id=? ORDER BY b.created_at DESC"
);
$bills->execute([$id]);
$bills = $bills->fetchAll();

// All payments
$payments = $pdo->prepare(
    "SELECT p.*,b.billing_month FROM payments p
     JOIN bills b ON p.bill_id=b.id WHERE p.customer_id=? ORDER BY p.payment_date DESC"
);
$payments->execute([$id]);
$payments = $payments->fetchAll();

// Summary stats
$totalBilled    = array_sum(array_column($bills, 'total'));
$totalPaid      = array_sum(array_column($payments, 'amount'));
$totalBalance   = $totalBilled - $totalPaid;
$latestReading  = $readings[0] ?? null;
$avgConsumption = $readings ? round(array_sum(array_column($readings,'consumption'))/count($readings),1) : 0;

require_once 'includes/header.php';
renderHeader('Customer Detail', 'customers');
?>

<div style="margin-bottom:16px">
  <a href="customers.php" class="btn btn-outline btn-sm">← Back to Customers</a>
</div>

<!-- Customer Header -->
<div class="card mb-3" style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
  <div style="width:60px;height:60px;border-radius:14px;background:var(--accent);color:#fff;
              display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;flex-shrink:0">
    <?= strtoupper(substr($customer['name'],0,1)) ?>
  </div>
  <div style="flex:1;min-width:200px">
    <h2 style="margin:0 0 4px;color:var(--text)"><?= h($customer['name']) ?></h2>
    <div class="fs-sm text-muted"><?= h($customer['address']) ?></div>
    <div style="display:flex;gap:16px;margin-top:10px;flex-wrap:wrap">
      <div><span class="fs-xs text-muted">Account No</span><br><code><?= h($customer['id']) ?></code></div>
      <div><span class="fs-xs text-muted">Meter No</span><br><code><?= h($customer['meter_no']) ?></code></div>
      <div><span class="fs-xs text-muted">Contact</span><br><span class="fw-bold fs-sm"><?= h($customer['contact']) ?></span></div>
      <div><span class="fs-xs text-muted">Since</span><br><span class="fs-sm"><?= h($customer['created_at']) ?></span></div>
      <div><span class="fs-xs text-muted">Status</span><br><span class="badge badge-<?= strtolower(h($customer['status'])) ?>"><?= h($customer['status']) ?></span></div>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="customers.php?edit=<?= h($customer['id']) ?>" class="btn btn-primary btn-sm">✏️ Edit</a>
    <a href="readings.php?customer=<?= h($customer['id']) ?>" class="btn btn-outline btn-sm">💧 Add Reading</a>
    <a href="billing.php" class="btn btn-outline btn-sm">💵 New Bill</a>
  </div>
</div>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px">
  <div class="card" style="text-align:center">
    <div style="font-size:20px;font-weight:900;color:var(--accent)"><?= fmt($totalBilled) ?></div>
    <div class="fs-xs text-muted mt-1">Total Billed</div>
  </div>
  <div class="card" style="text-align:center">
    <div style="font-size:20px;font-weight:900;color:var(--success)"><?= fmt($totalPaid) ?></div>
    <div class="fs-xs text-muted mt-1">Total Paid</div>
  </div>
  <div class="card" style="text-align:center">
    <div style="font-size:20px;font-weight:900;color:<?= $totalBalance>0?'var(--danger)':'var(--success)' ?>"><?= fmt($totalBalance) ?></div>
    <div class="fs-xs text-muted mt-1">Outstanding Balance</div>
  </div>
  <div class="card" style="text-align:center">
    <div style="font-size:20px;font-weight:900;color:var(--info)"><?= $latestReading ? fmtNum($latestReading['current_reading']).' m³' : '—' ?></div>
    <div class="fs-xs text-muted mt-1">Last Reading</div>
  </div>
  <div class="card" style="text-align:center">
    <div style="font-size:20px;font-weight:900;color:var(--info)"><?= $avgConsumption ?> m³</div>
    <div class="fs-xs text-muted mt-1">Avg Consumption</div>
  </div>
  <div class="card" style="text-align:center">
    <div style="font-size:20px;font-weight:900;color:var(--text)"><?= count($bills) ?></div>
    <div class="fs-xs text-muted mt-1">Total Bills</div>
  </div>
</div>

<!-- Readings history -->
<div class="card mb-3">
  <div class="card-title">💧 Reading History</div>
  <?php if ($readings): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Previous (m³)</th><th>Current (m³)</th><th>Consumption</th></tr></thead>
      <tbody>
        <?php foreach ($readings as $r): ?>
        <tr>
          <td class="fs-sm"><?= h($r['reading_date']) ?></td>
          <td><code><?= fmtNum($r['previous_reading']) ?></code></td>
          <td><code><?= fmtNum($r['current_reading']) ?></code></td>
          <td class="fw-bold <?= $r['consumption']>30?'text-warning':'text-success' ?>"><?= fmtNum($r['consumption']) ?> m³</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><div class="empty-icon">📟</div><div class="empty-title">No readings yet</div><div class="empty-sub">Add the first meter reading for this customer.</div></div>
  <?php endif; ?>
</div>

<!-- Billing history -->
<div class="card mb-3">
  <div class="card-title">💵 Billing History</div>
  <?php if ($bills): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Month</th><th>Consumption</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Due</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($bills as $b): $bal = $b['total'] - $b['paid_amt']; ?>
        <tr>
          <td class="fs-sm"><?= h($b['billing_month']) ?></td>
          <td style="color:var(--info)"><?= h($b['consumption']) ?> m³</td>
          <td class="fw-bold"><?= fmt($b['total']) ?></td>
          <td class="text-success"><?= fmt($b['paid_amt']) ?></td>
          <td class="fw-bold <?= $bal>0?'text-danger':'text-success' ?>"><?= fmt($bal) ?></td>
          <td><span class="badge badge-<?= strtolower(h($b['status'])) ?>"><?= h($b['status']) ?></span></td>
          <td class="fs-xs"><?= h($b['due_date']) ?></td>
          <td><a href="billing.php?view=<?= h($b['id']) ?>" class="btn btn-sm btn-info-soft">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><div class="empty-icon">💵</div><div class="empty-title">No bills yet</div><div class="empty-sub">Generate the first bill for this customer.</div></div>
  <?php endif; ?>
</div>

<!-- Payment history -->
<div class="card">
  <div class="card-title">💳 Payment History</div>
  <?php if ($payments): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Receipt No</th><th>Bill Period</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><code style="background:var(--success-bg);color:var(--success)"><?= h($p['receipt_no']) ?></code></td>
          <td class="fs-sm"><?= h($p['billing_month']) ?></td>
          <td class="fw-bolder text-success"><?= fmt($p['amount']) ?></td>
          <td><span class="badge badge-staff"><?= h($p['method']) ?></span></td>
          <td class="fs-sm"><?= h($p['payment_date']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><div class="empty-icon">💳</div><div class="empty-title">No payments yet</div></div>
  <?php endif; ?>
</div>

<?php renderFooter(); ?>
