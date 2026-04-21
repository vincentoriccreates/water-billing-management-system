<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: field-app-guide.php'); exit; }
$serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');
require_once 'includes/header.php';
renderHeader('Field App Guide', 'field-app-guide');
?>

<div style="max-width:860px;margin:0 auto">

  <!-- Hero -->
  <div class="card mb-3" style="background:linear-gradient(135deg,var(--accent-dark),var(--accent));color:#fff;border:none;text-align:center;padding:32px">
    <div style="font-size:52px;margin-bottom:12px">📱</div>
    <h1 style="font-size:24px;font-weight:900;margin:0 0 8px">AquaBill Field App</h1>
    <p style="opacity:.85;font-size:14px;margin:0 0 20px">Offline meter reading app for field staff — works without internet!</p>
    <a href="field-app.html" target="_blank" class="btn btn-outline"
       style="color:#fff;border-color:rgba(255,255,255,.5);width:auto;display:inline-flex;padding:12px 28px;font-size:15px">
      🚀 Open Field App
    </a>
  </div>

  <!-- Features overview -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:24px">
    <?php $features = [
      ['📵','Works Offline','Record readings even with no internet. Data syncs when you reconnect.'],
      ['📲','Install on Phone','Add to home screen like a native app — no app store needed.'],
      ['☁️','Auto Sync','Readings automatically sync to the server when online.'],
      ['📷','Meter Photos','Capture photos of meters as proof of reading.'],
      ['🔒','Secure Login','Token-based auth — stays logged in for 7 days.'],
      ['⚡','Fast & Light','Loads in under 1 second, works on any Android or iPhone.'],
    ];
    foreach ($features as [$icon,$title,$desc]): ?>
    <div class="card" style="text-align:center;padding:18px">
      <div style="font-size:28px;margin-bottom:8px"><?= $icon ?></div>
      <div style="font-weight:800;font-size:13px;margin-bottom:4px"><?= $title ?></div>
      <div style="font-size:11px;color:var(--muted)"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- INSTALLATION TABS -->
  <div class="tabs mb-3">
    <button class="tab-btn active" id="tab-android" onclick="showInstallTab('android',this)">🤖 Android</button>
    <button class="tab-btn" id="tab-ios"     onclick="showInstallTab('ios',this)">🍎 iPhone / iPad</button>
    <button class="tab-btn" id="tab-desktop" onclick="showInstallTab('desktop',this)">🖥️ Desktop</button>
    <button class="tab-btn" id="tab-api"     onclick="showInstallTab('api',this)">🔌 API Setup</button>
  </div>

  <!-- ANDROID -->
  <div id="install-android">
    <div class="card mb-3">
      <div class="card-title">🤖 Install on Android Phone or Tablet</div>
      <p class="fs-sm text-muted mb-2">Android supports full PWA installation — it behaves exactly like a native app.</p>

      <?php $androidSteps = [
        ['1','Open Chrome', 'Open <strong>Google Chrome</strong> on your Android device. (Chrome is recommended — other browsers may have limited PWA support.)'],
        ['2','Go to the App URL', 'Type or paste the field app URL in the address bar:<br><code style="background:var(--info-bg);color:var(--info);padding:4px 10px;border-radius:6px;font-size:13px">'.$serverUrl.'/field-app.html</code>'],
        ['3','Wait for it to load', 'Wait for the app to finish loading. You will see the AquaBill login screen.'],
        ['4','Tap "Add to Home Screen"', 'Chrome will show a banner at the bottom saying <strong>"Add AquaBill to Home screen"</strong>. Tap it.<br><br>If the banner doesn\'t appear: tap the <strong>⋮ menu</strong> (top-right) → <strong>"Add to Home screen"</strong> → <strong>"Add"</strong>.'],
        ['5','Confirm Installation', 'Tap <strong>"Add"</strong> or <strong>"Install"</strong> in the popup. The app icon will appear on your home screen.'],
        ['6','Launch from Home Screen', 'Tap the <strong>💧 AquaBill</strong> icon on your home screen. The app opens in full-screen mode — no browser bars!'],
        ['7','Log In', 'Enter your server URL, email, and password. The app will cache customer data so you can work offline.'],
      ];
      foreach ($androidSteps as [$num,$title,$desc]): ?>
      <div style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--border)">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;flex-shrink:0"><?= $num ?></div>
        <div>
          <div style="font-weight:800;font-size:14px;margin-bottom:4px"><?= $title ?></div>
          <div style="font-size:13px;color:var(--muted);line-height:1.6"><?= $desc ?></div>
        </div>
      </div>
      <?php endforeach; ?>

      <div class="success-box" style="margin-top:16px;margin-bottom:0">
        ✅ <strong>That's it!</strong> The app is installed. It works offline and auto-syncs when you reconnect to the internet.
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-title">💡 Android Tips</div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">📶 <strong>Offline mode:</strong> Once logged in, the app remembers all customers. You can take readings with WiFi/data turned off.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">🔄 <strong>Auto sync:</strong> When you return to an area with internet, open the app and it will automatically sync pending readings.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">📷 <strong>Camera:</strong> The app can access your camera to capture meter photos as evidence.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">🔋 <strong>Battery:</strong> The app is lightweight and consumes very little battery compared to native apps.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">🔁 <strong>Update:</strong> The app updates automatically when connected to the server — no manual reinstall needed.</div>
      </div>
    </div>
  </div>

  <!-- iOS -->
  <div id="install-ios" style="display:none">
    <div class="card mb-3">
      <div class="card-title">🍎 Install on iPhone or iPad</div>
      <p class="fs-sm text-muted mb-2">Use <strong>Safari</strong> on iOS — Chrome on iPhone does NOT support home screen installation.</p>

      <div class="info-box mb-2">
        ⚠️ <strong>Important:</strong> You MUST use <strong>Safari</strong> on iPhone/iPad. Chrome and other browsers on iOS cannot install web apps to the home screen.
      </div>

      <?php $iosSteps = [
        ['1','Open Safari','Open the <strong>Safari</strong> browser (the compass icon). Do NOT use Chrome or Firefox.'],
        ['2','Go to the App URL','Type the field app URL in the address bar:<br><code style="background:var(--info-bg);color:var(--info);padding:4px 10px;border-radius:6px;font-size:13px">'.$serverUrl.'/field-app.html</code>'],
        ['3','Wait for the app to load','The AquaBill login screen should appear. Scroll to the top to make sure the page is fully loaded.'],
        ['4','Tap the Share button','Tap the <strong>Share button</strong> — it looks like a box with an arrow pointing up (↑). It\'s in the bottom toolbar on iPhone, or top toolbar on iPad.'],
        ['5','Tap "Add to Home Screen"','Scroll down in the Share menu and tap <strong>"Add to Home Screen"</strong>.'],
        ['6','Name the app','The name "AquaBill Field" will be pre-filled. Tap <strong>"Add"</strong> in the top-right corner.'],
        ['7','Launch from Home Screen','Go to your home screen and find the <strong>💧 AquaBill</strong> icon. Tap it to open in full-screen mode.'],
        ['8','Allow camera (optional)','When taking a meter photo for the first time, tap <strong>"Allow"</strong> when Safari asks for camera access.'],
      ];
      foreach ($iosSteps as [$num,$title,$desc]): ?>
      <div style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--border)">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;flex-shrink:0"><?= $num ?></div>
        <div>
          <div style="font-weight:800;font-size:14px;margin-bottom:4px"><?= $title ?></div>
          <div style="font-size:13px;color:var(--muted);line-height:1.6"><?= $desc ?></div>
        </div>
      </div>
      <?php endforeach; ?>

      <div class="success-box" style="margin-top:16px;margin-bottom:0">
        ✅ Installed! The app now works offline on your iPhone/iPad.
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-title">💡 iPhone / iPad Tips</div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">🌐 <strong>WiFi Only Setup:</strong> The first login requires internet. After that, you can work offline in areas with no signal.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">⚠️ <strong>iOS Limitation:</strong> Service Worker storage on iOS is limited to ~50MB but is more than enough for customer lists.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">🔒 <strong>Data Safety:</strong> Readings stored on device survive app restarts and are only cleared when you choose to reset.</div>
      </div>
    </div>
  </div>

  <!-- DESKTOP -->
  <div id="install-desktop" style="display:none">
    <div class="card mb-3">
      <div class="card-title">🖥️ Install on Desktop (Windows / Mac / Linux)</div>
      <p class="fs-sm text-muted mb-2">Use Google Chrome or Microsoft Edge to install the field app as a desktop application.</p>

      <?php $desktopSteps = [
        ['1','Open Chrome or Edge','Use Google Chrome or Microsoft Edge (Chromium-based).'],
        ['2','Navigate to the app','Go to: <code style="background:var(--info-bg);color:var(--info);padding:2px 8px;border-radius:4px">'.$serverUrl.'/field-app.html</code>'],
        ['3','Look for install icon','In Chrome: look for the <strong>install icon</strong> (⊕) on the right side of the address bar.<br>In Edge: look for the app install icon or go to ⋯ menu → <strong>Apps → Install this site as an app</strong>.'],
        ['4','Click Install','Click <strong>Install</strong> in the popup. The app will open in its own window without browser chrome.'],
        ['5','Pin to taskbar','Right-click the app in the taskbar and choose <strong>"Pin to taskbar"</strong> or <strong>"Pin to Dock"</strong> for quick access.'],
      ];
      foreach ($desktopSteps as [$num,$title,$desc]): ?>
      <div style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--border)">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;flex-shrink:0"><?= $num ?></div>
        <div>
          <div style="font-weight:800;font-size:14px;margin-bottom:4px"><?= $title ?></div>
          <div style="font-size:13px;color:var(--muted);line-height:1.6"><?= $desc ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- API SETUP -->
  <div id="install-api" style="display:none">
    <div class="card mb-3">
      <div class="card-title">🔌 API Endpoint Reference</div>
      <p class="fs-sm text-muted mb-2">The field app communicates with the server via this API. All endpoints return JSON.</p>
      <div style="font-size:13px;font-weight:700;margin-bottom:8px">Base URL:</div>
      <code style="display:block;background:var(--surface-alt);border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px">
        <?= $serverUrl ?>/api/sync.php
      </code>

      <?php $endpoints = [
        ['GET','?action=ping','None','Health check — test if server is reachable'],
        ['POST','?action=login','{"email":"...","password":"..."}','Authenticate and receive API token'],
        ['GET','?action=customers','X-API-Token header','Get all active customers with last reading'],
        ['GET','?action=last_readings','X-API-Token header','Get latest meter reading per customer'],
        ['POST','?action=sync_readings','{"readings":[...]} + token','Push offline readings to server'],
      ];
      foreach ($endpoints as [$method,$path,$auth,$desc]): ?>
      <div style="background:var(--surface-alt);border-radius:10px;padding:12px 14px;margin-bottom:10px">
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
          <span style="background:<?= $method==='GET'?'var(--success-bg)':'var(--info-bg)' ?>;color:<?= $method==='GET'?'var(--success)':'var(--info)' ?>;border-radius:5px;padding:2px 10px;font-size:11px;font-weight:800;font-family:monospace"><?= $method ?></span>
          <code style="font-size:12px"><?= h($path) ?></code>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:4px">Auth: <?= h($auth) ?></div>
        <div style="font-size:13px"><?= h($desc) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card mb-3">
      <div class="card-title">📦 Reading Payload Format</div>
      <p class="fs-sm text-muted mb-2">When syncing readings, each reading object should follow this structure:</p>
      <pre style="background:var(--surface-alt);border-radius:8px;padding:14px;font-size:12px;overflow-x:auto;line-height:1.8">{
  "local_id":       "r_1234567890_abc",  // client-generated unique ID
  "customer_id":    "C001",              // must match DB customer id
  "reading_date":   "2025-03-15",        // YYYY-MM-DD
  "current_reading": 1280.5,             // decimal, >= previous reading
  "notes":          "Meter slightly foggy" // optional
}</pre>
    </div>

    <div class="card">
      <div class="card-title">🔒 Security Notes</div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">🔑 Tokens are valid for <strong>7 days</strong> and are stored in localStorage on the device.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">🌐 Use <strong>HTTPS</strong> in production to encrypt data in transit.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">📵 The API never stores passwords in plain text — bcrypt is used server-side.</div>
        <div style="padding:8px 12px;background:var(--surface-alt);border-radius:8px">🔄 The <code>sync_readings</code> endpoint is idempotent — duplicate readings (same customer + date) are automatically skipped.</div>
      </div>
    </div>
  </div>

  <!-- QR Code link -->
  <div class="card" style="text-align:center;margin-top:8px">
    <div class="card-title" style="text-align:left">📲 Share App Link with Field Staff</div>
    <p class="fs-sm text-muted mb-2">Share this URL with your field staff so they can install the app on their phones:</p>
    <div style="background:var(--info-bg);border-radius:10px;padding:14px;font-size:15px;font-weight:800;color:var(--info);margin-bottom:14px;word-break:break-all">
      <?= h($serverUrl) ?>/field-app.html
    </div>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <button onclick="copyUrl()" class="btn btn-primary" style="width:auto;padding:10px 20px">📋 Copy URL</button>
      <a href="field-app.html" target="_blank" class="btn btn-outline" style="width:auto;padding:10px 20px">🚀 Open App</a>
    </div>
  </div>

</div>

<script>
function showInstallTab(tab, btn) {
  ['android','ios','desktop','api'].forEach(t => {
    document.getElementById('install-' + t).style.display = t === tab ? '' : 'none';
    document.getElementById('tab-' + t)?.classList.toggle('active', t === tab);
  });
}

function copyUrl() {
  const url = '<?= h($serverUrl) ?>/field-app.html';
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => alert('URL copied to clipboard!'));
  } else {
    prompt('Copy this URL:', url);
  }
}
</script>

<?php renderFooter(); ?>
