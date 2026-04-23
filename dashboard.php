<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: dashboard.php'); exit; }

$pdo = getDB();
$pdo->exec("UPDATE bills SET status='Overdue' WHERE status='Unpaid' AND due_date < CURDATE()");

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalCustomers  = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$activeCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE status='Active'")->fetchColumn();
$totalCollected  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$unpaidCount     = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status IN('Unpaid','Overdue')")->fetchColumn();
$unpaidAmount    = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills WHERE status IN('Unpaid','Overdue')")->fetchColumn();
$overdueCount    = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='Overdue'")->fetchColumn();
$curM = date('m'); $curY = date('Y');
$monthRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=$curM AND YEAR(payment_date)=$curY")->fetchColumn();
$lastM = date('m',strtotime('-1 month')); $lastY = date('Y',strtotime('-1 month'));
$lastRev = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=$lastM AND YEAR(payment_date)=$lastY")->fetchColumn();
$revenueChange = $lastRev > 0 ? round(($monthRevenue-$lastRev)/$lastRev*100,1) : 0;

// GCash payments this month
$gcashMonth = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE method='GCash' AND MONTH(payment_date)=$curM AND YEAR(payment_date)=$curY")->fetchColumn();

// ── Monthly revenue (7 months) ────────────────────────────────────────────────
$monthlyRev = [];
for ($i=6;$i>=0;$i--) {
    $ts=strtotime("-$i months"); $m=date('m',$ts); $y=date('Y',$ts);
    $rev=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=$m AND YEAR(payment_date)=$y")->fetchColumn();
    $monthlyRev[]=['label'=>date('M',$ts),'value'=>$rev];
}

