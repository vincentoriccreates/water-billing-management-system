<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: readings.php'); exit; }

$pdo = getDB();

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $custId  = post('customer_id');
    $date    = post('reading_date');
    $current = (float)post('current_reading');

    // Get last reading for this customer
    $stmt = $pdo->prepare("SELECT current_reading FROM readings WHERE customer_id=? ORDER BY reading_date DESC LIMIT 1");
    $stmt->execute([$custId]);
    $prev = (float)($stmt->fetchColumn() ?: 0);

    if (!$custId || !$date || $current === '') {
        setFlash('Please fill in all required fields.', 'error');
    } elseif ($current < $prev) {
        setFlash('Current reading cannot be less than previous reading (' . fmtNum($prev) . ').', 'error');
    } else {
        $consumption = $current - $prev;
        $count = (int)$pdo->query("SELECT COUNT(*) FROM readings")->fetchColumn() + 1;
        $newId = 'R' . str_pad($count, 3, '0', STR_PAD_LEFT);
        $stmt  = $pdo->prepare("INSERT INTO readings (id,customer_id,reading_date,previous_reading,current_reading,consumption) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$newId, $custId, $date, $prev, $current, $consumption]);
        setFlash("Reading recorded! Consumption: {$consumption} m³");
    }
    header('Location: readings.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$search  = get('search');
$custFlt = get('customer');
$pg      = max(1,(int)get('page','1'));
$perPage = 8;

$where  = [];
$params = [];
if ($search) { $where[] = "c.name LIKE ?"; $params[] = "%$search%"; }
if ($custFlt) { $where[] = "r.customer_id=?"; $params[] = $custFlt; }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM readings r JOIN customers c ON r.customer_id=c.id $whereSQL");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1,ceil($total/$perPage));
$offset = ($pg-1)*$perPage;

$stmt = $pdo->prepare("SELECT r.*,c.name AS cname,c.meter_no FROM readings r JOIN customers c ON r.customer_id=c.id $whereSQL ORDER BY r.reading_date DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$readings = $stmt->fetchAll();

// Active customers for dropdown
$activeCustomers = $pdo->query("SELECT id,name,meter_no FROM customers WHERE status='Active' ORDER BY name")->fetchAll();

// Last reading per customer (for JS preview)
$lastReadings = [];
foreach ($activeCustomers as $ac) {
    $s = $pdo->prepare("SELECT current_reading FROM readings WHERE customer_id=? ORDER BY reading_date DESC LIMIT 1");
    $s->execute([$ac['id']]);
    $lastReadings[$ac['id']] = (float)($s->fetchColumn() ?: 0);
}
$lastReadingsJson = json_encode($lastReadings);

require_once 'includes/header.php';
renderHeader('Meter Readings', 'readings');
?>

<div class="toolbar">
  <form method="GET" class="toolbar-left">
    <input type="text" name="search" class="search-input" placeholder="🔍 Search by customer..." value="<?= h($search) ?>">
    <select name="customer" class="filter-select" onchange="this.form.submit()">
      <option value="">All Customers</option>
      <?php foreach ($activeCustomers as $ac): ?>
      <option value="<?= h($ac['id']) ?>" <?= $custFlt===$ac['id']?'selected':'' ?>><?= h($ac['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filter</button>
  </form>
  <button class="btn btn-primary" onclick="document.getElementById('add-reading-modal').style.display='flex'">+ Add Reading</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Customer</th><th>Meter</th><th>Date</th><th>Previous (m³)</th><th>Current (m³)</th><th>Consumption</th></tr>
      </thead>
      <tbody>
        <?php foreach ($readings as $r): ?>
        <tr>
          <td class="fw-bold"><?= h($r['cname']) ?></td>
          <td><code><?= h($r['meter_no']) ?></code></td>
          <td class="fs-sm"><?= h($r['reading_date']) ?></td>
          <td><code><?= fmtNum($r['previous_reading']) ?></code></td>
          <td><code><?= fmtNum($r['current_reading']) ?></code></td>
          <td>
            <span class="fw-bolder <?= $r['consumption']>30?'text-warning':'text-success' ?>">
              <?= fmtNum($r['consumption']) ?> m³
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($readings)): ?>
        <tr><td colspan="6" style="text-align:center;padding:24px" class="text-muted">No readings found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <span class="total"><?= $total ?> records</span>
    <?php if ($pages>1): for ($i=1;$i<=$pages;$i++): ?>
    <a href="?search=<?= urlencode($search) ?>&customer=<?= urlencode($custFlt) ?>&page=<?= $i ?>" class="page-btn <?= $i===$pg?'active':'' ?>"><?= $i ?></a>
    <?php endfor; endif; ?>
  </div>
</div>

<!-- Add Reading Modal -->
<div class="modal-overlay" id="add-reading-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Meter Reading</span>
      <button class="modal-close" onclick="document.getElementById('add-reading-modal').style.display='none'">×</button>
    </div>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Customer *</label>
        <select name="customer_id" id="customer_id_sel" class="form-control" required onchange="fillPrev()">
          <option value="">Select customer...</option>
          <?php foreach ($activeCustomers as $ac): ?>
          <option value="<?= h($ac['id']) ?>"><?= h($ac['name']) ?> (<?= h($ac['meter_no']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="info-box" id="prev-box" style="display:none">
        📟 Last Reading: <strong id="prev-val">0</strong> m³
      </div>
      <input type="hidden" name="previous_reading" id="prev_reading" value="0">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Reading Date *</label>
          <input type="date" name="reading_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Current Reading (m³) *</label>
          <input type="number" step="0.01" name="current_reading" id="current_reading" class="form-control" placeholder="e.g. 1280" required oninput="updateConsumption()">
        </div>
      </div>
      <div class="success-box" id="consumption_box" style="display:none">
        💧 Consumption: <strong id="consumption_preview">0 m³</strong>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('add-reading-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Record Reading</button>
      </div>
    </form>
  </div>
</div>

<script>
const lastReadings = <?= $lastReadingsJson ?>;
function fillPrev() {
  const id  = document.getElementById('customer_id_sel').value;
  const val = lastReadings[id] || 0;
  document.getElementById('prev_reading').value = val;
  document.getElementById('prev-val').textContent = val.toLocaleString();
  document.getElementById('prev-box').style.display = val > 0 ? 'block' : 'none';
  updateConsumption();
}
function updateConsumption() {
  const prev = parseFloat(document.getElementById('prev_reading').value) || 0;
  const curr = parseFloat(document.getElementById('current_reading').value) || 0;
  const c    = Math.max(0, curr - prev);
  document.getElementById('consumption_preview').textContent = c.toFixed(2) + ' m³';
  document.getElementById('consumption_box').style.display = curr ? 'block' : 'none';
}
</script>

<?php renderFooter(); ?>
