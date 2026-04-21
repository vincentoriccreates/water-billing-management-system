<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: customers.php'); exit; }

$pdo = getDB();

// ── POST Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'add' || $action === 'edit') {
        $name    = post('name');
        $address = post('address');
        $contact = post('contact');
        $meter   = post('meter_no');
        $status  = post('status');
        $id      = post('id');

        if (!$name || !$address || !$contact || !$meter) {
            setFlash('Please fill in all required fields.', 'error');
        } elseif ($action === 'add') {
            // Generate next ID
            $count = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() + 1;
            $newId = 'C' . str_pad($count, 3, '0', STR_PAD_LEFT);
            // Ensure unique
            while ($pdo->prepare("SELECT id FROM customers WHERE id=?")->execute([$newId]) && $pdo->query("SELECT COUNT(*) FROM customers WHERE id='$newId'")->fetchColumn() > 0) {
                $count++;
                $newId = 'C' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
            $stmt = $pdo->prepare("INSERT INTO customers (id,name,address,contact,meter_no,status,created_at) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$newId, $name, $address, $contact, $meter, $status, date('Y-m-d')]);
            setFlash("Customer added successfully! Account: $newId");
        } else {
            $stmt = $pdo->prepare("UPDATE customers SET name=?,address=?,contact=?,meter_no=?,status=? WHERE id=?");
            $stmt->execute([$name, $address, $contact, $meter, $status, $id]);
            setFlash('Customer updated successfully!');
        }
    } elseif ($action === 'delete' && isAdmin()) {
        $id = post('id');
        $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);
        setFlash('Customer deleted.');
    }
    header('Location: customers.php');
    exit;
}

// ── Fetch & Pagination ────────────────────────────────────────────────────────
$search = get('search');
$filter = get('filter', 'All');
$pg     = max(1, (int)get('page', '1'));
$perPage= 8;

$where  = [];
$params = [];
if ($search) { $where[] = "(name LIKE ? OR id LIKE ? OR address LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($filter !== 'All') { $where[] = "status=?"; $params[] = $filter; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total    = (int)$pdo->prepare("SELECT COUNT(*) FROM customers $whereSQL")->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM customers $whereSQL")->execute($params) : 0;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages  = max(1, ceil($total / $perPage));
$offset = ($pg - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM customers $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Edit prefill
$editCustomer = null;
if (get('edit')) {
    $s = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $s->execute([get('edit')]);
    $editCustomer = $s->fetch();
}

require_once 'includes/header.php';
renderHeader('Customers', 'customers');
?>

<!-- Toolbar -->
<form method="GET" action="customers.php" class="toolbar">
  <div class="toolbar-left">
    <input type="text" name="search" class="search-input" placeholder="🔍 Search customers..." value="<?= h($search) ?>">
    <select name="filter" class="filter-select" onchange="this.form.submit()">
      <option value="All"          <?= $filter==='All'?'selected':'' ?>>All Status</option>
      <option value="Active"       <?= $filter==='Active'?'selected':'' ?>>Active</option>
      <option value="Disconnected" <?= $filter==='Disconnected'?'selected':'' ?>>Disconnected</option>
    </select>
    <button type="submit" class="btn btn-outline">Search</button>
  </div>
  <button type="button" class="btn btn-primary" onclick="document.getElementById('add-modal').style.display='flex'">+ Add Customer</button>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Acct #</th><th>Name</th><th>Address</th><th>Contact</th><th>Meter #</th><th>Status</th><th>Since</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
        <tr>
          <td><code><?= h($c['id']) ?></code></td>
          <td class="fw-bold"><?= h($c['name']) ?></td>
          <td class="fs-sm text-muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($c['address']) ?></td>
          <td class="fs-sm"><?= h($c['contact']) ?></td>
          <td><code><?= h($c['meter_no']) ?></code></td>
          <td><span class="badge badge-<?= strtolower(h($c['status'])) ?>"><?= h($c['status']) ?></span></td>
          <td class="fs-xs text-muted"><?= h($c['created_at']) ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="customer_detail.php?id=<?= h($c['id']) ?>" class="btn btn-sm btn-outline">View</a>
              <a href="customers.php?edit=<?= h($c['id']) ?>" class="btn btn-sm btn-info-soft">Edit</a>
              <?php if (isAdmin()): ?>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete customer <?= h($c['name']) ?>?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= h($c['id']) ?>">
                <button type="submit" class="btn btn-sm btn-danger-soft">Delete</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($customers)): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px" class="text-muted">No customers found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <div class="pagination">
    <span class="total"><?= $total ?> records</span>
    <?php if ($pages > 1): ?>
      <a href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= max(1,$pg-1) ?>" class="page-btn" <?= $pg<=1?'style="pointer-events:none;opacity:.4"':'' ?>>‹</a>
      <?php for ($i=1;$i<=$pages;$i++): ?>
        <a href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= $i ?>" class="page-btn <?= $i===$pg?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= min($pages,$pg+1) ?>" class="page-btn" <?= $pg>=$pages?'style="pointer-events:none;opacity:.4"':'' ?>>›</a>
    <?php endif; ?>
  </div>
</div>

<!-- ADD Modal -->
<div class="modal-overlay" id="add-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Customer</span>
      <button class="modal-close" onclick="document.getElementById('add-modal').style.display='none'">×</button>
    </div>
    <form method="POST" action="customers.php">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Full Name *</label><input name="name" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Contact Number *</label><input name="contact" class="form-control" required></div>
      </div>
      <div class="form-group"><label class="form-label">Address *</label><input name="address" class="form-control" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Meter Number *</label><input name="meter_no" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Status</label>
          <select name="status" class="form-control"><option>Active</option><option>Disconnected</option></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('add-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Customer</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT Modal (auto-open if ?edit= present) -->
<?php if ($editCustomer): ?>
<div class="modal-overlay" id="edit-modal" style="display:flex" onclick="if(event.target===this)window.location='customers.php'">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Customer</span>
      <a href="customers.php" class="modal-close">×</a>
    </div>
    <form method="POST" action="customers.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= h($editCustomer['id']) ?>">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Full Name *</label><input name="name" class="form-control" value="<?= h($editCustomer['name']) ?>" required></div>
        <div class="form-group"><label class="form-label">Contact Number *</label><input name="contact" class="form-control" value="<?= h($editCustomer['contact']) ?>" required></div>
      </div>
      <div class="form-group"><label class="form-label">Address *</label><input name="address" class="form-control" value="<?= h($editCustomer['address']) ?>" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Meter Number *</label><input name="meter_no" class="form-control" value="<?= h($editCustomer['meter_no']) ?>" required></div>
        <div class="form-group"><label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option <?= $editCustomer['status']==='Active'?'selected':'' ?>>Active</option>
            <option <?= $editCustomer['status']==='Disconnected'?'selected':'' ?>>Disconnected</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <a href="customers.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Update Customer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