// ── Bill status breakdown ─────────────────────────────────────────────────────
$billStats=['Paid'=>(int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='Paid'")->fetchColumn(),
            'Unpaid'=>(int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='Unpaid'")->fetchColumn(),
            'Overdue'=>$overdueCount];
$totalBills=array_sum($billStats);

// ── Top consumers (5) ─────────────────────────────────────────────────────────
$topConsumers=$pdo->query("SELECT c.name,SUM(r.consumption) AS tot FROM readings r JOIN customers c ON r.customer_id=c.id GROUP BY c.id ORDER BY tot DESC LIMIT 5")->fetchAll();

// ── Recent activity ───────────────────────────────────────────────────────────
$recPay=$pdo->query("SELECT 'payment' AS type,p.created_at AS ts,CONCAT('₱',FORMAT(p.amount,2),' from ',c.name) AS msg,p.method AS sub FROM payments p JOIN customers c ON p.customer_id=c.id ORDER BY p.created_at DESC LIMIT 5")->fetchAll();
$recBill=$pdo->query("SELECT 'bill' AS type,b.created_at AS ts,CONCAT('Bill: ',c.name,' — ',b.billing_month) AS msg,b.status AS sub FROM bills b JOIN customers c ON b.customer_id=c.id ORDER BY b.created_at DESC LIMIT 4")->fetchAll();
$activity=array_slice(array_merge($recPay,$recBill),0,7);
usort($activity,fn($a,$b)=>strtotime($b['ts'])-strtotime($a['ts']));
$activity=array_slice($activity,0,6);

// ── Upcoming due ──────────────────────────────────────────────────────────────
$upcoming=$pdo->query("SELECT b.*,c.name AS cname FROM bills b JOIN customers c ON b.customer_id=c.id WHERE b.status='Unpaid' AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 10 DAY) ORDER BY b.due_date LIMIT 5")->fetchAll();

// ── Overdue alerts ────────────────────────────────────────────────────────────
$overdues=$pdo->query("SELECT b.*,c.name AS cname FROM bills b JOIN customers c ON b.customer_id=c.id WHERE b.status='Overdue' ORDER BY b.due_date LIMIT 5")->fetchAll();

require_once 'includes/header.php';
renderHeader('Dashboard','dashboard');
?>

<!-- ══════════════════════════════════════════════════════
     ROW 1 — STAT CARDS (3 columns on desktop, 2 on mobile)
═════════════════════════════════════════════════════════ -->
<div class="db-stat-row">
  <a href="customers.php" class="db-stat" style="text-decoration:none">
    <div class="db-stat-icon" style="background:var(--info-bg)">👥</div>
    <div class="db-stat-body">
      <div class="db-stat-val text-accent"><?= number_format($totalCustomers) ?></div>
      <div class="db-stat-lbl">Total Customers</div>
      <div class="db-stat-sub"><?= $activeCustomers ?> active</div>
    </div>
  </a>
  <div class="db-stat">
    <div class="db-stat-icon" style="background:var(--success-bg)">💰</div>
    <div class="db-stat-body">
      <div class="db-stat-val text-success"><?= fmt($totalCollected) ?></div>
      <div class="db-stat-lbl">Total Collected</div>
      <div class="db-stat-sub">All-time</div>
    </div>
  </div>
  <a href="billing.php?filter=Unpaid" class="db-stat" style="text-decoration:none">
    <div class="db-stat-icon" style="background:var(--warning-bg)">📋</div>
    <div class="db-stat-body">
      <div class="db-stat-val text-warning"><?= $unpaidCount ?></div>
      <div class="db-stat-lbl">Unpaid / Overdue</div>
      <div class="db-stat-sub"><?= fmt($unpaidAmount) ?></div>
    </div>
  </a>
  <div class="db-stat">
    <div class="db-stat-icon" style="background:var(--info-bg)">📈</div>
    <div class="db-stat-body">
      <div class="db-stat-val" style="color:var(--info)"><?= fmt($monthRevenue) ?></div>
      <div class="db-stat-lbl">This Month</div>
      <div class="db-stat-sub" style="color:<?= $revenueChange>=0?'var(--success)':'var(--danger)' ?>">
        <?= $revenueChange>=0?'▲':'▼' ?> <?= abs($revenueChange) ?>% vs last month
      </div>
    </div>
  </div>
  <a href="notifications.php" class="db-stat" style="text-decoration:none">
    <div class="db-stat-icon" style="background:var(--danger-bg)">🚨</div>
    <div class="db-stat-body">
      <div class="db-stat-val text-danger"><?= $overdueCount ?></div>
      <div class="db-stat-lbl">Overdue Bills</div>
      <div class="db-stat-sub">Needs attention</div>
    </div>
  </a>
  <div class="db-stat">
    <div class="db-stat-icon" style="background:var(--success-bg)">📱</div>
    <div class="db-stat-body">
      <div class="db-stat-val text-success"><?= fmt($gcashMonth) ?></div>
      <div class="db-stat-lbl">GCash This Month</div>
      <div class="db-stat-sub">09269340806</div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     ROW 2 — CHARTS (Revenue bar + Donut + Top Consumers)
═════════════════════════════════════════════════════════ -->
<div class="db-row2">

  <!-- Revenue bar chart -->
  <div class="card db-chart-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <div class="card-title" style="margin:0;font-size:13px">Monthly Revenue (₱)</div>
      <div class="fs-xs text-muted"><?= date('Y') ?></div>
    </div>
    <div id="rev-chart" class="bar-chart" style="height:90px"></div>
  </div>

  <!-- Bill Status Donut -->
  <div class="card db-donut-card">
    <div class="card-title" style="font-size:13px">Bill Status</div>
    <?php
    $dColors=['Paid'=>'#2d6a4f','Unpaid'=>'#b5632a','Overdue'=>'#c1121f'];
    $cx=70;$cy=70;$r=58;$ir=36;$sa=-90;
    $segs=[];
    foreach($billStats as $st=>$cnt){
        $pct=$cnt/max($totalBills,1);$ea=$sa+$pct*360;
        $segs[]=['s'=>$st,'n'=>$cnt,'pct'=>$pct,'sa'=>$sa,'ea'=>$ea,'c'=>$dColors[$st]];
        $sa=$ea;
    }
    function arc($cx,$cy,$r,$sa,$ea){
        if(abs($ea-$sa)>=360)$ea=$sa+359.9;
        $sa=deg2rad($sa);$ea=deg2rad($ea);
        $x1=$cx+$r*cos($sa);$y1=$cy+$r*sin($sa);
        $x2=$cx+$r*cos($ea);$y2=$cy+$r*sin($ea);
        $lg=($ea-$sa)>M_PI?1:0;
        return "M $cx $cy L $x1 $y1 A $r $r 0 $lg 1 $x2 $y2 Z";
    }
    ?>
    <div style="display:flex;align-items:center;gap:12px">
      <svg width="140" height="140" viewBox="0 0 140 140" style="flex-shrink:0">
        <?php foreach($segs as $sg):if($sg['pct']<=0)continue;?>
        <path d="<?= arc($cx,$cy,$r,$sg['sa'],$sg['ea']) ?>" fill="<?= $sg['c'] ?>"/>
        <?php endforeach;?>
        <circle cx="<?=$cx?>" cy="<?=$cy?>" r="<?=$ir?>" fill="var(--surface)"/>
        <text x="<?=$cx?>" y="<?=$cy-4?>" text-anchor="middle" font-size="18" font-weight="900" fill="var(--text)"><?=$totalBills?></text>
        <text x="<?=$cx?>" y="<?=$cy+12?>" text-anchor="middle" font-size="8" fill="var(--muted)">TOTAL</text>
      </svg>
      <div style="flex:1;min-width:0">
        <?php foreach($billStats as $st=>$cnt):
          $pct=$totalBills>0?round($cnt/$totalBills*100):0;?>
        <div style="margin-bottom:8px">
          <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px">
            <span style="font-weight:700;color:<?=$dColors[$st]?>"><?=$st?></span>
            <span class="text-muted"><?=$cnt?> (<?=$pct?>%)</span>
          </div>
          <div class="progress-bar-wrap" style="height:5px">
            <div class="progress-bar-fill" style="width:<?=$pct?>%;background:<?=$dColors[$st]?>"></div>
          </div>
        </div>
        <?php endforeach;?>
      </div>
    </div>
  </div>

  <!-- Top Consumers -->
  <div class="card db-consumers-card">
    <div class="card-title" style="font-size:13px">🏆 Top Consumers</div>
    <?php if($topConsumers):$mc=max(array_column($topConsumers,'tot'),1);
    $bc=['var(--accent)','var(--info)','var(--success)','var(--warning)','var(--danger)'];
    foreach($topConsumers as $i=>$c):$pct=round($c['tot']/$mc*100);?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px">
        <span class="fw-bold" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px"><?=$i+1?>. <?=h($c['name'])?></span>
        <span style="font-weight:700;color:<?=$bc[$i]?>;flex-shrink:0;margin-left:4px"><?=number_format($c['tot'],1)?> m³</span>
      </div>
      <div class="progress-bar-wrap" style="height:5px">
        <div class="progress-bar-fill" style="width:<?=$pct?>%;background:<?=$bc[$i]?>"></div>
      </div>
    </div>
    <?php endforeach;
    else:?><p class="text-muted fs-sm">No readings yet.</p><?php endif;?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     ROW 3 — Activity + Upcoming + Overdue
═════════════════════════════════════════════════════════ -->
<div class="db-row3">

  <!-- Recent Activity -->
  <div class="card">
    <div class="card-title" style="font-size:13px">🕐 Recent Activity</div>
    <?php if($activity):
    $ai=['payment'=>'💳','bill'=>'💵'];
    $ab=['payment'=>'var(--success-bg)','bill'=>'var(--info-bg)'];
    foreach($activity as $a):
      $diff=time()-strtotime($a['ts']);
      $ago=$diff<3600?round($diff/60).'m':($diff<86400?round($diff/3600).'h':date('M j',strtotime($a['ts'])));
    ?>
    <div style="display:flex;gap:10px;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--border)">
      <div style="width:28px;height:28px;border-radius:50%;background:<?=$ab[$a['type']]?>;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0"><?=$ai[$a['type']]?></div>
      <div style="flex:1;min-width:0">
        <div class="fs-sm" style="line-height:1.4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=h($a['msg'])?></div>
        <div class="fs-xs text-muted"><?=$ago?> · <?=h($a['sub']??'')?></div>
      </div>
    </div>
    <?php endforeach;
    else:?><p class="text-muted fs-sm">No recent activity.</p><?php endif;?>
  </div>

  <!-- Upcoming Due -->
  <div class="card">
    <div class="card-title" style="font-size:13px">📅 Due in 10 Days</div>
    <?php if($upcoming):foreach($upcoming as $b):
      $diff=round((strtotime($b['due_date'])-strtotime(date('Y-m-d')))/86400);
      $uc=$diff<=2?'var(--danger)':($diff<=5?'var(--warning)':'var(--info)');
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)">
      <div style="min-width:0"><div class="fw-bold fs-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=h($b['cname'])?></div><div class="fs-xs text-muted"><?=h($b['billing_month'])?></div></div>
      <div style="text-align:right;flex-shrink:0;margin-left:8px">
        <div class="fw-bold fs-sm" style="color:<?=$uc?>"><?=fmt($b['total'])?></div>
        <div class="fs-xs" style="color:<?=$uc?>"><?=$diff===0?'Today!':$diff.'d'?></div>
      </div>
    </div>
    <?php endforeach;
    echo '<div style="margin-top:8px"><a href="billing.php" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;font-size:12px">View All →</a></div>';
    else:?><div class="empty-state" style="padding:12px"><div class="empty-icon" style="font-size:28px">✅</div><div class="empty-sub">No bills due soon</div></div><?php endif;?>
  </div>

  <!-- Overdue -->
  <div class="card">
    <div class="card-title text-danger" style="font-size:13px">🚨 Overdue Bills</div>
    <?php if($overdues):foreach($overdues as $b):?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)">
      <div style="min-width:0"><div class="fw-bold fs-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=h($b['cname'])?></div><div class="fs-xs text-muted"><?=h($b['billing_month'])?></div></div>
      <div style="text-align:right;flex-shrink:0;margin-left:8px">
        <div class="fw-bold fs-sm text-danger"><?=fmt($b['total'])?></div>
        <a href="billing.php?view=<?=h($b['id'])?>" class="fs-xs" style="color:var(--accent)">View</a>
      </div>
    </div>
    <?php endforeach;
    else:?><div class="empty-state" style="padding:12px"><div class="empty-icon" style="font-size:28px">🎉</div><div class="empty-sub">No overdue bills</div></div><?php endif;?>
  </div>
