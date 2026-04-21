<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: dashboard.php'); exit; }

$pdo = getDB();

// Auto-mark overdue
$pdo->exec("UPDATE bills SET status='Overdue' WHERE status='Unpaid' AND due_date < CURDATE()");

// ── Core stats ────────────────────────────────────────────────────────────────
$totalCustomers  = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$activeCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE status='Active'")->fetchColumn();
$totalCollected  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$unpaidCount     = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status IN('Unpaid','Overdue')")->fetchColumn();
$unpaidAmount    = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bills WHERE status IN('Unpaid','Overdue')")->fetchColumn();
$overdueCount    = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='Overdue'")->fetchColumn();

$curMonth    = date('m'); $curYear = date('Y');
$monthRevenue= (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=$curMonth AND YEAR(payment_date)=$curYear")->fetchColumn();
$lastM = date('m',strtotime('-1 month')); $lastY = date('Y',strtotime('-1 month'));
$lastMonthRev= (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=$lastM AND YEAR(payment_date)=$lastY")->fetchColumn();
$revenueChange = $lastMonthRev > 0 ? round(($monthRevenue-$lastMonthRev)/$lastMonthRev*100,1) : 0;

// ── Monthly revenue last 7 months ─────────────────────────────────────────────
$monthlyRev = [];
for ($i=6;$i>=0;$i--) {
    $ts=$ts2=strtotime("-$i months");
    $m=date('m',$ts); $y=date('Y',$ts);
    $rev=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=$m AND YEAR(payment_date)=$y")->fetchColumn();
    $monthlyRev[]=['label'=>date('M',$ts),'value'=>$rev];
}

// ── Consumption trend last 7 months ───────────────────────────────────────────
$consumptionTrend=[];
for ($i=6;$i>=0;$i--) {
    $ts=strtotime("-$i months"); $m=date('m',$ts); $y=date('Y',$ts);
    $avg=(float)$pdo->query("SELECT COALESCE(AVG(consumption),0) FROM readings WHERE MONTH(reading_date)=$m AND YEAR(reading_date)=$y")->fetchColumn();
    $consumptionTrend[]=['label'=>date('M',$ts),'value'=>round($avg,1)];
}

// ── Bill status breakdown ─────────────────────────────────────────────────────
$billStats=['Paid'=>(int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='Paid'")->fetchColumn(),
            'Unpaid'=>(int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='Unpaid'")->fetchColumn(),
            'Overdue'=>$overdueCount];
$totalBills=array_sum($billStats);

// ── Top consumers ─────────────────────────────────────────────────────────────
$topConsumers=$pdo->query("SELECT c.name,SUM(r.consumption) AS tot FROM readings r JOIN customers c ON r.customer_id=c.id GROUP BY c.id ORDER BY tot DESC LIMIT 5")->fetchAll();

// ── Recent activity ───────────────────────────────────────────────────────────
$recentPayments=$pdo->query("SELECT 'payment' AS type,p.created_at AS ts,CONCAT('₱',FORMAT(p.amount,2),' received from ',c.name) AS msg FROM payments p JOIN customers c ON p.customer_id=c.id ORDER BY p.created_at DESC LIMIT 4")->fetchAll();
$recentBillsA=$pdo->query("SELECT 'bill' AS type,b.created_at AS ts,CONCAT('Bill generated for ',c.name,' — ',b.billing_month) AS msg FROM bills b JOIN customers c ON b.customer_id=c.id ORDER BY b.created_at DESC LIMIT 4")->fetchAll();
$recentCust=$pdo->query("SELECT 'customer' AS type,created_at AS ts,CONCAT('New customer: ',name) AS msg FROM customers ORDER BY created_at DESC LIMIT 3")->fetchAll();
$activity=array_slice(array_merge($recentPayments,$recentBillsA,$recentCust),0,9);
usort($activity,fn($a,$b)=>strtotime($b['ts'])-strtotime($a['ts']));
$activity=array_slice($activity,0,8);

// ── Upcoming due ──────────────────────────────────────────────────────────────
$upcoming=$pdo->query("SELECT b.*,c.name AS cname FROM bills b JOIN customers c ON b.customer_id=c.id WHERE b.status='Unpaid' AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 10 DAY) ORDER BY b.due_date ASC LIMIT 5")->fetchAll();

require_once 'includes/header.php';
renderHeader('Dashboard','dashboard');
?>

<!-- STAT CARDS -->
<div class="stat-grid">
  <a href="customers.php" class="stat-card" style="text-decoration:none;color:inherit">
    <div class="stat-icon" style="background:var(--info-bg)">👥</div>
    <div>
      <div class="stat-val text-accent"><?= number_format($totalCustomers) ?></div>
      <div class="stat-label">Total Customers</div>
      <div class="stat-sub"><?= $activeCustomers ?> active · <?= $totalCustomers-$activeCustomers ?> disconnected</div>
    </div>
  </a>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--success-bg)">💰</div>
    <div>
      <div class="stat-val text-success"><?= fmt($totalCollected) ?></div>
      <div class="stat-label">Total Collected</div>
      <div class="stat-sub">All-time payments</div>
    </div>
  </div>
  <a href="billing.php?filter=Unpaid" class="stat-card" style="text-decoration:none;color:inherit">
    <div class="stat-icon" style="background:var(--warning-bg)">📋</div>
    <div>
      <div class="stat-val text-warning"><?= $unpaidCount ?></div>
      <div class="stat-label">Unpaid / Overdue</div>
      <div class="stat-sub"><?= fmt($unpaidAmount) ?> outstanding</div>
    </div>
  </a>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--info-bg)">📈</div>
    <div>
      <div class="stat-val" style="color:var(--info)"><?= fmt($monthRevenue) ?></div>
      <div class="stat-label">This Month's Revenue</div>
      <div class="stat-sub" style="color:<?= $revenueChange>=0?'var(--success)':'var(--danger)' ?>">
        <?= $revenueChange>=0?'▲':'▼' ?> <?= abs($revenueChange) ?>% vs last month
      </div>
    </div>
  </a>
  <a href="notifications.php" class="stat-card" style="text-decoration:none;color:inherit">
    <div class="stat-icon" style="background:var(--danger-bg)">🚨</div>
    <div>
      <div class="stat-val text-danger"><?= $overdueCount ?></div>
      <div class="stat-label">Overdue Bills</div>
      <div class="stat-sub">Needs attention</div>
    </div>
  </a>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--success-bg)">✅</div>
    <div>
      <div class="stat-val text-success"><?= $billStats['Paid'] ?></div>
      <div class="stat-label">Bills Paid</div>
      <div class="stat-sub"><?= $totalBills>0?round($billStats['Paid']/$totalBills*100):0 ?>% collection rate</div>
    </div>
  </div>
</div>

<!-- CHARTS -->
<div class="charts-grid mb-3">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div class="card-title" style="margin:0">Monthly Revenue (₱)</div>
      <div class="fs-xs text-muted"><?= date('Y') ?></div>
    </div>
    <div id="revenue-chart" class="bar-chart" style="height:100px"></div>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div class="card-title" style="margin:0">Avg Consumption (m³/month)</div>
      <div class="fs-xs text-muted">Last 7 months</div>
    </div>
    <?php
    $cVals=array_column($consumptionTrend,'value');
    $cMax=max(array_merge($cVals,[1]));$cMin=min(array_merge($cVals,[0]));
    $cRange=max($cMax-$cMin,1);$W=400;$H=90;$pad=14;
    $pts=[];
    foreach($consumptionTrend as $i=>$d){
        $x=$pad+($i/(max(count($consumptionTrend)-1,1)))*($W-$pad*2);
        $y=$H-$pad-(($d['value']-$cMin)/$cRange)*($H-$pad*2);
        $pts[]="$x,$y";
    }
    $polyline=implode(' ',$pts);
    $areaPath="M ".$pts[0];
    foreach(array_slice($pts,1)as $p)$areaPath.=" L $p";
    $areaPath.=" L ".($pad+($W-$pad*2)).",{$H} L {$pad},{$H} Z";
    ?>
    <svg width="100%" viewBox="0 0 <?= $W ?> <?= $H ?>" style="overflow:visible">
      <defs>
        <linearGradient id="cGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--info)" stop-opacity="0.3"/>
          <stop offset="100%" stop-color="var(--info)" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <path d="<?= $areaPath ?>" fill="url(#cGrad)"/>
      <polyline points="<?= $polyline ?>" fill="none" stroke="var(--info)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
      <?php foreach($consumptionTrend as $i=>$d):[$x,$y]=explode(',',$pts[$i]);?>
      <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4" fill="var(--info)" stroke="var(--surface)" stroke-width="2"/>
      <?php endforeach;?>
    </svg>
    <div style="display:flex;justify-content:space-between;margin-top:4px">
      <?php foreach($consumptionTrend as $d):?>
      <div style="text-align:center;flex:1;font-size:9px;color:var(--muted)"><?= h($d['label']) ?></div>
      <?php endforeach;?>
    </div>
  </div>
</div>

<!-- MID ROW -->
<div class="two-col mb-3">
  <!-- Donut Chart -->
  <div class="card">
    <div class="card-title">📊 Bill Status Breakdown</div>
    <?php
    $dColors=['Paid'=>'#2d6a4f','Unpaid'=>'#b5632a','Overdue'=>'#c1121f'];
    $cx=80;$cy=80;$r=65;$iR=40;$sa=-90;
    $segs=[];
    foreach($billStats as $status=>$cnt){
        $pct=$cnt/max($totalBills,1);
        $ea=$sa+$pct*360;
        $segs[]=['s'=>$status,'cnt'=>$cnt,'pct'=>$pct,'sa'=>$sa,'ea'=>$ea,'c'=>$dColors[$status]];
        $sa=$ea;
    }
    function svgArc($cx,$cy,$r,$sa,$ea){
        if(abs($ea-$sa)>=360)$ea=$sa+359.99;
        $sa=deg2rad($sa);$ea=deg2rad($ea);
        $x1=$cx+$r*cos($sa);$y1=$cy+$r*sin($sa);
        $x2=$cx+$r*cos($ea);$y2=$cy+$r*sin($ea);
        $lg=($ea-$sa)>M_PI?1:0;
        return "M $cx $cy L $x1 $y1 A $r $r 0 $lg 1 $x2 $y2 Z";
    }
    ?>
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
      <svg width="160" height="160" viewBox="0 0 160 160" style="flex-shrink:0">
        <?php foreach($segs as $seg):if($seg['pct']<=0)continue;?>
        <path d="<?= svgArc($cx,$cy,$r,$seg['sa'],$seg['ea']) ?>" fill="<?= $seg['c'] ?>"/>
        <?php endforeach;?>
        <circle cx="<?=$cx?>" cy="<?=$cy?>" r="<?=$iR?>" fill="var(--surface)"/>
        <text x="<?=$cx?>" y="<?=$cy-5?>" text-anchor="middle" font-size="20" font-weight="900" fill="var(--text)"><?=$totalBills?></text>
        <text x="<?=$cx?>" y="<?=$cy+13?>" text-anchor="middle" font-size="9" fill="var(--muted)">TOTAL BILLS</text>
      </svg>
      <div style="flex:1;min-width:120px">
        <?php foreach($billStats as $status=>$cnt):
          $pct=$totalBills>0?round($cnt/$totalBills*100):0;?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;margin-bottom:3px">
            <span style="font-size:12px;font-weight:700;color:<?=$dColors[$status]?>"><?=$status?></span>
            <span class="fs-xs text-muted"><?=$cnt?> (<?=$pct?>%)</span>
          </div>
          <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?=$pct?>%;background:<?=$dColors[$status]?>"></div>
          </div>
        </div>
        <?php endforeach;?>
      </div>
    </div>
  </div>

  <!-- Top Consumers -->
  <div class="card">
    <div class="card-title">🏆 Top Water Consumers</div>
    <?php if($topConsumers):$maxC=max(array_column($topConsumers,'tot'),1);
    $barColors=['var(--accent)','var(--info)','var(--success)','var(--warning)','var(--danger)'];
    foreach($topConsumers as $i=>$c):$pct=round($c['tot']/$maxC*100);?>
    <div style="margin-bottom:11px">
      <div style="display:flex;justify-content:space-between;margin-bottom:3px">
        <span class="fw-bold fs-sm"><?=$i+1?>. <?=h($c['name'])?></span>
        <span class="fw-bold fs-sm" style="color:<?=$barColors[$i]?>"><?=number_format($c['tot'],1)?> m³</span>
      </div>
      <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:<?=$pct?>%;background:<?=$barColors[$i]?>"></div>
      </div>
    </div>
    <?php endforeach;
    else:?><div class="empty-state" style="padding:20px"><div class="empty-icon">📟</div><div class="empty-sub">No readings yet.</div></div><?php endif;?>
  </div>
</div>

<!-- BOTTOM ROW -->
<div class="two-col">
  <!-- Activity Feed -->
  <div class="card">
    <div class="card-title">🕐 Recent Activity</div>
    <?php
    $icons=['payment'=>'💳','bill'=>'💵','customer'=>'👤'];
    $actBg=['payment'=>'var(--success-bg)','bill'=>'var(--info-bg)','customer'=>'var(--accent-light)'];
    if($activity):foreach($activity as $a):
      $ts=strtotime($a['ts']);$diff=time()-$ts;
      $ago=$diff<3600?round($diff/60).'m ago':($diff<86400?round($diff/3600).'h ago':date('M j',$ts));
    ?>
    <div style="display:flex;gap:12px;align-items:flex-start;padding:9px 0;border-bottom:1px solid var(--border)">
      <div style="width:30px;height:30px;border-radius:50%;background:<?=$actBg[$a['type']]?>;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0"><?=$icons[$a['type']]?></div>
      <div style="flex:1"><div class="fs-sm" style="line-height:1.4"><?=h($a['msg'])?></div><div class="fs-xs text-muted" style="margin-top:2px"><?=$ago?></div></div>
    </div>
    <?php endforeach;
    else:?><div class="empty-state" style="padding:16px"><div class="empty-icon">🕐</div><div class="empty-sub">No recent activity.</div></div><?php endif;?>
  </div>

  <!-- Upcoming Due -->
  <div class="card">
    <div class="card-title">📅 Due in Next 10 Days</div>
    <?php if($upcoming):foreach($upcoming as $b):
      $diff=round((strtotime($b['due_date'])-strtotime(date('Y-m-d')))/86400);
      $urgency=$diff<=2?'var(--danger)':($diff<=5?'var(--warning)':'var(--info)');
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border)">
      <div><div class="fw-bold fs-sm"><?=h($b['cname'])?></div><div class="fs-xs text-muted"><?=h($b['billing_month'])?></div></div>
      <div style="text-align:right">
        <div class="fw-bold" style="color:<?=$urgency?>"><?=fmt($b['total'])?></div>
        <div class="fs-xs" style="color:<?=$urgency?>"><?=$diff==0?'Due today!':($diff==1?'1 day':$diff.' days')?></div>
      </div>
    </div>
    <?php endforeach;
    echo '<div style="margin-top:12px"><a href="billing.php" class="btn btn-outline btn-sm" style="width:100%;justify-content:center">View All Bills →</a></div>';
    else:?><div class="empty-state" style="padding:16px"><div class="empty-icon">✅</div><div class="empty-title" style="font-size:14px">No bills due soon!</div></div><?php endif;?>
  </div>
</div>

<script>renderBarChart('revenue-chart',<?=json_encode($monthlyRev)?>);</script>

<?php renderFooter();?>
