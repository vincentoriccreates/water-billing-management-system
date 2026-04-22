<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: payments.php'); exit; }

$pdo = getDB();

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billId = post('bill_id');
    $amount = (float)post('amount');
    $method = post('method');
    $notes  = post('notes');

    if (!$billId || $amount <= 0) {
        setFlash('Please fill in all required fields.', 'error');
    } else {
        // Get bill
        $bs = $pdo->prepare("SELECT * FROM bills WHERE id=?");
        $bs->execute([$billId]);
        $bill = $bs->fetch();

        if (!$bill) {
            setFlash('Bill not found.', 'error');
        } else {
            $alreadyPaid = (float)$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE bill_id=?")->execute([$billId]) ? 0 : 0;
            $ps = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE bill_id=?");
            $ps->execute([$billId]);
            $alreadyPaid = (float)$ps->fetchColumn();
            $remaining   = $bill['total'] - $alreadyPaid;

            if ($amount > $remaining + 0.01) {
                setFlash('Amount exceeds remaining balance of ' . fmt($remaining), 'error');
            } else {
                $count    = (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn() + 1;
                $newId    = 'P' . str_pad($count, 3, '0', STR_PAD_LEFT);
                $rcptNo   = 'RCP-' . str_pad($count, 4, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare(
                    "INSERT INTO payments (id,bill_id,customer_id,amount,payment_date,method,receipt_no,notes)
                     VALUES (?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([$newId, $billId, $bill['customer_id'], $amount, date('Y-m-d'), $method, $rcptNo, $notes]);

                // Update bill status if fully paid
                $newPaid = $alreadyPaid + $amount;
                if ($newPaid >= $bill['total'] - 0.01) {
                    $pdo->prepare("UPDATE bills SET status='Paid' WHERE id=?")->execute([$billId]);
                }
                setFlash("Payment recorded! Receipt: $rcptNo");
                header("Location: payments.php?receipt=$newId");
                exit;
            }
        }
    }
    header('Location: payments.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$search  = get('search');
$pg      = max(1,(int)get('page','1'));
$perPage = 7;

$where  = []; $params = [];
if ($search) { $where[] = "(c.name LIKE ? OR p.receipt_no LIKE ?)"; $params = array_merge($params,["%$search%","%$search%"]); }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN customers c ON p.customer_id=c.id $whereSQL");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1,ceil($total/$perPage));
$offset = ($pg-1)*$perPage;

$stmt = $pdo->prepare(
    "SELECT p.*,c.name AS cname,b.billing_month FROM payments p
     JOIN customers c ON p.customer_id=c.id
     JOIN bills b ON p.bill_id=b.id
     $whereSQL ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Unpaid bills for dropdown
$unpaidBills = $pdo->query(
    "SELECT b.*,c.name AS cname,
     COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id=b.id),0) AS paid_amt
     FROM bills b JOIN customers c ON b.customer_id=c.id
     WHERE b.status!='Paid' ORDER BY b.due_date"
)->fetchAll();

// Receipt view
$receiptData = null;
if (get('receipt')) {
    $rs = $pdo->prepare("SELECT p.*,c.name AS cname,c.id AS cid,b.billing_month FROM payments p JOIN customers c ON p.customer_id=c.id JOIN bills b ON p.bill_id=b.id WHERE p.id=?");
    $rs->execute([get('receipt')]);
    $receiptData = $rs->fetch();
}

require_once 'includes/header.php';
renderHeader('Payments', 'payments');
?>

<!-- Receipt Modal -->
<?php if ($receiptData): ?>
<script>
var _receiptData = <?= json_encode([
  'receipt_no'    => $receiptData['receipt_no'],
  'payment_date'  => $receiptData['payment_date'],
  'cname'         => $receiptData['cname'],
  'cid'           => $receiptData['cid'],
  'billing_month' => $receiptData['billing_month'],
  'method'        => $receiptData['method'],
  'notes'         => $receiptData['notes'] ?? '',
  'amount'        => $receiptData['amount'],
], JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
</script>
<div class="modal-overlay" id="receipt-modal" style="display:flex" onclick="if(event.target===this)window.location='payments.php'">
  <div class="modal" style="max-width:460px">
    <!-- On-screen display (styled) -->
    <div style="background:#fff;color:#111;font-family:'Courier New',monospace;padding:24px;border-radius:10px;border:1px solid #ddd">
      <div style="text-align:center;border-bottom:2px dashed #ccc;padding-bottom:14px;margin-bottom:14px">
        <div style="font-size:18px;font-weight:900;letter-spacing:.5px;font-family:sans-serif">AQUABILL COOP. INC.</div>
        <div style="font-size:12px;color:#555;margin-top:2px">San Juan, Siquijor, Philippines</div>
        <div style="font-size:13px;font-weight:700;margin-top:8px;letter-spacing:2px;text-transform:uppercase">— Official Receipt —</div>
      </div>
      <div style="font-size:13px;line-height:2.1">
        <div style="display:flex;justify-content:space-between"><span><b>Receipt No:</b></span><span><?= h($receiptData['receipt_no']) ?></span></div>
        <div style="display:flex;justify-content:space-between"><span><b>Date:</b></span><span><?= h($receiptData['payment_date']) ?></span></div>
        <div style="border-top:1px dashed #ccc;margin:6px 0"></div>
        <div style="display:flex;justify-content:space-between"><span><b>Customer:</b></span><span><?= h($receiptData['cname']) ?></span></div>
        <div style="display:flex;justify-content:space-between"><span><b>Account No:</b></span><span><?= h($receiptData['cid']) ?></span></div>
        <div style="display:flex;justify-content:space-between"><span><b>Bill Period:</b></span><span><?= h($receiptData['billing_month']) ?></span></div>
        <div style="display:flex;justify-content:space-between"><span><b>Method:</b></span><span><?= h($receiptData['method']) ?></span></div>
        <?php if ($receiptData['notes']): ?><div style="display:flex;justify-content:space-between"><span><b>Notes:</b></span><span><?= h($receiptData['notes']) ?></span></div><?php endif; ?>
      </div>
      <div style="border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;margin:10px 0;padding:10px 0;text-align:center">
        <div style="font-size:30px;font-weight:900;color:#2d6a4f"><?= fmt($receiptData['amount']) ?></div>
      </div>
      <div style="text-align:center;font-size:11px;color:#777;margin-top:10px">
        Thank you for your payment!<br>This serves as your official receipt.<br>
        AquaBill Coop. Inc. — San Juan, Siquijor, Philippines
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="printReceipt(buildReceiptHtml(_receiptData))" class="btn btn-primary">🖨️ Print Receipt</button>
      <a href="payments.php" class="btn btn-outline">Close</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="toolbar">
  <form method="GET" class="toolbar-left">
    <input type="text" name="search" class="search-input" placeholder="🔍 Search payments..." value="<?= h($search) ?>">
    <button type="submit" class="btn btn-outline">Search</button>
  </form>
  <button class="btn btn-success" onclick="document.getElementById('pay-modal').style.display='flex'">+ Record Payment</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Receipt No</th><th>Customer</th><th>Bill Period</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><code style="background:var(--success-bg);color:var(--success)"><?= h($p['receipt_no']) ?></code></td>
          <td class="fw-bold"><?= h($p['cname']) ?></td>
          <td class="fs-sm text-muted"><?= h($p['billing_month']) ?></td>
          <td class="fw-bolder text-success"><?= fmt($p['amount']) ?></td>
          <td><span class="badge badge-staff"><?= h($p['method']) ?></span></td>
          <td class="fs-sm"><?= h($p['payment_date']) ?></td>
          <td><a href="payments.php?receipt=<?= h($p['id']) ?>" class="btn btn-sm btn-success-soft">Receipt</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($payments)): ?>
        <tr><td colspan="7" style="text-align:center;padding:24px" class="text-muted">No payments recorded yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <span class="total"><?= $total ?> records</span>
    <?php if ($pages>1): for ($i=1;$i<=$pages;$i++): ?>
    <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>" class="page-btn <?= $i===$pg?'active':'' ?>"><?= $i ?></a>
    <?php endfor; endif; ?>
  </div>
</div>

<!-- Record Payment Modal -->
<div class="modal-overlay" id="pay-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Record Payment</span>
      <button class="modal-close" onclick="document.getElementById('pay-modal').style.display='none'">×</button>
    </div>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Select Bill *</label>
        <select name="bill_id" id="bill_id" class="form-control" required onchange="updateBalance()">
          <option value="">Select unpaid bill...</option>
          <?php foreach ($unpaidBills as $b):
            $bal = $b['total'] - $b['paid_amt'];
          ?>
          <option value="<?= h($b['id']) ?>" data-balance="<?= $bal ?>">
            <?= h($b['cname']) ?> — <?= h($b['billing_month']) ?> — Balance: <?= fmt($bal) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="info-box" id="balance-box" style="display:none">
        Remaining Balance: <strong id="balance_preview">₱0.00</strong>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Amount (₱) *</label>
          <input type="number" step="0.01" name="amount" id="amount" class="form-control" placeholder="Enter amount" required min="1">
        </div>
        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <select name="method" class="form-control">
            <option>Cash</option><option>GCash</option><option>PayMaya</option>
            <option>Bank Transfer</option><option>Check</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Notes (optional)</label>
        <input type="text" name="notes" class="form-control" placeholder="Optional notes">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('pay-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-success">Record Payment</button>
      </div>
    </form>
  </div>
</div>

<script>
function updateBalance() {
  const sel = document.getElementById('bill_id');
  const opt = sel.options[sel.selectedIndex];
  const bal = parseFloat(opt?.dataset?.balance || 0);
  document.getElementById('balance_preview').textContent = '₱' + bal.toFixed(2);
  document.getElementById('balance-box').style.display = opt.value ? 'block' : 'none';
  const amtInput = document.getElementById('amount');
  if (amtInput) { amtInput.max = bal; amtInput.value = bal.toFixed(2); }
}
</script>

<?php renderFooter(); ?>
