<?php
/**
 * gcash.php — Public GCash Payment Submission Page
 * Customers submit their GCash reference number after paying.
 * Admin reviews and confirms on gcash_admin.php
 */
require_once 'config/db.php';

session_start();

// ── Config ────────────────────────────────────────────────────────────────────
define('GCASH_NUMBER', '09269340806');
define('GCASH_NAME',   'AquaBill Coop. Inc.');

$pdo = getDB();
$flash = '';
$flashType = 'success';

// Ensure gcash_payments table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gcash_payments (
            id VARCHAR(12) PRIMARY KEY,
            bill_id VARCHAR(10) NOT NULL,
            customer_id VARCHAR(10) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            gcash_ref VARCHAR(50),
            payer_number VARCHAR(20),
            status ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
            notes TEXT,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            confirmed_at TIMESTAMP NULL,
            confirmed_by INT NULL
        )
    ");
} catch (Exception $e) {}

// ── POST: Submit GCash payment ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountNo  = trim($_POST['account_no'] ?? '');
    $billId     = trim($_POST['bill_id'] ?? '');
    $amount     = (float)($_POST['amount'] ?? 0);
    $gcashRef   = trim($_POST['gcash_ref'] ?? '');
    $payerNum   = trim($_POST['payer_number'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$accountNo || !$billId || $amount <= 0 || !$gcashRef || !$payerNum) {
        $flash = 'Please fill in all required fields.';
        $flashType = 'error';
    } else {
        // Verify customer + bill match
        $stmt = $pdo->prepare("SELECT b.*,c.name AS cname FROM bills b JOIN customers c ON b.customer_id=c.id WHERE b.id=? AND b.customer_id=? AND b.status!='Paid'");
        $stmt->execute([$billId, $accountNo]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bill) {
            $flash = 'No unpaid bill found for this Account No and Bill ID. Please check your details.';
            $flashType = 'error';
        } elseif ($amount > $bill['total'] + 0.01) {
            $flash = 'Amount entered exceeds the bill total of ₱' . number_format($bill['total'], 2);
            $flashType = 'error';
        } else {
            // Check for duplicate reference
            $dup = $pdo->prepare("SELECT id FROM gcash_payments WHERE gcash_ref=?");
            $dup->execute([$gcashRef]);
            if ($dup->fetch()) {
                $flash = 'This GCash reference number has already been submitted.';
                $flashType = 'error';
            } else {
                $newId = 'GC' . date('ymd') . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO gcash_payments (id,bill_id,customer_id,amount,gcash_ref,payer_number,notes) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$newId, $billId, $accountNo, $amount, $gcashRef, $payerNum, $notes]);
                $flash = "✅ Payment submitted successfully! Reference ID: <strong>$newId</strong>. The admin will confirm your payment shortly and it will reflect on your account.";
                $flashType = 'success';
            }
        }
    }
}

