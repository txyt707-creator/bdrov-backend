<?php
header('Content-Type: application/json');

$dbFile = 'licenses.json';
$keysFile = 'generated_keys.json';

$licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
$genKeys = file_exists($keysFile) ? json_decode(file_get_contents($keysFile), true) : [];

$action = $_GET['action'] ?? '';
$hwid = $_GET['hwid'] ?? '';
$key = $_GET['key'] ?? '';
$admin_secret = $_GET['secret'] ?? '';

$secretKey = "BDrAdmin123Secure";

// فحص واستمرار الاتصال من العميل (Heartbeat)
if ($action === 'check') {
    if (empty($hwid) || !isset($licenses[$hwid])) {
        echo json_encode(["status" => "unregistered"]);
        exit;
    }
    if ($licenses[$hwid]['banned'] ?? false) {
        echo json_encode(["status" => "banned"]);
        exit;
    }
    if (time() > $licenses[$hwid]['expiry']) {
        echo json_encode(["status" => "expired"]);
        exit;
    }

    // تحديث وقت آخر اتصال بالثانية
    $licenses[$hwid]['last_ping'] = time();
    file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));

    echo json_encode(["status" => "success", "expiry" => $licenses[$hwid]['expiry']]);
    exit;
}

// تفعيل كود
if ($action === 'activate') {
    if (empty($hwid) || empty($key)) {
        echo json_encode(["status" => "error", "message" => "Missing data"]);
        exit;
    }
    if (!isset($genKeys[$key]) || $genKeys[$key]['used']) {
        echo json_encode(["status" => "error", "message" => "Invalid or used key"]);
        exit;
    }

    $days = $genKeys[$key]['days'];
    $expiryTime = time() + ($days * 24 * 60 * 60);

    $genKeys[$key]['used'] = true;
    $genKeys[$key]['hwid'] = $hwid;
    file_put_contents($keysFile, json_encode($genKeys, JSON_PRETTY_PRINT));

    $licenses[$hwid] = [
        "key" => $key,
        "expiry" => $expiryTime,
        "banned" => false,
        "last_ping" => time()
    ];
    file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));

    echo json_encode(["status" => "success", "expiry" => $expiryTime]);
    exit;
}

// أوامر لوحة الأدمن
if ($admin_secret !== $secretKey) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if ($action === 'admin_generate') {
    $days = intval($_GET['days'] ?? 30);
    $prefix = $_GET['prefix'] ?? 'BDR';
    $newKey = strtoupper($prefix) . "-" . strtoupper(substr(md5(mt_rand()), 0, 4)) . "-" . strtoupper(substr(md5(mt_rand()), 0, 4)) . "-" . strtoupper(substr(md5(mt_rand()), 0, 4));
    
    $genKeys[$newKey] = [
        "days" => $days,
        "used" => false,
        "hwid" => "",
        "created_at" => date('Y-m-d H:i:s')
    ];
    file_put_contents($keysFile, json_encode($genKeys, JSON_PRETTY_PRINT));
    echo json_encode(["status" => "success", "key" => $newKey]);
    exit;
}

if ($action === 'admin_get_data') {
    // حساب حالة الاتصال (إذا اتصل خلال آخر دقيقتين يكون أونلاين)
    $now = time();
    $formattedLicenses = [];
    foreach ($licenses as $h => $data) {
        $lastPing = $data['last_ping'] ?? 0;
        $isOnline = ($now - $lastPing) < 120; // 2 Minutes threshold
        $formattedLicenses[$h] = [
            "key" => $data['key'],
            "expiry" => date('Y-m-d H:i', $data['expiry']),
            "online" => $isOnline,
            "banned" => $data['banned'] ?? false
        ];
    }

    echo json_encode([
        "status" => "success",
        "keys" => $genKeys,
        "licenses" => $formattedLicenses
    ]);
    exit;
}
