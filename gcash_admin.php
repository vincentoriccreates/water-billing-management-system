<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: gcash_admin.php'); exit; }

$pdo = getDB();

// Ensure table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gcash_payments (
            id VARCHAR(12) PRIMARY KEY,
            bill_id VARCHAR(10) NOT NULL,
            customer_id VARCHAR(10) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            gcash_ref VARCHAR(50),
            payer_number VARCHAR(20),
            status ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
            notes TEXT,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            confirmed_at TIMESTAMP NULL,
            confirmed_by INT NULL
        )
    ");
} catch (Exception $e) {}

// ── POST: Confirm or Reject ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = post('action');
    $gcashId  = post('gcash_id');

    $gs = $pdo->prepare("SELECT * FROM gcash_payments WHERE id=?");
    $gs->execute([$gcashId]);
    $gcashPay = $gs->fetch();

    if (!$gcashPay) { setFlash('GCash record not found.','error'); }
    elseif ($action === 'confirm') {
        // Record as official payment
        $bStmt = $pdo->prepare("SELECT * FROM bills WHERE id=?");
        $bStmt->execute([$gcashPay['bill_id']]);
        $bill = $bStmt->fetch();

        if ($bill) {
            // Get already paid amount
            $paid = (float)$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE bill_id=?")->execute([$bill['id']]) ? 0 : 0;
            $ps2 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE bill_id=?");
            $ps2->execute([$bill['id']]);
            $alreadyPaid = (float)$ps2->fetchColumn();

            // Insert payment record
            $pCount = (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn() + 1;
            $payId  = 'P' . str_pad($pCount, 3, '0', STR_PAD_LEFT);
            $rcpt   = 'GC-' . str_pad($pCount, 4, '0', STR_PAD_LEFT);
            $pdo->prepare(
                "INSERT INTO payments (id,bill_id,customer_id,amount,payment_date,method,receipt_no,notes)
                 VALUES (?,?,?,?,?,?,?,?)"
            )->execute([
                $payId, $bill['id'], $gcashPay['customer_id'],
                $gcashPay['amount'], date('Y-m-d'),
                'GCash', $rcpt,
                'GCash Ref: ' . $gcashPay['gcash_ref'] . ' · From: ' . $gcashPay['payer_number']
            ]);

            // Update bill status if fully paid
            $newTotal = $alreadyPaid + $gcashPay['amount'];
            if ($newTotal >= $bill['total'] - 0.01) {
                $pdo->prepare("UPDATE bills SET status='Paid' WHERE id=?")->execute([$bill['id']]);
            }

            // Mark gcash payment confirmed
            $pdo->prepare("UPDATE gcash_payments SET status='Confirmed', confirmed_at=NOW(), confirmed_by=? WHERE id=?")
                ->execute([currentUser()['id'], $gcashId]);

            setFlash("GCash payment confirmed! Receipt: $rcpt · Payment ID: $payId");
        }
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE gcash_payments SET status='Rejected', confirmed_at=NOW(), confirmed_by=? WHERE id=?")
            ->execute([currentUser()['id'], $gcashId]);
        setFlash("GCash payment rejected.");
    }
    header('Location: gcash_admin.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$filter = get('filter', 'Pending');
$pg     = max(1, (int)get('page','1'));
$perPage= 12;

$where  = $filter !== 'All' ? "WHERE gp.status=?" : "";
$params = $filter !== 'All' ? [$filter] : [];

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM gcash_payments gp $where");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1, ceil($total/$perPage));
$offset = ($pg-1)*$perPage;

$stmt = $pdo->prepare(
    "SELECT gp.*, c.name AS cname, b.billing_month, b.total AS bill_total
     FROM gcash_payments gp
     JOIN customers c ON gp.customer_id=c.id
     JOIN bills b ON gp.bill_id=b.id
     $where ORDER BY gp.submitted_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$gcashPayments = $stmt->fetchAll();

// Summary stats
$pendingCount  = (int)$pdo->query("SELECT COUNT(*) FROM gcash_payments WHERE status='Pending'")->fetchColumn();
$confirmedAmt  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM gcash_payments WHERE status='Confirmed'")->fetchColumn();
$pendingAmt    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM gcash_payments WHERE status='Pending'")->fetchColumn();

require_once 'includes/header.php';
renderHeader('GCash Payments', 'gcash_admin');
?>

<!-- Summary -->
<div class="stat-grid" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon" style="background:#d8f3dc">📱</div>
    <div>
      <div class="stat-val text-success"><?= fmt($confirmedAmt) ?></div>
      <div class="stat-label">Total Confirmed GCash</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#ffe8d6">⏳</div>
    <div>
      <div class="stat-val text-warning"><?= $pendingCount ?></div>
      <div class="stat-label">Pending Review</div>
      <div class="stat-sub"><?= fmt($pendingAmt) ?> total</div>
    </div>
  </div>
  <div class="stat-card" style="cursor:default">
    <div class="stat-icon" style="background:var(--info-bg)">🔢</div>
    <div>
      <div class="stat-val text-accent">09269340806</div>
      <div class="stat-label">GCash Number</div>
      <div class="stat-sub">AquaBill Coop. Inc.</div>
    </div>
  </div>
  <div class="stat-card" style="cursor:default">
    <div class="stat-icon" style="background:var(--info-bg)">🔗</div>
    <div>
      <div class="stat-val" style="font-size:14px;color:var(--accent)">
        <a href="gcash.php" target="_blank" style="color:var(--accent)">gcash.php</a>
      </div>
      <div class="stat-label">Customer Payment Page</div>
      <div class="stat-sub">Share this link with customers</div>
    </div>
  </div>
</div>

<!-- Filter tabs -->
<div class="tabs" style="margin-bottom:16px">
  <?php foreach (['All','Pending','Confirmed','Rejected'] as $f): ?>
  <a href="gcash_admin.php?filter=<?= urlencode($f) ?>" class="tab-btn <?= $filter===$f?'active':'' ?>"><?= $f ?></a>
  <?php endforeach; ?>
  <a href="gcash.php" target="_blank" class="btn btn-primary btn-sm" style="margin-left:auto">🔗 Customer Page</a>
</div>

<?php if ($pendingCount > 0 && $filter === 'Pending'): ?>
<div class="info-box" style="margin-bottom:16px">
  🔔 You have <strong><?= $pendingCount ?></strong> pending GCash payment<?= $pendingCount!=1?'s':'' ?> awaiting confirmation.
  Review each one and confirm or reject after verifying in your GCash app.
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Customer</th><th>Bill</th><th>Amount</th>
          <th>GCash Ref</th><th>Payer No</th><th>Submitted</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($gcashPayments as $gp): ?>
        <tr>
          <td><code style="font-size:11px;background:var(--info-bg);color:var(--info)"><?= h($gp['id']) ?></code></td>
          <td>
            <div class="fw-bold fs-sm"><?= h($gp['cname']) ?></div>
            <div class="fs-xs text-muted"><?= h($gp['customer_id']) ?></div>
          </td>
          <td>
            <div class="fs-sm"><?= h($gp['billing_month']) ?></div>
            <div class="fs-xs text-muted">Bill: <?= h($gp['bill_id']) ?> · Total: <?= fmt($gp['bill_total']) ?></div>
          </td>
          <td class="fw-bolder" style="color:var(--success)"><?= fmt($gp['amount']) ?></td>
          <td>
            <code style="font-size:12px;font-weight:700"><?= h($gp['gcash_ref']) ?></code>
          </td>
          <td class="fs-sm"><?= h($gp['payer_number']) ?></td>
          <td class="fs-xs text-muted"><?= date('M j, g:i A', strtotime($gp['submitted_at'])) ?></td>
          <td>
            <?php
            $badgeCls = ['Pending'=>'badge-unpaid','Confirmed'=>'badge-paid','Rejected'=>'badge-overdue'];
            echo '<span class="badge '.($badgeCls[$gp['status']]??'badge-unpaid').'">'.h($gp['status']).'</span>';
            ?>
          </td>
          <td>
            <?php if ($gp['status'] === 'Pending'): ?>
            <div style="display:flex;gap:5px">
              <form method="POST" style="display:inline" onsubmit="return confirm('Confirm this GCash payment of <?= fmt($gp['amount']) ?> from <?= h($gp['cname']) ?>?')">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="gcash_id" value="<?= h($gp['id']) ?>">
                <button type="submit" class="btn btn-sm btn-success-soft" style="font-weight:700">✅ Confirm</button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Reject this payment?')">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="gcash_id" value="<?= h($gp['id']) ?>">
                <button type="submit" class="btn btn-sm btn-danger-soft">✕ Reject</button>
              </form>
            </div>
            <?php elseif ($gp['status'] === 'Confirmed'): ?>
            <span class="fs-xs text-success">Confirmed <?= date('M j', strtotime($gp['confirmed_at'])) ?></span>
            <?php else: ?>
            <span class="fs-xs text-danger">Rejected</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($gcashPayments)): ?>
        <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--muted)">
          <?= $filter==='Pending' ? 'No pending GCash payments. ✅' : 'No records found.' ?>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?filter=<?= urlencode($filter) ?>&page=<?= $i ?>" class="page-btn <?= $i===$pg?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php renderFooter(); ?>
