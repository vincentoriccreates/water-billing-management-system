<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: billing.php'); exit; }

$pdo = getDB();

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $custId    = post('customer_id');
    $month     = post('billing_month');
    $readingId = post('reading_id');
    $penalty   = (float)post('penalty', '0');

    if (!$custId || !$month || !$readingId) {
        setFlash('Please fill in all required fields.', 'error');
    } else {
        // Check duplicate
        $dup = $pdo->prepare("SELECT id FROM bills WHERE customer_id=? AND billing_month=?");
        $dup->execute([$custId, $month]);
        if ($dup->fetch()) {
            setFlash("Bill already exists for this customer and month.", 'error');
        } else {
            // Get reading
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

                $stmt = $pdo->prepare(
                    "INSERT INTO bills (id,customer_id,reading_id,billing_month,prev_reading,curr_reading,consumption,rate_per_cubic,base_charge,penalty,total,status,due_date)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([
                    $newId, $custId, $readingId, $month,
                    $reading['previous_reading'], $reading['current_reading'], $reading['consumption'],
                    RATE_PER_CUBIC, BASE_CHARGE, $penalty, $total, 'Unpaid', $due
                ]);
                setFlash("Bill generated: $newId — Total: " . fmt($total));
            }
        }
    }
    header('Location: billing.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$search = get('search');
$filter = get('filter', 'All');
$pg     = max(1,(int)get('page','1'));
$perPage= 7;

$where  = []; $params = [];
if ($search) { $where[] = "(c.name LIKE ? OR b.billing_month LIKE ?)"; $params = array_merge($params,["%$search%","%$search%"]); }
if ($filter !== 'All') { $where[] = "b.status=?"; $params[] = $filter; }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bills b JOIN customers c ON b.customer_id=c.id $whereSQL");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1,ceil($total/$perPage));
$offset = ($pg-1)*$perPage;

$stmt = $pdo->prepare(
    "SELECT b.*,c.name AS cname,c.id AS cid,
     COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.bill_id=b.id),0) AS paid_amount
     FROM bills b JOIN customers c ON b.customer_id=c.id
     $whereSQL ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// For generate form
$activeCustomers = $pdo->query("SELECT id,name FROM customers WHERE status='Active' ORDER BY name")->fetchAll();

// Reading options per customer (JSON for JS)
$allReadings = $pdo->query("SELECT r.*,c.name AS cname FROM readings r JOIN customers c ON r.customer_id=c.id ORDER BY r.reading_date DESC")->fetchAll();
$readingsJson = [];
foreach ($allReadings as $r) {
    $readingsJson[$r['customer_id']][] = [
        'id'          => $r['id'],
        'date'        => $r['reading_date'],
        'consumption' => $r['consumption'],
        'prev'        => $r['previous_reading'],
        'curr'        => $r['current_reading'],
    ];
}

// View bill
$viewBill = null;
if (get('view')) {
    $s = $pdo->prepare("SELECT b.*,c.name AS cname,c.address,c.meter_no,c.contact FROM bills b JOIN customers c ON b.customer_id=c.id WHERE b.id=?");
    $s->execute([get('view')]);
    $viewBill = $s->fetch();
    if ($viewBill) {
        $ps = $pdo->prepare("SELECT * FROM payments WHERE bill_id=?");
        $ps->execute([$viewBill['id']]);
        $viewBill['payments'] = $ps->fetchAll();
    }
}

require_once 'includes/header.php';
renderHeader('Billing', 'billing');
?>