// ── GET: Look up bills for an account ─────────────────────────────────────────
$lookupBills = [];
$lookupCustomer = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['account'])) {
    $acct = trim($_GET['account']);
    if ($acct) {
        $cs = $pdo->prepare("SELECT * FROM customers WHERE id=? AND status='Active'");
        $cs->execute([$acct]);
        $lookupCustomer = $cs->fetch(PDO::FETCH_ASSOC);
        if ($lookupCustomer) {
            $bs = $pdo->prepare("SELECT b.*, COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id=b.id),0) AS paid FROM bills b WHERE b.customer_id=? AND b.status!='Paid' ORDER BY b.due_date");
            $bs->execute([$acct]);
            $lookupBills = $bs->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

$preAccount = htmlspecialchars($_GET['account'] ?? '', ENT_QUOTES);
$preBill    = htmlspecialchars($_GET['bill'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GCash Payment — AquaBill Coop. Inc.</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Nunito',sans-serif;background:#f0f4f8;min-height:100vh;font-size:15px;color:#1a2a3a}
.wrap{max-width:540px;margin:0 auto;padding:20px 16px}
.header{background:linear-gradient(135deg,#023e8a,#0077b6);color:#fff;border-radius:16px;padding:24px;text-align:center;margin-bottom:20px}
.header h1{font-size:22px;font-weight:900;margin-bottom:6px}
.header p{font-size:13px;opacity:.85}
.gcash-box{background:#fff;border-radius:14px;padding:20px;margin-bottom:20px;box-shadow:0 2px 12px rgba(0,100,200,.1);text-align:center}
.gcash-num{font-size:28px;font-weight:900;color:#0077b6;letter-spacing:2px;margin:8px 0}
.gcash-name{font-size:13px;color:#5a7085;font-weight:600}
.card{background:#fff;border-radius:14px;padding:20px;margin-bottom:16px;box-shadow:0 2px 12px rgba(0,100,200,.1)}
.card h2{font-size:15px;font-weight:800;margin-bottom:14px;color:#023e8a}
.fg{margin-bottom:13px}
.fl{display:block;font-size:12px;font-weight:700;color:#5a7085;margin-bottom:4px}
.fc{width:100%;padding:10px 13px;border-radius:9px;border:1.5px solid #ccd8e5;font-size:15px;font-family:inherit;outline:none}
.fc:focus{border-color:#0077b6;box-shadow:0 0 0 3px rgba(0,119,182,.12)}
.btn{width:100%;padding:14px;border-radius:10px;border:none;font-size:16px;font-weight:800;cursor:pointer;font-family:inherit}
.btn-primary{background:#0077b6;color:#fff}
.btn-outline{background:none;border:1.5px solid #ccd8e5;color:#5a7085;margin-top:8px}
.flash{border-radius:10px;padding:13px 16px;margin-bottom:16px;font-size:14px;font-weight:600;line-height:1.5}
.flash-success{background:#d8f3dc;color:#2d6a4f}
.flash-error  {background:#ffe5e5;color:#c1121f}
.bill-option{background:#f0f4f8;border-radius:9px;padding:12px;margin-bottom:8px;cursor:pointer;border:2px solid transparent;transition:border-color .2s}
.bill-option:hover,.bill-option.selected{border-color:#0077b6;background:#caf0f8}
.bill-option input[type=radio]{margin-right:8px;accent-color:#0077b6}
.steps{counter-reset:step;display:flex;flex-direction:column;gap:12px;margin-bottom:16px}
.step{display:flex;gap:12px;align-items:flex-start}
.step-num{width:28px;height:28px;border-radius:50%;background:#0077b6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;flex-shrink:0;margin-top:1px}
.step-body{flex:1}
.step-title{font-weight:800;font-size:14px;margin-bottom:2px}
.step-desc{font-size:12px;color:#5a7085;line-height:1.5}
.lookup-row{display:flex;gap:8px;margin-bottom:16px}
.lookup-row input{flex:1;padding:10px 13px;border-radius:9px;border:1.5px solid #ccd8e5;font-size:15px;font-family:inherit;outline:none}
.lookup-row button{padding:10px 16px;border-radius:9px;background:#0077b6;color:#fff;border:none;font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap}
.cust-found{background:#d8f3dc;border-radius:9px;padding:11px 14px;margin-bottom:12px;font-size:13px;color:#2d6a4f;font-weight:700}
</style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <div class="header">
    <div style="font-size:36px;margin-bottom:8px">💧</div>
    <h1>AquaBill Coop. Inc.</h1>
    <p>San Juan, Siquijor, Philippines<br>GCash Payment Portal</p>
  </div>

  <!-- Flash message -->
  <?php if ($flash): ?>
  <div class="flash flash-<?= $flashType ?>"><?= $flash ?></div>
  <?php endif; ?>

  <!-- GCash number -->
  <div class="gcash-box">
    <div style="font-size:13px;color:#5a7085;margin-bottom:4px;font-weight:600">Send GCash Payment To</div>
    <div style="font-size:32px;margin:4px 0">📱</div>
    <div class="gcash-num"><?= GCASH_NUMBER ?></div>
    <div class="gcash-name"><?= GCASH_NAME ?></div>
  </div>

  <!-- How to pay steps -->
  <div class="card">
    <h2>📋 How to Pay via GCash</h2>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-body"><div class="step-title">Open GCash App</div><div class="step-desc">Launch GCash on your phone and tap "Send Money".</div></div></div>
      <div class="step"><div class="step-num">2</div><div class="step-body"><div class="step-title">Send to <?= GCASH_NUMBER ?></div><div class="step-desc">Enter the amount from your water bill and complete the transaction.</div></div></div>
      <div class="step"><div class="step-num">3</div><div class="step-body"><div class="step-title">Copy your Reference Number</div><div class="step-desc">After payment, GCash gives you a 13-digit reference number. Copy it.</div></div></div>
      <div class="step"><div class="step-num">4</div><div class="step-body"><div class="step-title">Submit the form below</div><div class="step-desc">Fill in your account details and the reference number to notify the admin.</div></div></div>
    </div>
  </div>

  <!-- Look up unpaid bills -->
  <div class="card">
    <h2>🔍 Look Up Your Unpaid Bills</h2>
    <form method="GET" action="gcash.php">
      <div class="lookup-row">
        <input type="text" name="account" placeholder="Enter your Account No (e.g. C001)" value="<?= $preAccount ?>" required>
        <button type="submit">Search</button>
      </div>
    </form>
    <?php if ($lookupCustomer): ?>
    <div class="cust-found">✅ <?= htmlspecialchars($lookupCustomer['name']) ?> — <?= htmlspecialchars($lookupCustomer['id']) ?></div>
    <?php if ($lookupBills): ?>
    <div style="font-size:13px;font-weight:700;color:#5a7085;margin-bottom:8px">Your unpaid bills:</div>
    <?php foreach ($lookupBills as $lb):
      $bal = $lb['total'] - $lb['paid'];
    ?>
    <div style="background:#f0f4f8;border-radius:9px;padding:11px;margin-bottom:7px;font-size:13px">
      <div style="display:flex;justify-content:space-between"><span style="font-weight:800"><?= htmlspecialchars($lb['billing_month']) ?></span><span style="font-weight:900;color:#0077b6">₱<?= number_format($bal, 2) ?></span></div>
      <div style="color:#5a7085;margin-top:2px">Bill ID: <?= htmlspecialchars($lb['id']) ?> · Due: <?= htmlspecialchars($lb['due_date']) ?></div>
      <div style="margin-top:8px">
        <a href="gcash.php?account=<?= urlencode($lookupCustomer['id']) ?>&bill=<?= urlencode($lb['id']) ?>&amt=<?= urlencode($bal) ?>#pay-form"
           style="display:inline-block;background:#0077b6;color:#fff;border-radius:7px;padding:5px 14px;font-size:12px;font-weight:700;text-decoration:none">
          Pay This Bill →
        </a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div style="color:#2d6a4f;font-size:13px;font-weight:600">🎉 No unpaid bills found for this account!</div>
    <?php endif; ?>
    <?php elseif (isset($_GET['account']) && $_GET['account']): ?>
    <div style="color:#c1121f;font-size:13px;font-weight:600">❌ Account not found. Check your Account No and try again.</div>
    <?php endif; ?>
  </div>

  <!-- Submit form -->
  <div class="card" id="pay-form">
    <h2>📤 Submit Payment Confirmation</h2>
    <form method="POST" action="gcash.php">
      <div class="fg">
        <label class="fl">Account No *</label>
        <input type="text" name="account_no" class="fc" placeholder="e.g. C001" required
               value="<?= $preAccount ?: htmlspecialchars($_POST['account_no'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="fl">Bill ID *</label>
        <input type="text" name="bill_id" class="fc" placeholder="e.g. B001" required
               value="<?= $preBill ?: htmlspecialchars($_POST['bill_id'] ?? '') ?>">
        <div style="font-size:11px;color:#5a7085;margin-top:3px">Find your Bill ID on your paper bill or in the Unpaid Bills lookup above.</div>
      </div>
      <div class="fg">
        <label class="fl">Amount Paid (₱) *</label>
        <input type="number" name="amount" class="fc" placeholder="e.g. 995.00" step="0.01" min="1" required
               value="<?= htmlspecialchars($_GET['amt'] ?? $_POST['amount'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="fl">GCash Reference Number * <span style="font-size:10px;font-weight:600;color:#5a7085">(13-digit code from GCash)</span></label>
        <input type="text" name="gcash_ref" class="fc" placeholder="e.g. 1234567890123" maxlength="20" required
               value="<?= htmlspecialchars($_POST['gcash_ref'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="fl">Your GCash Number * <span style="font-size:10px;font-weight:600;color:#5a7085">(number you paid from)</span></label>
        <input type="tel" name="payer_number" class="fc" placeholder="e.g. 09171234567" maxlength="11" required
               value="<?= htmlspecialchars($_POST['payer_number'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="fl">Notes (optional)</label>
        <input type="text" name="notes" class="fc" placeholder="Any additional notes..."
               value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary">📤 Submit Payment</button>
    </form>
  </div>

  <div style="text-align:center;font-size:12px;color:#5a7085;padding:16px 0">
    AquaBill Coop. Inc. · San Juan, Siquijor, Philippines<br>
    For assistance call or text: <?= GCASH_NUMBER ?>
  </div>
</div>
</body>
</html>
