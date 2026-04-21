<?php
require_once 'includes/functions.php';
requireLogin();
if (!isAdmin()) { header('Location: dashboard.php'); exit; }
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: backup.php'); exit; }

$pdo = getDB();

// ── EXPORT (Backup) ──────────────────────────────────────────────────────────
if (get('action') === 'backup') {
    $tables = ['users','customers','readings','bills','payments'];
    if (isset($_GET['rates'])) $tables[] = 'billing_rates';

    $sql  = "-- AquaBill Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Server: " . DB_HOST . " | Database: " . DB_NAME . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Table structure
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sql .= "-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create[1] . ";\n\n";

        // Table data
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        if ($rows) {
            $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            $colList = implode('`, `', $cols);
            $sql .= "INSERT INTO `$table` (`$colList`) VALUES\n";
            $inserts = [];
            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, $row);
                $inserts[] = '(' . implode(', ', $vals) . ')';
            }
            $sql .= implode(",\n", $inserts) . ";\n\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = 'aquabill_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

// ── IMPORT (Restore) ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'restore') {
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('Please upload a valid SQL file.', 'error');
    } else {
        $ext = strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            setFlash('Only .sql files are accepted.', 'error');
        } else {
            $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
            // Split by semicolons and run each statement
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $ok = 0; $fail = 0;
            foreach ($statements as $stmt) {
                if (empty($stmt) || strpos($stmt, '--') === 0) continue;
                try {
                    $pdo->exec($stmt);
                    $ok++;
                } catch (PDOException $e) {
                    $fail++;
                }
            }
            setFlash("Restore complete: $ok statements executed, $fail failed.");
        }
    }
    header('Location: backup.php');
    exit;
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$tableSizes = [];
try {
    $rows = $pdo->query(
        "SELECT table_name, table_rows,
         ROUND((data_length+index_length)/1024,1) AS size_kb
         FROM information_schema.tables
         WHERE table_schema='" . DB_NAME . "'
         ORDER BY table_name"
    )->fetchAll();
    foreach ($rows as $r) $tableSizes[$r['table_name']] = $r;
} catch (Exception $e) {}

$tables = ['customers','readings','bills','payments','users'];
$totalRows = 0;
foreach ($tables as $t) {
    $totalRows += (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}

require_once 'includes/header.php';
renderHeader('Backup & Restore', 'backup');
?>

<div style="max-width:860px;margin:0 auto">

  <!-- Info Banner -->
  <div class="card mb-3" style="background:linear-gradient(135deg,var(--accent-dark),var(--accent));color:#fff;border:none">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <div style="font-size:40px">🗄️</div>
      <div>
        <div style="font-size:18px;font-weight:900">Database Backup & Restore</div>
        <div style="font-size:13px;opacity:.85;margin-top:4px">
          Database: <strong><?= DB_NAME ?></strong> on <strong><?= DB_HOST ?></strong>
          &nbsp;·&nbsp; <?= $totalRows ?> total records &nbsp;·&nbsp; Last backup: check your downloads
        </div>
      </div>
    </div>
  </div>

  <div class="two-col mb-3">

    <!-- BACKUP -->
    <div class="card">
      <div class="card-title">⬇️ Create Backup</div>
      <p class="fs-sm text-muted mb-2">Download a full SQL dump of your database. Store this file safely — it can be used to fully restore your data.</p>

      <div style="background:var(--surface-alt);border-radius:10px;padding:14px;margin-bottom:16px">
        <div class="fs-xs text-muted" style="margin-bottom:8px;font-weight:700">What will be backed up:</div>
        <?php foreach ($tables as $t):
          $count = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
          $kb    = $tableSizes[$t]['size_kb'] ?? '?';
        ?>
        <div class="info-row">
          <span style="text-transform:capitalize"><?= $t ?></span>
          <span class="fs-xs text-muted"><?= $count ?> rows · <?= $kb ?> KB</span>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px">
        <a href="backup.php?action=backup" class="btn btn-primary">
          ⬇️ Download Full Backup (.sql)
        </a>
        <a href="backup.php?action=backup&rates=1" class="btn btn-outline">
          ⬇️ Backup Including Rate Tiers
        </a>
      </div>

      <div class="info-box" style="margin-top:14px;margin-bottom:0">
        💡 <strong>Tip:</strong> Schedule weekly backups. Store copies in Google Drive or USB.
      </div>
    </div>

    <!-- RESTORE -->
    <div class="card">
      <div class="card-title">⬆️ Restore from Backup</div>
      <p class="fs-sm text-muted mb-2">Upload a previously exported <code>.sql</code> backup file to restore data.</p>

      <div class="danger-box">
        ⚠️ <strong>WARNING:</strong> Restoring will overwrite existing data. This action cannot be undone. Make a current backup first!
      </div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="restore">
        <div class="form-group">
          <label class="form-label">Select SQL Backup File *</label>
          <input type="file" name="sql_file" class="form-control" accept=".sql" required
                 style="padding:8px">
          <div class="form-hint">Only .sql files exported from AquaBill are accepted.</div>
        </div>
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('⚠️ RESTORE DATABASE? This will overwrite all current data. Are you absolutely sure?')">
          ⬆️ Restore Database
        </button>
      </form>
    </div>
  </div>

  <!-- Table Stats -->
  <div class="card mb-3">
    <div class="card-title">📊 Database Table Statistics</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Table</th><th>Rows</th><th>Size (KB)</th><th>Health</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tables as $t):
            $count = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $kb    = $tableSizes[$t]['size_kb'] ?? '—';
          ?>
          <tr>
            <td class="fw-bold" style="text-transform:capitalize"><?= $t ?></td>
            <td><?= number_format($count) ?></td>
            <td><?= $kb ?> KB</td>
            <td>
              <span style="color:var(--success);font-weight:700">✅ OK</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Maintenance Tips -->
  <div class="card">
    <div class="card-title">🛠️ Maintenance Recommendations</div>
    <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
      <?php
      $tips = [
        ['icon'=>'📅','title'=>'Weekly Backups','desc'=>'Download a full backup every Monday before starting work.'],
        ['icon'=>'☁️','title'=>'Off-site Storage','desc'=>'Keep copies in Google Drive, Dropbox, or an external hard drive.'],
        ['icon'=>'🧹','title'=>'Archive Old Records','desc'=>'Use Settings → Archive Old Bills to keep the database lean.'],
        ['icon'=>'🔒','title'=>'Secure Your Config','desc'=>'Ensure config/db.php is not web-accessible. Use .htaccess.'],
        ['icon'=>'🔄','title'=>'Test Restores','desc'=>'Periodically test restoring a backup in a test environment.'],
      ];
      foreach ($tips as $tip): ?>
      <div style="display:flex;gap:12px;padding:10px;background:var(--surface-alt);border-radius:8px">
        <span style="font-size:20px;flex-shrink:0"><?= $tip['icon'] ?></span>
        <div>
          <div class="fw-bold"><?= $tip['title'] ?></div>
          <div class="text-muted fs-sm"><?= $tip['desc'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php renderFooter(); ?>
