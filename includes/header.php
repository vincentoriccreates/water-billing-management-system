<?php
// includes/header.php  –  call renderHeader($title, $page) at top of each page
function renderHeader(string $title = '', string $activePg = ''): void {
    $u    = currentUser();
    $dark = isDark();
    $flash = getFlash();
    $pageTitle = $title ? "$title | " . APP_NAME : APP_NAME;
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
<link rel="stylesheet" media="print" href="assets/css/print.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">💧</span>
    <div class="logo-text">
      <strong><?= APP_NAME ?></strong>
      <small>WATER BILLING</small>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php
    // Notification badge count
    $notifCount = 0;
    try {
        $pdo2 = getDB();
        $pdo2->exec("UPDATE bills SET status='Overdue' WHERE status='Unpaid' AND due_date < CURDATE()");
        $notifCount = (int)$pdo2->query(
            "SELECT COUNT(*) FROM bills WHERE status='Overdue' OR (status='Unpaid' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY))"
        )->fetchColumn();
    } catch (Exception $e) {}

    $navItems = [
        ['href'=>'dashboard.php',     'icon'=>'⊞', 'label'=>'Dashboard'],
        ['href'=>'customers.php',     'icon'=>'👥', 'label'=>'Customers'],
        ['href'=>'readings.php',      'icon'=>'💧', 'label'=>'Meter Readings'],
        ['href'=>'billing.php',       'icon'=>'💵', 'label'=>'Billing'],
        ['href'=>'payments.php',      'icon'=>'💳', 'label'=>'Payments'],
        ['href'=>'reports.php',       'icon'=>'📊', 'label'=>'Reports'],
        ['href'=>'notifications.php', 'icon'=>'🔔', 'label'=>'Notifications', 'badge'=>$notifCount],
        ['href'=>'import.php',        'icon'=>'📥', 'label'=>'CSV Import'],
    ];
    if ($u['role'] === 'Admin') {
        $navItems[] = ['href'=>'rates.php',   'icon'=>'💲','label'=>'Billing Rates'];
        $navItems[] = ['href'=>'settings.php','icon'=>'⚙️','label'=>'Settings'];
        $navItems[] = ['href'=>'backup.php',  'icon'=>'🗄️','label'=>'Backup & Restore'];
        $navItems[] = ['href'=>'users.php',   'icon'=>'🔐','label'=>'User Accounts'];
    }
    $navItems[] = ['href'=>'profile.php','icon'=>'👤','label'=>'My Profile'];
    foreach ($navItems as $nav):
        $pg    = basename($nav['href'], '.php');
        $cls   = $pg === $activePg ? 'active' : '';
        $badge = $nav['badge'] ?? 0;
    ?>
    <a href="<?= $nav['href'] ?>" class="nav-item <?= $cls ?>">
      <span class="nav-icon"><?= $nav['icon'] ?></span>
      <span class="nav-label"><?= $nav['label'] ?></span>
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
    <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
    <span class="topbar-title"><?= h($title) ?></span>
    <div class="topbar-right">
      <span class="topbar-date"><?= date('D, M j, Y') ?></span>
      <?php if (isset($notifCount) && $notifCount > 0): ?>
      <a href="notifications.php" class="topbar-bell" title="<?= $notifCount ?> alerts">
        🔔<span class="bell-badge"><?= $notifCount ?></span>
      </a>
      <?php endif; ?>
      <a href="profile.php" class="topbar-avatar" title="My Profile">
        <div class="topbar-av-circle"><?= h($u['avatar']) ?></div>
      </a>
      <a href="?dark=<?= $dark ? '0' : '1' ?>" class="btn-mode"><?= $dark ? '☀️ Light' : '🌙 Dark' ?></a>
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
} // end renderHeader


function renderFooter(): void {
?>
  </main><!-- /page-content -->
</div><!-- /main-wrap -->

<script src="assets/js/app.js"></script>
</body>
</html>
<?php
} // end renderFooter
