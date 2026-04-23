<?php
// includes/header.php
function renderHeader(string $title = '', string $activePg = ''): void {
    $u         = currentUser();
    $dark      = isDark();
    $flash     = getFlash();
    $pageTitle = $title ? "$title | " . APP_NAME : APP_NAME;

    $notifCount = 0;
    try {
        $pdo2 = getDB();
        $pdo2->exec("UPDATE bills SET status='Overdue' WHERE status='Unpaid' AND due_date < CURDATE()");
        $notifCount = (int)$pdo2->query(
            "SELECT COUNT(*) FROM bills WHERE status='Overdue'
             OR (status='Unpaid' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY))"
        )->fetchColumn();
    } catch (Exception $e) {}

    $navItems = [
        ['href'=>'dashboard.php',      'icon'=>'⊞',  'label'=>'Dashboard'],
        ['href'=>'customers.php',      'icon'=>'👥',  'label'=>'Customers'],
        ['href'=>'readings.php',       'icon'=>'💧',  'label'=>'Meter Readings'],
        ['href'=>'billing.php',        'icon'=>'💵',  'label'=>'Billing'],
        ['href'=>'payments.php',       'icon'=>'💳',  'label'=>'Payments'],
        ['href'=>'reports.php',        'icon'=>'📊',  'label'=>'Reports'],
        ['href'=>'notifications.php',  'icon'=>'🔔',  'label'=>'Notifications', 'badge'=>$notifCount],
        ['href'=>'gcash_admin.php',    'icon'=>'📱',  'label'=>'GCash Payments'],
        ['href'=>'import.php',         'icon'=>'📥',  'label'=>'CSV Import'],
        ['href'=>'field-app-guide.php','icon'=>'📲',  'label'=>'Field App'],
    ];
    if ($u['role'] === 'Admin') {
        $navItems[] = ['href'=>'rates.php',   'icon'=>'💲', 'label'=>'Billing Rates'];
        $navItems[] = ['href'=>'settings.php','icon'=>'⚙️', 'label'=>'Settings'];
        $navItems[] = ['href'=>'backup.php',  'icon'=>'🗄️', 'label'=>'Backup & Restore'];
        $navItems[] = ['href'=>'users.php',   'icon'=>'🔐', 'label'=>'User Accounts'];
    }
    $navItems[] = ['href'=>'profile.php','icon'=>'👤','label'=>'My Profile'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Mobile backdrop -->
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="closeMobileSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">💧</span>
    <div class="logo-text">
      <strong><?= h(APP_NAME) ?></strong>
      <small>Water Billing</small>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($navItems as $nav):
        $pg    = basename($nav['href'], '.php');
        $cls   = $pg === $activePg ? 'active' : '';
        $badge = $nav['badge'] ?? 0;
    ?>
    <a href="<?= h($nav['href']) ?>" class="nav-item <?= $cls ?>" onclick="closeMobileSidebar()">
      <span class="nav-icon"><?= $nav['icon'] ?></span>
      <span class="nav-label"><?= h($nav['label']) ?></span>
      <?php if ($badge > 0): ?>
      <span class="nav-badge"><?= $badge ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= h($u['avatar']) ?></div>
    <div class="user-info">
      <strong><?= h($u['name']) ?></strong>
      <small><?= h($u['role']) ?></small>
    </div>
    <a href="logout.php" title="Logout" class="logout-btn">⎋</a>
  </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="main-wrap">

  <!-- TOPBAR -->
  <header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">☰</button>
    <span class="topbar-title"><?= h($title) ?></span>
    <div class="topbar-right">
      <span class="topbar-date"><?= date('D, M j, Y') ?></span>
      <?php if ($notifCount > 0): ?>
      <a href="notifications.php" class="topbar-bell" title="<?= $notifCount ?> alerts">
        🔔<span class="bell-badge"><?= $notifCount ?></span>
      </a>
      <?php endif; ?>
      <a href="profile.php" class="topbar-avatar" title="My Profile">
        <div class="topbar-av-circle"><?= h($u['avatar']) ?></div>
      </a>
      <a href="?dark=<?= $dark ? '0' : '1' ?>" class="btn-mode"><?= $dark ? '☀️' : '🌙' ?></a>
    </div>
  </header>

  <!-- FLASH -->
  <?php if ($flash): ?>
  <div class="flash flash-<?= h($flash['type']) ?>" id="flash-msg">
    <?= h($flash['msg']) ?>
    <button onclick="this.parentElement.remove()">×</button>
  </div>
  <?php endif; ?>

  <!-- PAGE CONTENT -->
  <main class="page-content">
<?php
}

function renderFooter(): void {
?>
  </main>
</div><!-- /main-wrap -->

<!-- Hidden printable receipt — populated by JS -->
<div id="printable-receipt" style="display:none"></div>

<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}
