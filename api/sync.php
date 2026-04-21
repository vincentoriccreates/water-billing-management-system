<?php
/**
 * AquaBill Field App Sync API
 * ─────────────────────────────────────────────────────────────
 * Endpoints (all JSON, use ?action=... or POST body action):
 *
 *   GET  ?action=ping             — health check
 *   POST ?action=login            — authenticate staff, returns token
 *   GET  ?action=customers        — list active customers (requires token)
 *   POST ?action=sync_readings    — push offline readings (requires token)
 *   GET  ?action=last_readings    — latest reading per customer (requires token)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
function jsonOk(array $data): void {
    echo json_encode(['ok' => true, 'data' => $data, 'ts' => time()]);
    exit;
}
function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg, 'ts' => time()]);
    exit;
}

// Parse JSON body
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $body['action'] ?? '';

// ── PING ─────────────────────────────────────────────────────────────────────
if ($action === 'ping') {
    jsonOk(['message' => 'AquaBill API online', 'version' => '1.2']);
}

// ── LOGIN ─────────────────────────────────────────────────────────────────────
if ($action === 'login') {
    $email    = trim($body['email'] ?? '');
    $password = trim($body['password'] ?? '');
    if (!$email || !$password) jsonErr('Email and password required.');

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $valid = $user && (
            password_verify($password, $user['password']) ||
            $password === $user['password']
        );

        if (!$valid) jsonErr('Invalid credentials.', 401);

        // Simple token: base64(id:email:timestamp:secret_hash)
        $secret = hash('sha256', DB_NAME . $user['id'] . $user['email'] . date('Y-m-d'));
        $token  = base64_encode($user['id'] . ':' . $user['email'] . ':' . time() . ':' . $secret);

        jsonOk([
            'token'  => $token,
            'user'   => ['id'=>$user['id'],'name'=>$user['name'],'role'=>$user['role'],'avatar'=>$user['avatar']],
            'expires'=> time() + 86400 * 7   // 7 days
        ]);
    } catch (Exception $e) {
        jsonErr('Server error.', 500);
    }
}

// ── Token verification ────────────────────────────────────────────────────────
function verifyToken(): array {
    $token = $_SERVER['HTTP_X_API_TOKEN']
          ?? $_SERVER['HTTP_AUTHORIZATION']
          ?? $_GET['token']
          ?? '';
    $token = str_replace('Bearer ', '', $token);
    if (!$token) jsonErr('Authentication required.', 401);

    $decoded = base64_decode($token, true);
    if (!$decoded) jsonErr('Invalid token.', 401);

    $parts = explode(':', $decoded, 4);
    if (count($parts) !== 4) jsonErr('Malformed token.', 401);

    [$uid, $email, $ts, $hash] = $parts;

    // Token valid for 7 days
    if (time() - (int)$ts > 86400 * 7) jsonErr('Token expired. Please log in again.', 401);

    $expectedHash = hash('sha256', DB_NAME . $uid . $email . date('Y-m-d', (int)$ts));
    if (!hash_equals($expectedHash, $hash)) jsonErr('Invalid token signature.', 401);

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id,name,role,email FROM users WHERE id=? AND email=?");
        $stmt->execute([$uid, $email]);
        $user = $stmt->fetch();
        if (!$user) jsonErr('User not found.', 401);
        return $user;
    } catch (Exception $e) {
        jsonErr('Server error.', 500);
    }
}

// ── CUSTOMERS ─────────────────────────────────────────────────────────────────
if ($action === 'customers') {
    verifyToken();
    try {
        $pdo       = getDB();
        $customers = $pdo->query(
            "SELECT c.id, c.name, c.address, c.contact, c.meter_no, c.status,
             COALESCE(r.current_reading, 0) AS last_reading,
             r.reading_date AS last_reading_date,
             COALESCE(r.consumption, 0) AS last_consumption
             FROM customers c
             LEFT JOIN readings r ON r.id = (
                 SELECT id FROM readings WHERE customer_id=c.id ORDER BY reading_date DESC LIMIT 1
             )
             WHERE c.status = 'Active'
             ORDER BY c.name"
        )->fetchAll();
        jsonOk(['customers' => $customers, 'count' => count($customers)]);
    } catch (Exception $e) {
        jsonErr('Failed to fetch customers.', 500);
    }
}

// ── LAST READINGS ──────────────────────────────────────────────────────────
if ($action === 'last_readings') {
    verifyToken();
    try {
        $pdo  = getDB();
        $rows = $pdo->query(
            "SELECT r.customer_id, r.current_reading, r.reading_date, r.consumption
             FROM readings r
             INNER JOIN (
                 SELECT customer_id, MAX(reading_date) AS max_date
                 FROM readings GROUP BY customer_id
             ) latest ON r.customer_id=latest.customer_id AND r.reading_date=latest.max_date"
        )->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['customer_id']] = $r;
        jsonOk(['readings' => $map]);
    } catch (Exception $e) {
        jsonErr('Failed to fetch readings.', 500);
    }
}

// ── SYNC READINGS ─────────────────────────────────────────────────────────────
if ($action === 'sync_readings') {
    $user     = verifyToken();
    $readings = $body['readings'] ?? [];

    if (empty($readings) || !is_array($readings)) {
        jsonErr('No readings provided.');
    }

    try {
        $pdo      = getDB();
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($readings as $i => $r) {
            $custId  = trim($r['customer_id'] ?? '');
            $date    = trim($r['reading_date'] ?? '');
            $current = (float)($r['current_reading'] ?? 0);
            $localId = $r['local_id'] ?? "row_$i";   // client-side temp ID

            if (!$custId || !$date || $current <= 0) {
                $errors[] = ['local_id'=>$localId,'error'=>'Missing fields'];
                $skipped++;
                continue;
            }

            // Validate customer exists
            $cs = $pdo->prepare("SELECT id FROM customers WHERE id=? AND status='Active'");
            $cs->execute([$custId]);
            if (!$cs->fetch()) {
                $errors[] = ['local_id'=>$localId,'error'=>"Customer $custId not found"];
                $skipped++;
                continue;
            }

            // Prevent duplicate (same customer + same date)
            $dup = $pdo->prepare("SELECT id FROM readings WHERE customer_id=? AND reading_date=?");
            $dup->execute([$custId, $date]);
            if ($dup->fetch()) {
                $errors[] = ['local_id'=>$localId,'error'=>'Duplicate reading for this date'];
                $skipped++;
                continue;
            }

            // Get previous reading
            $prev_s = $pdo->prepare("SELECT current_reading FROM readings WHERE customer_id=? ORDER BY reading_date DESC LIMIT 1");
            $prev_s->execute([$custId]);
            $prev = (float)($prev_s->fetchColumn() ?: 0);

            if ($current < $prev) {
                $errors[] = ['local_id'=>$localId,'error'=>"Current ($current) < previous ($prev)"];
                $skipped++;
                continue;
            }

            $consumption = round($current - $prev, 2);
            $count = (int)$pdo->query("SELECT COUNT(*) FROM readings")->fetchColumn() + 1;
            $newId = 'R' . str_pad($count, 3, '0', STR_PAD_LEFT);

            $pdo->prepare(
                "INSERT INTO readings (id,customer_id,reading_date,previous_reading,current_reading,consumption)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$newId, $custId, $date, $prev, $current, $consumption]);

            $imported++;
        }

        jsonOk([
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'synced_by'=> $user['name'],
            'synced_at'=> date('Y-m-d H:i:s'),
        ]);

    } catch (Exception $e) {
        jsonErr('Server error during sync: ' . $e->getMessage(), 500);
    }
}

// ── 404 ───────────────────────────────────────────────────────────────────────
jsonErr("Unknown action: '$action'. Valid actions: ping, login, customers, last_readings, sync_readings.", 404);