</div>

<!-- GCash Banner -->
<div class="card" style="background:linear-gradient(135deg,#0077b6,#023e8a);color:#fff;border:none;margin-top:0">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="font-size:28px">📱</div>
    <div style="flex:1">
      <div style="font-weight:900;font-size:15px">GCash Payments Accepted</div>
      <div style="font-size:13px;opacity:.85;margin-top:2px">Send payment to <strong>09269340806</strong> · AquaBill Coop. Inc.</div>
    </div>
    <div style="text-align:right">
      <div style="font-size:13px;opacity:.75">This Month via GCash</div>
      <div style="font-size:20px;font-weight:900"><?=fmt($gcashMonth)?></div>
    </div>
  </div>
</div>

<script>renderBarChart('rev-chart',<?=json_encode($monthlyRev)?>);</script>

<style>
/* ── Dashboard-specific layout ────────────────────── */
.db-stat-row{
  display:grid;
  grid-template-columns:repeat(6,1fr);
  gap:10px;margin-bottom:14px;
}
.db-stat{
  background:var(--surface);border-radius:var(--radius);
  padding:12px 14px;box-shadow:var(--shadow);border:1px solid var(--border);
  display:flex;align-items:center;gap:10px;color:var(--text);
  transition:transform .15s;
}
.db-stat:hover{transform:translateY(-1px)}
.db-stat-icon{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.db-stat-val{font-size:18px;font-weight:900;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.db-stat-lbl{font-size:11px;font-weight:700;margin-top:2px}
.db-stat-sub{font-size:10px;color:var(--muted);margin-top:1px}

.db-row2{display:grid;grid-template-columns:2fr 1.2fr 1.2fr;gap:12px;margin-bottom:14px}
.db-chart-card{}
.db-donut-card{}
.db-consumers-card{}

.db-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px}

/* Responsive */
@media(max-width:1200px){
  .db-stat-row{grid-template-columns:repeat(3,1fr)}
  .db-row2{grid-template-columns:1fr 1fr}
  .db-row2 .db-chart-card{grid-column:1/-1}
  .db-row3{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
  .db-stat-row{grid-template-columns:1fr 1fr;gap:8px}
  .db-stat-val{font-size:15px}
  .db-row2{grid-template-columns:1fr}
  .db-row3{grid-template-columns:1fr}
}
@media(max-width:480px){
  .db-stat-row{grid-template-columns:1fr 1fr;gap:7px}
  .db-stat{padding:10px 10px}
  .db-stat-icon{width:34px;height:34px;font-size:15px}
  .db-stat-val{font-size:14px}
}
</style>

<?php renderFooter();?>
