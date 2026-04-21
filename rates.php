<?php
require_once 'includes/functions.php';
requireLogin();
if (!isAdmin()) { header('Location: dashboard.php'); exit; }
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: rates.php'); exit; }

$pdo = getDB();

// Ensure rates table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS billing_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(80) NOT NULL,
        min_cubic DECIMAL(10,2) NOT NULL DEFAULT 0,
        max_cubic DECIMAL(10,2) DEFAULT NULL COMMENT 'NULL means unlimited',
        rate_per_cubic DECIMAL(8,2) NOT NULL,
        base_charge DECIMAL(8,2) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Seed default rates if empty
$count = (int)$pdo->query("SELECT COUNT(*) FROM billing_rates")->fetchColumn();
if ($count === 0) {
    $pdo->exec("
        INSERT INTO billing_rates (label, min_cubic, max_cubic, rate_per_cubic, base_charge) VALUES
        ('Lifeline (0–10 m³)',     0,  10, 20.00, 120.00),
        ('Basic (11–20 m³)',      11,  20, 30.00,   0.00),
        ('Standard (21–40 m³)',   21,  40, 35.00,   0.00),
        ('Commercial (41–100 m³)',41, 100, 45.00,   0.00),
        ('Industrial (>100 m³)', 101, NULL,55.00,   0.00)
    ");
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'add') {
        $label   = post('label');
        $minC    = (float)post('min_cubic');
        $maxC    = post('max_cubic') !== '' ? (float)post('max_cubic') : null;
        $rate    = (float)post('rate_per_cubic');
        $base    = (float)post('base_charge');
        if (!$label || $rate <= 0) { setFlash('Label and rate are required.','error'); }
        else {
            $pdo->prepare("INSERT INTO billing_rates (label,min_cubic,max_cubic,rate_per_cubic,base_charge) VALUES (?,?,?,?,?)")
                ->execute([$label, $minC, $maxC, $rate, $base]);
            setFlash('Rate tier added!');
        }
    } elseif ($action === 'edit') {
        $id    = (int)post('id');
        $label = post('label');
        $minC  = (float)post('min_cubic');
        $maxC  = post('max_cubic') !== '' ? (float)post('max_cubic') : null;
        $rate  = (float)post('rate_per_cubic');
        $base  = (float)post('base_charge');
        $pdo->prepare("UPDATE billing_rates SET label=?,min_cubic=?,max_cubic=?,rate_per_cubic=?,base_charge=? WHERE id=?")
            ->execute([$label, $minC, $maxC, $rate, $base, $id]);
        setFlash('Rate updated!');
    } elseif ($action === 'toggle') {
        $id = (int)post('id');
        $pdo->prepare("UPDATE billing_rates SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        setFlash('Rate status toggled.');
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        $pdo->prepare("DELETE FROM billing_rates WHERE id=?")->execute([$id]);
        setFlash('Rate tier deleted.');
    }
    header('Location: rates.php');
    exit;
}

$rates   = $pdo->query("SELECT * FROM billing_rates ORDER BY min_cubic ASC")->fetchAll();
$editRate= null;
if (get('edit')) {
    $s = $pdo->prepare("SELECT * FROM billing_rates WHERE id=?");
    $s->execute([(int)get('edit')]);
    $editRate = $s->fetch();
}

// Compute sample bills for each tier
function calcTierBill(array $tier, float $consumption): float {
    return $tier['base_charge'] + ($consumption * $tier['rate_per_cubic']);
}

require_once 'includes/header.php';
renderHeader('Billing Rates', 'rates');
?>

<div class="toolbar">
  <div>
    <h2 class="section-title" style="margin:0">💧 Water Billing Rate Tiers</h2>
  </div>
  <button class="btn btn-primary" onclick="document.getElementById('add-rate-modal').style.display='flex'">+ Add Tier</button>
</div>

<!-- Tier Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:24px">
  <?php foreach ($rates as $r):
    $sample = calcTierBill($r, $r['min_cubic'] + 5);
    $col = $r['is_active'] ? 'var(--accent)' : 'var(--muted)';
  ?>
  <div class="card" style="border-top:4px solid <?= $col ?>;opacity:<?= $r['is_active']?'1':'0.6' ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
      <div style="font-weight:800;font-size:14px;color:<?= $col ?>"><?= h($r['label']) ?></div>
      <span class="badge <?= $r['is_active']?'badge-active':'badge-disconnected' ?>"><?= $r['is_active']?'Active':'Off' ?></span>
    </div>
    <div style="display:flex;gap:16px;margin-bottom:14px;font-size:13px">
      <div>
        <div class="fs-xs text-muted">Range</div>
        <div class="fw-bold"><?= fmtNum($r['min_cubic']) ?>–<?= $r['max_cubic']!==null?fmtNum($r['max_cubic']).'m³':'∞' ?></div>
      </div>
      <div>
        <div class="fs-xs text-muted">Rate/m³</div>
        <div class="fw-bold" style="color:var(--accent)"><?= fmt($r['rate_per_cubic']) ?></div>
      </div>
      <?php if ($r['base_charge'] > 0): ?>
      <div>
        <div class="fs-xs text-muted">Base</div>
        <div class="fw-bold"><?= fmt($r['base_charge']) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <div class="fs-xs text-muted" style="margin-bottom:12px">
      Sample (<?= $r['min_cubic']+5 ?>m³): <strong style="color:var(--success)"><?= fmt($sample) ?></strong>
    </div>
    <div style="display:flex;gap:6px">
      <a href="rates.php?edit=<?= $r['id'] ?>" class="btn btn-sm btn-info-soft">Edit</a>
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button class="btn btn-sm btn-outline"><?= $r['is_active']?'Disable':'Enable' ?></button>
      </form>
      <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete this rate tier?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button class="btn btn-sm btn-danger-soft">Del</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Bill Calculator -->
<div class="card mb-3">
  <div class="card-title">🧮 Bill Calculator Preview</div>
  <p class="fs-sm text-muted mb-2">Enter a consumption amount to see how it maps across all active tiers.</p>
  <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="margin:0">
      <label class="form-label">Consumption (m³)</label>
      <input type="number" id="calc_consumption" class="form-control" value="25" min="0" style="width:160px" oninput="calcBillPreview()">
    </div>
    <div id="calc_result" style="font-size:15px;font-weight:700;color:var(--accent);padding-bottom:9px"></div>
  </div>
  <div id="calc_breakdown" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap"></div>
</div>

<!-- Rates Table -->
<div class="card">
  <div class="card-title">📋 All Rate Tiers</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Label</th><th>Min (m³)</th><th>Max (m³)</th><th>Rate/m³</th><th>Base Charge</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rates as $r): ?>
        <tr>
          <td class="fs-xs text-muted"><?= $r['id'] ?></td>
          <td class="fw-bold"><?= h($r['label']) ?></td>
          <td><?= fmtNum($r['min_cubic']) ?></td>
          <td><?= $r['max_cubic']!==null ? fmtNum($r['max_cubic']) : '∞' ?></td>
          <td class="fw-bold" style="color:var(--accent)"><?= fmt($r['rate_per_cubic']) ?></td>
          <td><?= fmt($r['base_charge']) ?></td>
          <td><span class="badge <?= $r['is_active']?'badge-active':'badge-disconnected' ?>"><?= $r['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="rates.php?edit=<?= $r['id'] ?>" class="btn btn-sm btn-info-soft">Edit</a>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete rate tier?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-sm btn-danger-soft">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Rate Modal -->
<div class="modal-overlay" id="add-rate-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Rate Tier</span>
      <button class="modal-close" onclick="document.getElementById('add-rate-modal').style.display='none'">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label class="form-label">Tier Label *</label><input name="label" class="form-control" placeholder="e.g. Lifeline (0–10 m³)" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Min Cubic Meter</label><input type="number" step="0.01" name="min_cubic" class="form-control" value="0"></div>
        <div class="form-group"><label class="form-label">Max Cubic Meter <span class="fs-xs text-muted">(blank=unlimited)</span></label><input type="number" step="0.01" name="max_cubic" class="form-control" placeholder="Leave blank for ∞"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Rate per m³ (₱) *</label><input type="number" step="0.01" name="rate_per_cubic" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Base Charge (₱)</label><input type="number" step="0.01" name="base_charge" class="form-control" value="0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('add-rate-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Tier</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Rate Modal -->
<?php if ($editRate): ?>
<div class="modal-overlay" id="edit-rate-modal" style="display:flex" onclick="if(event.target===this)window.location='rates.php'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Rate Tier</span>
      <a href="rates.php" class="modal-close">×</a>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= $editRate['id'] ?>">
      <div class="form-group"><label class="form-label">Tier Label *</label><input name="label" class="form-control" value="<?= h($editRate['label']) ?>" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Min Cubic Meter</label><input type="number" step="0.01" name="min_cubic" class="form-control" value="<?= h($editRate['min_cubic']) ?>"></div>
        <div class="form-group"><label class="form-label">Max Cubic Meter</label><input type="number" step="0.01" name="max_cubic" class="form-control" value="<?= h($editRate['max_cubic'] ?? '') ?>" placeholder="∞"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Rate per m³ (₱) *</label><input type="number" step="0.01" name="rate_per_cubic" class="form-control" value="<?= h($editRate['rate_per_cubic']) ?>" required></div>
        <div class="form-group"><label class="form-label">Base Charge (₱)</label><input type="number" step="0.01" name="base_charge" class="form-control" value="<?= h($editRate['base_charge']) ?>"></div>
      </div>
      <div class="modal-footer">
        <a href="rates.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const rateTiers = <?= json_encode(array_values(array_filter($rates, fn($r)=>$r['is_active']))) ?>;

function calcBillPreview() {
  const c   = parseFloat(document.getElementById('calc_consumption').value) || 0;
  const el  = document.getElementById('calc_result');
  const bd  = document.getElementById('calc_breakdown');
  bd.innerHTML = '';

  // Find applicable tier (first match where c is in range)
  let applied = null;
  for (const t of rateTiers) {
    const inRange = c >= t.min_cubic && (t.max_cubic === null || c <= t.max_cubic);
    if (inRange) { applied = t; break; }
  }

  if (!applied && rateTiers.length) applied = rateTiers[rateTiers.length - 1];
  if (!applied) { el.textContent = 'No active tiers'; return; }

  const total = parseFloat(applied.base_charge) + c * parseFloat(applied.rate_per_cubic);
  el.textContent = `→ ${applied.label}: ₱${total.toFixed(2)}`;

  // Show all tiers
  rateTiers.forEach(t => {
    const isMatch = t.id === applied.id;
    const div = document.createElement('div');
    const tot = parseFloat(t.base_charge) + c * parseFloat(t.rate_per_cubic);
    div.style.cssText = `background:${isMatch?'var(--accent)':'var(--surface-alt)'};color:${isMatch?'#fff':'var(--text)'};border-radius:8px;padding:8px 14px;font-size:12px;font-weight:${isMatch?'800':'500'}`;
    div.innerHTML = `<div>${t.label}</div><div style="font-size:15px;margin-top:2px">₱${tot.toFixed(2)}</div>`;
    bd.appendChild(div);
  });
}
calcBillPreview();
</script>

<?php renderFooter(); ?>
