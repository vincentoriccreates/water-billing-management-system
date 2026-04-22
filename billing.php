<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: billing.php'); exit; }

$pdo = getDB();

// ── POST Actions ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    // ── Generate Bill ──────────────────────────────────────────────────────────
    if ($action === 'generate') {
        $custId    = post('customer_id');
        $month     = post('billing_month');
        $readingId = post('reading_id');
        $penalty   = (float)post('penalty', '0');

        if (!$custId || !$month || !$readingId) {
            setFlash('Please fill in all required fields.', 'error');
        } else {
            $dup = $pdo->prepare("SELECT id FROM bills WHERE customer_id=? AND billing_month=?");
            $dup->execute([$custId, $month]);
            if ($dup->fetch()) {
                setFlash("Bill already exists for this customer and month.", 'error');
            } else {
                $rs = $pdo->prepare("SELECT * FROM readings WHERE id=?");
                $rs->execute([$readingId]);
                $reading = $rs->fetch();
                if (!$reading) {
                    setFlash('Reading not found.', 'error');
                } else {
                    $total = calcBill((float)$reading['consumption']) + $penalty;
                    $count = (int)$pdo->query("SELECT COUNT(*) FROM bills")->fetchColumn() + 1;
                    $newId = 'B' . str_pad($count, 3, '0', STR_PAD_LEFT);
                    $due   = date('Y-m-d', strtotime('+15 days'));
                    $pdo->prepare(
                        "INSERT INTO bills (id,customer_id,reading_id,billing_month,prev_reading,curr_reading,
                         consumption,rate_per_cubic,base_charge,penalty,total,status,due_date)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    )->execute([
                        $newId, $custId, $readingId, $month,
                        $reading['previous_reading'], $reading['current_reading'],
                        $reading['consumption'], RATE_PER_CUBIC, BASE_CHARGE, $penalty, $total, 'Unpaid', $due
                    ]);
                    setFlash("Bill generated: $newId — Total: " . fmt($total));
                }
            }
        }

    // ── Update Status ──────────────────────────────────────────────────────────
    } elseif ($action === 'update_status') {
        $billId    = post('bill_id');
        $newStatus = post('new_status');
        $note      = post('status_note');
        $allowed   = ['Unpaid','Paid','Overdue','Waived','Disputed'];
        if (!$billId || !in_array($newStatus, $allowed)) {
            setFlash('Invalid status.', 'error');
        } else {
            $pdo->prepare("UPDATE bills SET status=? WHERE id=?")->execute([$newStatus, $billId]);
            // If marking Paid manually, record a zero-balance note in payments log
            if ($newStatus === 'Paid') {
                $bill = $pdo->prepare("SELECT * FROM bills WHERE id=?")->execute([$billId]) ? null : null;
                $bStmt = $pdo->prepare("SELECT * FROM bills WHERE id=?");
                $bStmt->execute([$billId]);
                $bill = $bStmt->fetch();
                if ($bill) {
                    $alreadyPaid = (float)$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE bill_id=?")->execute([$billId]) ? 0 : 0;
                    $apStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE bill_id=?");
                    $apStmt->execute([$billId]);
                    $alreadyPaid = (float)$apStmt->fetchColumn();
                    $remaining   = $bill['total'] - $alreadyPaid;
                    if ($remaining > 0.01) {
                        // Log an administrative payment
                        $count  = (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn() + 1;
                        $rcptNo = 'ADM-' . str_pad($count, 4, '0', STR_PAD_LEFT);
                        $pdo->prepare(
                            "INSERT INTO payments (id,bill_id,customer_id,amount,payment_date,method,receipt_no,notes)
                             VALUES (?,?,?,?,?,?,?,?)"
                        )->execute([
                            'P'.str_pad($count,3,'0',STR_PAD_LEFT),
                            $billId, $bill['customer_id'], $remaining,
                            date('Y-m-d'), 'Administrative',
                            $rcptNo, $note ?: 'Status manually set to Paid'
                        ]);
                    }
                }
            }
            setFlash("Bill $billId status updated to $newStatus." . ($note ? " Note: $note" : ''));
        }

    // ── Bulk Status Update ─────────────────────────────────────────────────────
    } elseif ($action === 'bulk_status') {
        $ids       = $_POST['bill_ids'] ?? [];
        $newStatus = post('bulk_new_status');
        $allowed   = ['Unpaid','Paid','Overdue','Waived','Disputed'];
        if (empty($ids) || !in_array($newStatus, $allowed)) {
            setFlash('Select at least one bill and a valid status.', 'error');
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$newStatus], $ids);
            $pdo->prepare("UPDATE bills SET status=? WHERE id IN ($placeholders)")->execute($params);
            setFlash(count($ids) . " bill(s) updated to $newStatus.");
        }

    // ── Add Penalty to single bill ─────────────────────────────────────────────
    } elseif ($action === 'add_penalty') {
        $billId  = post('bill_id');
        $penalty = (float)post('extra_penalty');
        if ($billId && $penalty > 0) {
            $pdo->prepare("UPDATE bills SET penalty=penalty+?, total=total+? WHERE id=?")
                ->execute([$penalty, $penalty, $billId]);
            setFlash("Penalty of " . fmt($penalty) . " added to bill $billId.");
        }

    // ── Waive penalty ─────────────────────────────────────────────────────────
    } elseif ($action === 'waive_penalty') {
        $billId = post('bill_id');
        $bStmt  = $pdo->prepare("SELECT penalty FROM bills WHERE id=?");
        $bStmt->execute([$billId]);
        $b = $bStmt->fetch();
        if ($b && $b['penalty'] > 0) {
            $pdo->prepare("UPDATE bills SET total=total-penalty, penalty=0 WHERE id=?")
                ->execute([$billId]);
            setFlash("Penalty waived for bill $billId.");
        }

    // ── Set Due Date ───────────────────────────────────────────────────────────
    } elseif ($action === 'set_due_date') {
        $billId  = post('bill_id');
        $dueDate = post('due_date');
        if ($billId && $dueDate) {
            $pdo->prepare("UPDATE bills SET due_date=? WHERE id=?")->execute([$dueDate, $billId]);
            setFlash("Due date updated for bill $billId.");
        }
    }

    $redirect = post('redirect') ?: 'billing.php';
    header("Location: $redirect");
    exit;
}

