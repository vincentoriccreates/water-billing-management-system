<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: import.php'); exit; }

$pdo = getDB();
$importType = post('import_type') ?: get('type', 'readings');
$results    = [];

// ── POST: Process uploaded CSV ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $type = post('import_type');

    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('Upload error. Please try again.','error');
    } else {
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') { setFlash('Only CSV files are accepted.','error'); }
        else {
            $handle   = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $headers  = array_map('trim', fgetcsv($handle)); // skip header row
            $ok = 0; $skip = 0; $errors = [];

            if ($type === 'readings') {
                // Expected columns: customer_id, reading_date, current_reading
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) { $skip++; continue; }
                    [$custId, $date, $current] = array_map('trim', $row);
                    $current = (float)$current;

                    // Validate customer
                    $cs = $pdo->prepare("SELECT id FROM customers WHERE id=? AND status='Active'");
                    $cs->execute([$custId]);
                    if (!$cs->fetch()) { $errors[] = "Row: Customer $custId not found/active."; $skip++; continue; }

                    // Get previous reading
                    $prev_s = $pdo->prepare("SELECT current_reading FROM readings WHERE customer_id=? ORDER BY reading_date DESC LIMIT 1");
                    $prev_s->execute([$custId]);
                    $prev = (float)($prev_s->fetchColumn() ?: 0);

                    if ($current < $prev) { $errors[] = "Row: $custId — Current ($current) < Previous ($prev)."; $skip++; continue; }

                    $consumption = $current - $prev;
                    $count = (int)$pdo->query("SELECT COUNT(*) FROM readings")->fetchColumn() + 1;
                    $newId = 'R' . str_pad($count, 3, '0', STR_PAD_LEFT);
                    try {
                        $pdo->prepare("INSERT INTO readings (id,customer_id,reading_date,previous_reading,current_reading,consumption) VALUES (?,?,?,?,?,?)")
                            ->execute([$newId,$custId,$date,$prev,$current,$consumption]);
                        $ok++;
                    } catch (PDOException $e) { $errors[] = "Row: $custId — DB error."; $skip++; }
                }
            } elseif ($type === 'customers') {
                // Expected: name, address, contact, meter_no, status
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 4) { $skip++; continue; }
                    [$name, $address, $contact, $meter, $status] = array_pad(array_map('trim',$row), 5, 'Active');
                    $status = in_array($status,['Active','Disconnected']) ? $status : 'Active';
                    if (!$name || !$meter) { $skip++; continue; }

                    // Check duplicate meter
                    $dup = $pdo->prepare("SELECT id FROM customers WHERE meter_no=?");
                    $dup->execute([$meter]);
                    if ($dup->fetch()) { $errors[] = "Meter $meter already exists."; $skip++; continue; }

                    $count = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() + 1;
                    $newId = 'C' . str_pad($count, 3, '0', STR_PAD_LEFT);
                    try {
                        $pdo->prepare("INSERT INTO customers (id,name,address,contact,meter_no,status,created_at) VALUES (?,?,?,?,?,?,?)")
                            ->execute([$newId,$name,$address,$contact,$meter,$status,date('Y-m-d')]);
                        $ok++;
                    } catch (PDOException $e) { $errors[] = "Customer $name — DB error."; $skip++; }
                }
            }

            fclose($handle);
            $results = ['ok'=>$ok,'skip'=>$skip,'errors'=>$errors,'type'=>$type];
        }
    }
}

require_once 'includes/header.php';
renderHeader('CSV Import', 'import');
?>