<!-- Print bill statement -->
<?php if ($viewBill): ?>
<div class="modal-overlay" id="view-bill-modal" style="display:flex" onclick="if(event.target===this)window.location='billing.php'">
  <div class="modal" style="max-width:480px">
    <div style="background:#fff;color:#111;font-family:monospace;padding:24px;border-radius:10px" id="bill-print">
      <div style="text-align:center;border-bottom:2px dashed #ccc;padding-bottom:14px;margin-bottom:14px">
        <div style="font-size:30px">💧</div>
        <strong style="font-size:16px;font-family:sans-serif">AQUABILL WATER SERVICES</strong><br>
        <small>Barangay Water Utility · Official Billing Statement</small>
      </div>
      <div style="line-height:2;font-size:13px">
        <div><b>Account No:</b> <?= h($viewBill['customer_id']) ?></div>
        <div><b>Customer:</b> <?= h($viewBill['cname']) ?></div>
        <div><b>Address:</b> <?= h($viewBill['address']) ?></div>
        <div><b>Meter No:</b> <?= h($viewBill['meter_no']) ?></div>
        <div><b>Billing Period:</b> <?= h($viewBill['billing_month']) ?></div>
        <div><b>Due Date:</b> <?= h($viewBill['due_date']) ?></div>
      </div>
      <div style="border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;padding:10px 0;margin:10px 0;line-height:2;font-size:13px">
        <div style="display:flex;justify-content:space-between"><span>Previous Reading:</span><span><?= fmtNum($viewBill['prev_reading']) ?> m³</span></div>
        <div style="display:flex;justify-content:space-between"><span>Current Reading:</span><span><?= fmtNum($viewBill['curr_reading']) ?> m³</span></div>
        <div style="display:flex;justify-content:space-between;font-weight:700"><span>Consumption:</span><span><?= fmtNum($viewBill['consumption']) ?> m³</span></div>
        <div style="display:flex;justify-content:space-between"><span>Base Charge:</span><span><?= fmt($viewBill['base_charge']) ?></span></div>
        <div style="display:flex;justify-content:space-between"><span>Usage (<?= h($viewBill['consumption']) ?> m³ × ₱<?= RATE_PER_CUBIC ?>):</span><span><?= fmt($viewBill['consumption']*RATE_PER_CUBIC) ?></span></div>
        <?php if ($viewBill['penalty']>0): ?>
        <div style="display:flex;justify-content:space-between;color:red"><span>Penalty:</span><span><?= fmt($viewBill['penalty']) ?></span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-weight:900;font-size:16px;margin-top:6px"><span>TOTAL DUE:</span><span><?= fmt($viewBill['total']) ?></span></div>
        <?php
        $totalPaid = array_sum(array_column($viewBill['payments'],'amount'));
        $balance   = $viewBill['total'] - $totalPaid;
        ?>
        <div style="display:flex;justify-content:space-between;color:green"><span>Amount Paid:</span><span><?= fmt($totalPaid) ?></span></div>
        <div style="display:flex;justify-content:space-between;font-weight:900;color:<?= $balance>0?'red':'green' ?>"><span>BALANCE:</span><span><?= fmt($balance) ?></span></div>
      </div>
      <div style="text-align:center;font-size:11px;color:#777;margin-top:10px">
        Status: <strong style="color:<?= $viewBill['status']==='Paid'?'green':'red' ?>"><?= h($viewBill['status']) ?></strong><br>
        Thank you for your payment!
      </div>
    </div>
    <div class="modal-footer no-print">
      <button onclick="window.print()" class="btn btn-primary">🖨️ Print</button>
      <a href="billing.php" class="btn btn-outline">Close</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="toolbar">
  <form method="GET" class="toolbar-left">
    <input type="text" name="search" class="search-input" placeholder="🔍 Search bills..." value="<?= h($search) ?>">
    <select name="filter" class="filter-select" onchange="this.form.submit()">
      <option value="All"    <?= $filter==='All'?'selected':'' ?>>All Status</option>
      <option value="Unpaid" <?= $filter==='Unpaid'?'selected':'' ?>>Unpaid</option>
      <option value="Paid"   <?= $filter==='Paid'?'selected':'' ?>>Paid</option>
      <option value="Overdue"<?= $filter==='Overdue'?'selected':'' ?>>Overdue</option>
    </select>
    <button type="submit" class="btn btn-outline">Filter</button>
  </form>
  <button class="btn btn-primary" onclick="document.getElementById('gen-modal').style.display='flex'">+ Generate Bill</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Customer</th><th>Month</th><th>Usage</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Due</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($bills as $b): $balance = $b['total'] - $b['paid_amount']; ?>
        <tr>
          <td><div class="fw-bold"><?= h($b['cname']) ?></div><div class="fs-xs text-muted"><?= h($b['cid']) ?></div></td>
          <td class="fs-sm"><?= h($b['billing_month']) ?></td>
          <td class="fw-bold" style="color:var(--info)"><?= h($b['consumption']) ?> m³</td>
          <td class="fw-bolder"><?= fmt($b['total']) ?></td>
          <td class="text-success"><?= fmt($b['paid_amount']) ?></td>
          <td class="fw-bold <?= $balance>0?'text-danger':'text-success' ?>"><?= fmt($balance) ?></td>
          <td><span class="badge badge-<?= strtolower(h($b['status'])) ?>"><?= h($b['status']) ?></span></td>
          <td class="fs-xs"><?= h($b['due_date']) ?></td>
          <td><a href="billing.php?view=<?= h($b['id']) ?>" class="btn btn-sm btn-info-soft">View</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($bills)): ?>
        <tr><td colspan="9" style="text-align:center;padding:24px" class="text-muted">No bills found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <span class="total"><?= $total ?> records</span>
    <?php if ($pages>1): for ($i=1;$i<=$pages;$i++): ?>
    <a href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= $i ?>" class="page-btn <?= $i===$pg?'active':'' ?>"><?= $i ?></a>
    <?php endfor; endif; ?>
  </div>
</div>

<!-- Generate Bill Modal -->
<div class="modal-overlay" id="gen-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Generate Bill</span>
      <button class="modal-close" onclick="document.getElementById('gen-modal').style.display='none'">×</button>
    </div>
    <form method="POST">
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
const readingsData = <?= json_encode($readingsJson) ?>;
const BASE = <?= BASE_CHARGE ?>, RATE = <?= RATE_PER_CUBIC ?>;

function loadReadings() {
  const custId = document.getElementById('gen_customer').value;
  const sel    = document.getElementById('gen_reading');
  sel.innerHTML = '<option value="">Select reading...</option>';
  document.getElementById('bill_preview').style.display = 'none';
  if (!custId || !readingsData[custId]) return;
  readingsData[custId].forEach(r => {
    const opt = document.createElement('option');
    opt.value = r.id;
    opt.dataset.consumption = r.consumption;
    opt.textContent = `${r.date} — ${r.consumption} m³ (${r.prev}→${r.curr})`;
    sel.appendChild(opt);
  });
}

function updateBillPreview() {
  const sel = document.getElementById('gen_reading');
  const opt = sel.options[sel.selectedIndex];
  const c   = parseFloat(opt?.dataset?.consumption || 0);
  const pen = parseFloat(document.getElementById('penalty').value || 0);
  if (!c && !opt?.value) { document.getElementById('bill_preview').style.display='none'; return; }
  const total = BASE + (c * RATE) + pen;
  document.getElementById('bill_preview').innerHTML =
    `Base: ₱${BASE.toFixed(2)} &nbsp;|&nbsp; Usage (${c} m³ × ₱${RATE}): ₱${(c*RATE).toFixed(2)} &nbsp;|&nbsp; Penalty: ₱${pen.toFixed(2)} &nbsp;|&nbsp; <strong>Total: ₱${total.toFixed(2)}</strong>`;
  document.getElementById('bill_preview').style.display = 'block';
}
</script>

<?php renderFooter(); ?>