// Auto-mark overdue
$pdo->exec("UPDATE bills SET status='Overdue' WHERE status='Unpaid' AND due_date < CURDATE()");

// ── Fetch with filters ────────────────────────────────────────────────────────
$search = get('search');
$filter = get('filter', 'All');
$month  = get('month');
$pg     = max(1, (int)get('page', '1'));
$perPage = 10;

$where  = []; $params = [];
if ($search) {
    $where[]  = "(c.name LIKE ? OR b.billing_month LIKE ? OR b.id LIKE ?)";
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($filter !== 'All') { $where[] = "b.status=?"; $params[] = $filter; }
if ($month)            { $where[] = "b.billing_month LIKE ?"; $params[] = "%$month%"; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bills b JOIN customers c ON b.customer_id=c.id $whereSQL");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1, ceil($total / $perPage));
$offset = ($pg - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT b.*, c.name AS cname, c.id AS cid, c.contact,
     COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.bill_id=b.id),0) AS paid_amount
     FROM bills b JOIN customers c ON b.customer_id=c.id
     $whereSQL ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// For generate modal
$activeCustomers = $pdo->query("SELECT id,name FROM customers WHERE status='Active' ORDER BY name")->fetchAll();
$allReadings     = $pdo->query("SELECT r.*,c.name AS cname FROM readings r JOIN customers c ON r.customer_id=c.id ORDER BY r.reading_date DESC")->fetchAll();
$readingsByCustomer = [];
foreach ($allReadings as $r) {
    $readingsByCustomer[$r['customer_id']][] = $r;
}

// Summary bar counts
$statusCounts = $pdo->query(
    "SELECT status, COUNT(*) AS cnt, SUM(total) AS amt FROM bills GROUP BY status"
)->fetchAll(PDO::FETCH_ASSOC);
$sCounts = ['Paid'=>0,'Unpaid'=>0,'Overdue'=>0,'Waived'=>0,'Disputed'=>0];
$sAmounts= ['Paid'=>0,'Unpaid'=>0,'Overdue'=>0,'Waived'=>0,'Disputed'=>0];
foreach ($statusCounts as $sc) {
    $sCounts[$sc['status']]  = (int)$sc['cnt'];
    $sAmounts[$sc['status']] = (float)$sc['amt'];
}

// View bill
$viewBill = null;
if (get('view')) {
    $s = $pdo->prepare(
        "SELECT b.*, c.name AS cname, c.address, c.meter_no, c.contact, c.id AS cid
         FROM bills b JOIN customers c ON b.customer_id=c.id WHERE b.id=?"
    );
    $s->execute([get('view')]);
    $viewBill = $s->fetch();
    if ($viewBill) {
        $ps = $pdo->prepare("SELECT * FROM payments WHERE bill_id=? ORDER BY payment_date");
        $ps->execute([get('view')]);
        $viewBill['payments'] = $ps->fetchAll();
    }
}

// Status colors
$statusColors = [
    'Paid'     => ['badge-paid',     'var(--success)', 'var(--success-bg)'],
    'Unpaid'   => ['badge-unpaid',   'var(--warning)', 'var(--warning-bg)'],
    'Overdue'  => ['badge-overdue',  'var(--danger)',  'var(--danger-bg)'],
    'Waived'   => ['badge-staff',    'var(--info)',    'var(--info-bg)'],
    'Disputed' => ['badge-disconnected','var(--danger)','var(--danger-bg)'],
];

require_once 'includes/header.php';
renderHeader('Billing', 'billing');
?>

<?php if ($viewBill):
    $totalPaid = array_sum(array_column($viewBill['payments'],'amount'));
    $balance   = $viewBill['total'] - $totalPaid;
?>
<script>
var _billData = <?= json_encode([
  'id'            => $viewBill['id'],
  'billing_month' => $viewBill['billing_month'],
  'due_date'      => $viewBill['due_date'],
  'cname'         => $viewBill['cname'],
  'cid'           => $viewBill['cid'],
  'meter_no'      => $viewBill['meter_no'],
  'address'       => $viewBill['address'] ?? '',
  'prev_reading'  => $viewBill['prev_reading'],
  'curr_reading'  => $viewBill['curr_reading'],
  'consumption'   => $viewBill['consumption'],
  'base_charge'   => $viewBill['base_charge'],
  'rate_per_cubic'=> $viewBill['rate_per_cubic'],
  'penalty'       => $viewBill['penalty'],
  'total'         => $viewBill['total'],
  'paid_amount'   => $totalPaid,
  'status'        => $viewBill['status'],
], JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
</script>
<!-- BILL DETAIL MODAL -->
<div class="modal-overlay" id="view-bill-modal" style="display:flex" onclick="if(event.target===this)window.location='billing.php'">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <span class="modal-title">Bill <?= h($viewBill['id']) ?></span>
      <a href="billing.php" class="modal-close">×</a>
    </div>

    <!-- Customer info strip -->
    <div style="background:var(--surface-alt);border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;gap:16px;flex-wrap:wrap">
      <div><div class="fs-xs text-muted">Customer</div><div class="fw-bold"><?= h($viewBill['cname']) ?></div></div>
      <div><div class="fs-xs text-muted">Account</div><div><code><?= h($viewBill['cid']) ?></code></div></div>
      <div><div class="fs-xs text-muted">Meter</div><div><code><?= h($viewBill['meter_no']) ?></code></div></div>
      <div><div class="fs-xs text-muted">Contact</div><div class="fs-sm"><?= h($viewBill['contact']) ?></div></div>
    </div>

    <!-- Bill breakdown -->
    <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px" id="bill-print-area">
      <div style="background:var(--accent);color:#fff;padding:10px 16px;font-weight:800;font-size:13px">
        💧 <?= h($viewBill['billing_month']) ?> · <?= h($viewBill['id']) ?>
      </div>
      <div style="padding:14px 16px">
        <div class="info-row"><span>Previous Reading</span><span class="fw-bold"><?= fmtNum($viewBill['prev_reading']) ?> m³</span></div>
        <div class="info-row"><span>Current Reading</span><span class="fw-bold"><?= fmtNum($viewBill['curr_reading']) ?> m³</span></div>
        <div class="info-row"><span>Consumption</span><span class="fw-bold text-info"><?= fmtNum($viewBill['consumption']) ?> m³</span></div>
        <div class="info-row"><span>Base Charge</span><span><?= fmt($viewBill['base_charge']) ?></span></div>
        <div class="info-row"><span>Usage (<?= h($viewBill['consumption']) ?> m³ × ₱<?= RATE_PER_CUBIC ?>)</span><span><?= fmt($viewBill['consumption'] * $viewBill['rate_per_cubic']) ?></span></div>
        <?php if ($viewBill['penalty'] > 0): ?>
        <div class="info-row"><span style="color:var(--danger)">Penalty</span><span style="color:var(--danger)"><?= fmt($viewBill['penalty']) ?></span></div>
        <?php endif; ?>
        <div class="info-row" style="border-top:2px solid var(--border);margin-top:6px;padding-top:8px">
          <span class="fw-bold" style="font-size:15px">TOTAL DUE</span>
          <span class="fw-bolder" style="font-size:17px;color:var(--accent)"><?= fmt($viewBill['total']) ?></span>
        </div>
        <div class="info-row"><span class="text-success">Amount Paid</span><span class="fw-bold text-success"><?= fmt($totalPaid) ?></span></div>
        <div class="info-row" style="border-top:2px solid var(--border);margin-top:4px;padding-top:8px">
          <span class="fw-bold">BALANCE</span>
          <span class="fw-bolder" style="font-size:16px;color:<?= $balance>0?'var(--danger)':'var(--success)' ?>"><?= fmt($balance) ?></span>
        </div>
        <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center">
          <span class="fs-xs text-muted">Due: <?= h($viewBill['due_date']) ?></span>
          <span class="badge <?= $statusColors[$viewBill['status']][0] ?? 'badge-unpaid' ?>"><?= h($viewBill['status']) ?></span>
        </div>
      </div>
    </div>

    <!-- Payment history in modal -->
    <?php if ($viewBill['payments']): ?>
    <div style="margin-bottom:16px">
      <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.8px">Payment History</div>
      <?php foreach ($viewBill['payments'] as $p): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px">
        <div>
          <code style="font-size:10px;background:var(--success-bg);color:var(--success)"><?= h($p['receipt_no']) ?></code>
          <span class="text-muted" style="margin-left:6px;font-size:11px"><?= h($p['payment_date']) ?> · <?= h($p['method']) ?></span>
        </div>
        <span class="fw-bold text-success"><?= fmt($p['amount']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── STATUS ACTIONS ── -->
    <div style="background:var(--surface-alt);border-radius:10px;padding:14px;margin-bottom:14px">
      <div style="font-size:12px;font-weight:800;color:var(--muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:.8px">Update Payment Status</div>
      <form method="POST" action="billing.php?view=<?= h($viewBill['id']) ?>">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="bill_id" value="<?= h($viewBill['id']) ?>">
        <input type="hidden" name="redirect" value="billing.php">
        <div class="form-row" style="margin-bottom:10px">
          <div class="form-group" style="margin:0">
            <label class="form-label">New Status</label>
            <select name="new_status" class="form-control">
              <?php foreach (['Unpaid','Paid','Overdue','Waived','Disputed'] as $s): ?>
              <option value="<?= $s ?>" <?= $viewBill['status']===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Note (optional)</label>
            <input type="text" name="status_note" class="form-control" placeholder="Reason for change...">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">✅ Update Status</button>
      </form>
    </div>

    <!-- ── PENALTY ACTIONS ── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
      <form method="POST" action="billing.php?view=<?= h($viewBill['id']) ?>">
        <input type="hidden" name="action" value="add_penalty">
        <input type="hidden" name="bill_id" value="<?= h($viewBill['id']) ?>">
        <input type="hidden" name="redirect" value="billing.php?view=<?= h($viewBill['id']) ?>">
        <div class="form-group" style="margin-bottom:8px">
          <label class="form-label">Add Penalty (₱)</label>
          <input type="number" step="0.01" name="extra_penalty" class="form-control" placeholder="50.00" min="1" required>
        </div>
        <button type="submit" class="btn btn-danger btn-sm" style="width:100%">+ Add Penalty</button>
      </form>
      <div>
        <div style="margin-bottom:8px">
          <div class="form-label">Current Penalty</div>
          <div class="fw-bolder" style="font-size:18px;color:var(--danger);padding:9px 12px;background:var(--danger-bg);border-radius:8px"><?= fmt($viewBill['penalty']) ?></div>
        </div>
        <?php if ($viewBill['penalty'] > 0): ?>
        <form method="POST" action="billing.php?view=<?= h($viewBill['id']) ?>">
          <input type="hidden" name="action" value="waive_penalty">
          <input type="hidden" name="bill_id" value="<?= h($viewBill['id']) ?>">
          <input type="hidden" name="redirect" value="billing.php?view=<?= h($viewBill['id']) ?>">
          <button type="submit" class="btn btn-outline btn-sm" style="width:100%" onclick="return confirm('Waive penalty of <?= fmt($viewBill['penalty']) ?>?')">
            🙏 Waive Penalty
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── DUE DATE ── -->
    <form method="POST" action="billing.php?view=<?= h($viewBill['id']) ?>" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:14px;flex-wrap:wrap">
      <input type="hidden" name="action" value="set_due_date">
      <input type="hidden" name="bill_id" value="<?= h($viewBill['id']) ?>">
      <input type="hidden" name="redirect" value="billing.php?view=<?= h($viewBill['id']) ?>">
      <div class="form-group" style="margin:0;flex:1">
        <label class="form-label">Update Due Date</label>
        <input type="date" name="due_date" class="form-control" value="<?= h($viewBill['due_date']) ?>">
      </div>
      <button type="submit" class="btn btn-outline btn-sm" style="flex-shrink:0;margin-bottom:0">📅 Set Date</button>
    </form>

    <!-- Quick pay shortcut -->
    <?php if ($balance > 0.01 && $viewBill['status'] !== 'Paid'): ?>
    <a href="payments.php" class="btn btn-success" style="width:100%;justify-content:center">
      💳 Record Payment (Balance: <?= fmt($balance) ?>)
    </a>
    <?php endif; ?>

    <div class="modal-footer" style="margin-top:12px">
      <button onclick="printReceipt(buildBillHtml(_billData))" class="btn btn-outline btn-sm">🖨️ Print Bill</button>
      <a href="billing.php" class="btn btn-outline btn-sm">✕ Close</a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── STATUS SUMMARY BAR ─────────────────────────────────────────────────── -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px">
  <?php
  $filterLinks = [
    'All'      => ['All Bills',  count($bills)+(int)$total, 'var(--text)',    'var(--surface-alt)'],
    'Unpaid'   => ['Unpaid',     $sCounts['Unpaid'],  'var(--warning)', 'var(--warning-bg)'],
    'Paid'     => ['Paid',       $sCounts['Paid'],    'var(--success)', 'var(--success-bg)'],
    'Overdue'  => ['Overdue',    $sCounts['Overdue'], 'var(--danger)',  'var(--danger-bg)'],
    'Waived'   => ['Waived',     $sCounts['Waived'],  'var(--info)',    'var(--info-bg)'],
    'Disputed' => ['Disputed',   $sCounts['Disputed'],'var(--danger)',  'var(--danger-bg)'],
  ];
  foreach ($filterLinks as $key => [$label, $cnt, $col, $bg]):
    $isActive = $filter === $key;
  ?>
  <a href="billing.php?filter=<?= urlencode($key) ?>&search=<?= urlencode($search) ?>"
     style="display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:20px;
            background:<?= $isActive?$col:$bg ?>;color:<?= $isActive?'#fff':$col ?>;
            text-decoration:none;font-weight:700;font-size:13px;border:2px solid <?= $isActive?$col:'transparent' ?>;
            transition:all .2s">
    <span><?= $label ?></span>
    <span style="background:<?= $isActive?'rgba(255,255,255,.25)':$col ?>;color:<?= $isActive?'#fff':'#fff' ?>;
                 border-radius:10px;padding:1px 8px;font-size:11px"><?= $cnt ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── TOOLBAR ────────────────────────────────────────────────────────────── -->
<div class="toolbar">
  <form method="GET" class="toolbar-left" id="filter-form">
    <input type="hidden" name="filter" value="<?= h($filter) ?>">
    <input type="text" name="search" class="search-input" placeholder="🔍 Search name, month, bill ID..." value="<?= h($search) ?>">
    <input type="month" name="month" class="filter-select" value="<?= h($month) ?>" title="Filter by month" style="width:auto">
    <button type="submit" class="btn btn-outline">Filter</button>
    <?php if ($search || $month || $filter !== 'All'): ?>
    <a href="billing.php" class="btn btn-outline" style="color:var(--danger)">✕ Clear</a>
    <?php endif; ?>
  </form>
  <button class="btn btn-primary" onclick="document.getElementById('gen-modal').style.display='flex'">+ Generate Bill</button>
</div>

<!-- ── BULK ACTION BAR ────────────────────────────────────────────────────── -->
<form method="POST" id="bulk-form">
  <input type="hidden" name="action" value="bulk_status">
  <div id="bulk-bar" style="display:none;background:var(--accent);color:#fff;border-radius:10px;padding:10px 16px;margin-bottom:14px;align-items:center;gap:14px;flex-wrap:wrap">
    <span id="bulk-count" class="fw-bold fs-sm">0 selected</span>
    <select name="bulk_new_status" class="form-control" style="width:auto;padding:6px 10px;background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.3)">
      <option value="">— Set Status To —</option>
      <option value="Paid">Paid</option>
      <option value="Unpaid">Unpaid</option>
      <option value="Overdue">Overdue</option>
      <option value="Waived">Waived</option>
      <option value="Disputed">Disputed</option>
    </select>
    <button type="submit" class="btn btn-sm" style="background:#fff;color:var(--accent)" onclick="return confirmBulk()">✅ Apply to Selected</button>
    <button type="button" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" onclick="clearSelection()">✕ Clear</button>
  </div>

<!-- ── BILLS TABLE ─────────────────────────────────────────────────────────── -->
<div class="card">
  <div class="table-wrap">
    <table id="bills-table">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="select-all" onchange="toggleAll(this)" style="width:16px;height:16px;cursor:pointer"></th>
          <th>Customer</th><th>Month</th><th>Usage</th><th>Total</th>
          <th>Paid</th><th>Balance</th><th>Status</th><th>Due</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bills as $b):
          $balance = $b['total'] - $b['paid_amount'];
          [$badgeCls] = $statusColors[$b['status']] ?? ['badge-unpaid'];
          $isOverdue   = $b['status'] === 'Overdue';
          $rowBg       = $isOverdue ? 'background:rgba(193,18,31,.04)' : '';
        ?>
        <tr style="border-bottom:1px solid var(--border);<?= $rowBg ?>">
          <td>
            <input type="checkbox" name="bill_ids[]" value="<?= h($b['id']) ?>"
                   class="bill-checkbox" style="width:16px;height:16px;cursor:pointer"
                   onchange="updateBulkBar()">
          </td>
          <td>
            <div class="fw-bold" style="font-size:13px"><?= h($b['cname']) ?></div>
            <div class="fs-xs text-muted"><?= h($b['cid']) ?> · <?= h($b['contact']) ?></div>
          </td>
          <td class="fs-sm"><?= h($b['billing_month']) ?></td>
          <td style="color:var(--info);font-weight:700"><?= h($b['consumption']) ?> m³</td>
          <td class="fw-bolder"><?= fmt($b['total']) ?></td>
          <td class="text-success fw-bold"><?= fmt($b['paid_amount']) ?></td>
          <td>
            <span class="fw-bolder" style="color:<?= $balance>0?'var(--danger)':'var(--success)' ?>">
              <?= fmt($balance) ?>
            </span>
          </td>
          <td>
            <!-- Inline status quick-change dropdown -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="bill_id" value="<?= h($b['id']) ?>">
              <input type="hidden" name="redirect" value="billing.php?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&page=<?= $pg ?>">
              <select name="new_status" class="status-dropdown"
                      onchange="this.form.submit()"
                      style="border:none;border-radius:16px;padding:3px 8px;font-size:11px;font-weight:700;cursor:pointer;
                             background:<?= $statusColors[$b['status']][2] ?? 'var(--surface-alt)' ?>;
                             color:<?= $statusColors[$b['status']][1] ?? 'var(--text)' ?>">
                <?php foreach (['Unpaid','Paid','Overdue','Waived','Disputed'] as $s): ?>
                <option value="<?= $s ?>" <?= $b['status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td style="font-size:11px;<?= strtotime($b['due_date'])<time()&&$b['status']!=='Paid'?'color:var(--danger);font-weight:700':'' ?>">
            <?= h($b['due_date']) ?>
          </td>
          <td>
            <div style="display:flex;gap:5px">
              <a href="billing.php?view=<?= h($b['id']) ?>" class="btn btn-sm btn-info-soft" title="View & Manage">🔍</a>
              <?php if ($balance > 0.01 && $b['status'] !== 'Paid'): ?>
              <a href="payments.php" class="btn btn-sm btn-success-soft" title="Record Payment">💳</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($bills)): ?>
        <tr><td colspan="10" style="text-align:center;padding:32px" class="text-muted">No bills found for the selected filters.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:8px">
    <div class="fs-xs text-muted">Showing <?= count($bills) ?> of <?= $total ?> bills</div>
    <div class="pagination" style="margin:0">
      <?php if ($pages > 1):
        $qs = http_build_query(['filter'=>$filter,'search'=>$search,'month'=>$month]);
        for ($i=1;$i<=$pages;$i++): ?>
      <a href="billing.php?<?= $qs ?>&page=<?= $i ?>" class="page-btn <?= $i===$pg?'active':'' ?>"><?= $i ?></a>
      <?php endfor; endif; ?>
    </div>
  </div>
</div>
</form><!-- /bulk-form -->

<!-- ── GENERATE BILL MODAL ────────────────────────────────────────────────── -->
<div class="modal-overlay" id="gen-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Generate Bill</span>
      <button class="modal-close" onclick="document.getElementById('gen-modal').style.display='none'">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="generate">
      <div class="form-group">
        <label class="form-label">Customer *</label>
        <select name="customer_id" id="gen_customer" class="form-control" required onchange="loadReadings()">
          <option value="">Select customer...</option>
          <?php foreach ($activeCustomers as $ac): ?>
          <option value="<?= h($ac['id']) ?>"><?= h($ac['name']) ?> (<?= h($ac['id']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Billing Month * <span class="fs-xs text-muted">(e.g. January 2025)</span></label>
        <input type="text" name="billing_month" class="form-control" placeholder="January 2025" required>
      </div>
      <div class="form-group">
        <label class="form-label">Meter Reading *</label>
        <select name="reading_id" id="gen_reading" class="form-control" required onchange="updateBillPreview()">
          <option value="">Select customer first...</option>
        </select>
      </div>
      <div class="info-box" id="bill_preview" style="display:none"></div>
      <div class="form-group">
        <label class="form-label">Penalty / Late Fee (₱)</label>
        <input type="number" step="0.01" name="penalty" id="penalty" class="form-control" value="0" oninput="updateBillPreview()">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('gen-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Generate Bill</button>
      </div>
    </form>
  </div>
</div>

<script>
const readingsData = <?= json_encode($readingsByCustomer) ?>;
const BASE = <?= BASE_CHARGE ?>, RATE = <?= RATE_PER_CUBIC ?>;
// Expose to app.js helpers
window.__readingsData = readingsData;
window.__baseCharge   = BASE;
window.__rate         = RATE;

function loadReadings() {
  const custId = document.getElementById('gen_customer').value;
  const sel    = document.getElementById('gen_reading');
  sel.innerHTML = '<option value="">Select reading...</option>';
  document.getElementById('bill_preview').style.display = 'none';
  if (!custId || !readingsData[custId]) return;
  readingsData[custId].forEach(r => {
    const opt          = document.createElement('option');
    opt.value          = r.id;
    opt.dataset.consumption = r.consumption;
    opt.textContent    = `${r.reading_date} — ${r.consumption} m³ (${r.previous_reading}→${r.current_reading})`;
    sel.appendChild(opt);
  });
}

function updateBillPreview() {
  const sel = document.getElementById('gen_reading');
  const opt = sel.options[sel.selectedIndex];
  const c   = parseFloat(opt?.dataset?.consumption || 0);
  const pen = parseFloat(document.getElementById('penalty').value || 0);
  if (!opt?.value) { document.getElementById('bill_preview').style.display='none'; return; }
  const total = BASE + (c * RATE) + pen;
  const el = document.getElementById('bill_preview');
  el.innerHTML = `Base ₱${BASE.toFixed(2)} + Usage (${c}m³ × ₱${RATE}) ₱${(c*RATE).toFixed(2)} + Penalty ₱${pen.toFixed(2)} = <strong>Total ₱${total.toFixed(2)}</strong>`;
  el.style.display = 'block';
}

// Bulk selection
function toggleAll(cb) {
  document.querySelectorAll('.bill-checkbox').forEach(c => { c.checked = cb.checked; });
  updateBulkBar();
}
function updateBulkBar() {
  const checked = document.querySelectorAll('.bill-checkbox:checked').length;
  const bar     = document.getElementById('bulk-bar');
  document.getElementById('bulk-count').textContent = checked + ' selected';
  bar.style.display = checked > 0 ? 'flex' : 'none';
  document.getElementById('select-all').indeterminate =
    checked > 0 && checked < document.querySelectorAll('.bill-checkbox').length;
}
function clearSelection() {
  document.querySelectorAll('.bill-checkbox, #select-all').forEach(c => c.checked = false);
  updateBulkBar();
}
function confirmBulk() {
  const n   = document.querySelectorAll('.bill-checkbox:checked').length;
  const st  = document.querySelector('[name="bulk_new_status"]').value;
  if (!st)  { alert('Please select a status to apply.'); return false; }
  return confirm(`Update ${n} bill(s) to "${st}"?`);
}
</script>

<?php renderFooter(); ?>