<div style="max-width:800px;margin:0 auto">
  <h2 class="section-title">📥 CSV Data Import</h2>

  <!-- Results -->
  <?php if (!empty($results)): ?>
  <div class="card mb-3" style="border-left:4px solid <?= $results['skip']?'var(--warning)':'var(--success)' ?>">
    <div class="card-title">Import Results — <?= ucfirst(h($results['type'])) ?></div>
    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:12px">
      <div style="text-align:center;background:var(--success-bg);border-radius:8px;padding:10px 18px">
        <div style="font-size:24px;font-weight:900;color:var(--success)"><?= $results['ok'] ?></div>
        <div class="fs-xs text-muted">Imported</div>
      </div>
      <div style="text-align:center;background:var(--warning-bg);border-radius:8px;padding:10px 18px">
        <div style="font-size:24px;font-weight:900;color:var(--warning)"><?= $results['skip'] ?></div>
        <div class="fs-xs text-muted">Skipped</div>
      </div>
    </div>
    <?php if (!empty($results['errors'])): ?>
    <div class="danger-box">
      <strong>Errors:</strong><br>
      <?php foreach ($results['errors'] as $e): ?>
      <div class="fs-xs">• <?= h($e) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($results['ok'] > 0): ?>
    <div class="success-box">✅ <?= $results['ok'] ?> record(s) imported successfully!</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tabs mb-3">
    <button class="tab-btn report-btn <?= $importType==='readings'?'active':'' ?>" onclick="switchImportTab('readings')">💧 Meter Readings</button>
    <button class="tab-btn report-btn <?= $importType==='customers'?'active':'' ?>" onclick="switchImportTab('customers')">👥 Customers</button>
  </div>

  <!-- Readings Import -->
  <div id="tab-readings" <?= $importType!=='readings'?'style="display:none"':'' ?>>
    <div class="card mb-3">
      <div class="card-title">📥 Import Meter Readings</div>
      <p class="fs-sm text-muted mb-2">Upload a CSV file with meter readings. Each row should have:</p>
      <div class="info-box mb-2">
        <strong>Required columns (in order):</strong><br>
        <code>customer_id</code> , <code>reading_date</code> (YYYY-MM-DD) , <code>current_reading</code>
      </div>
      <div style="background:var(--surface-alt);border-radius:8px;padding:12px;font-family:monospace;font-size:12px;margin-bottom:16px">
        customer_id,reading_date,current_reading<br>
        C001,<?= date('Y-m-d') ?>,1280<br>
        C002,<?= date('Y-m-d') ?>,1045<br>
        C003,<?= date('Y-m-d') ?>,2420
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="import_type" value="readings">
        <div class="form-group">
          <label class="form-label">CSV File *</label>
          <input type="file" name="csv_file" class="form-control" accept=".csv" required style="padding:8px">
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary">📥 Import Readings</button>
          <a href="import.php?download=readings_template" class="btn btn-outline btn-sm">⬇️ Download Template</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Customers Import -->
  <div id="tab-customers" <?= $importType!=='customers'?'style="display:none"':'' ?>>
    <div class="card mb-3">
      <div class="card-title">📥 Import Customers</div>
      <p class="fs-sm text-muted mb-2">Upload a CSV file with customer records.</p>
      <div class="info-box mb-2">
        <strong>Required columns (in order):</strong><br>
        <code>name</code> , <code>address</code> , <code>contact</code> , <code>meter_no</code> , <code>status</code> (Active or Disconnected)
      </div>
      <div style="background:var(--surface-alt);border-radius:8px;padding:12px;font-family:monospace;font-size:12px;margin-bottom:16px">
        name,address,contact,meter_no,status<br>
        Juan dela Cruz,"123 Rizal St., Brgy. Uno",09171234567,M-0099,Active<br>
        Rosa Santos,"45 Mabini Ave.",09182345678,M-0100,Active
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="import_type" value="customers">
        <div class="form-group">
          <label class="form-label">CSV File *</label>
          <input type="file" name="csv_file" class="form-control" accept=".csv" required style="padding:8px">
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary">📥 Import Customers</button>
          <a href="import.php?download=customers_template" class="btn btn-outline btn-sm">⬇️ Download Template</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Rules -->
  <div class="card">
    <div class="card-title">📋 Import Rules & Notes</div>
    <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
      <?php $rules = [
        '✅ First row must be headers (they are automatically skipped).',
        '✅ Dates must be in YYYY-MM-DD format (e.g. 2025-03-15).',
        '✅ Customer IDs must exactly match existing records in the system.',
        '✅ Meter numbers must be unique for customer imports.',
        '⚠️ Duplicate entries are automatically skipped — no overwriting.',
        '⚠️ Current reading must be ≥ previous reading for meter readings.',
        '⚠️ Import only Active customer readings.',
        '💡 Download the template CSV to see the exact expected format.',
      ];
      foreach ($rules as $rule): ?>
      <div style="padding:7px 10px;background:var(--surface-alt);border-radius:6px"><?= $rule ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (get('download')): ?>
<?php
// Template downloads
$ttype = get('download');
if ($ttype === 'readings_template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="readings_import_template.csv"');
    echo "customer_id,reading_date,current_reading\n";
    echo "C001," . date('Y-m-d') . ",1280\n";
    echo "C002," . date('Y-m-d') . ",1045\n";
    exit;
} elseif ($ttype === 'customers_template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customers_import_template.csv"');
    echo "name,address,contact,meter_no,status\n";
    echo "Juan dela Cruz,\"123 Rizal St., Brgy. Uno\",09171234567,M-0099,Active\n";
    echo "Rosa Santos,\"45 Mabini Ave.\",09182345678,M-0100,Active\n";
    exit;
}
?>
<?php endif; ?>

<script>
function switchImportTab(tab) {
  document.getElementById('tab-readings').style.display  = tab==='readings'  ? '' : 'none';
  document.getElementById('tab-customers').style.display = tab==='customers' ? '' : 'none';
  document.querySelectorAll('.report-btn').forEach(b => b.classList.remove('active'));
  event.currentTarget.classList.add('active');
}
</script>

<?php renderFooter(); ?>
