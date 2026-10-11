<?php
header('Content-Type: application/json');

$dbFile = 'licenses.json';
$keysFile = 'generated_keys.json';

$licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
$genKeys = file_exists($keysFile) ? json_decode(file_get_contents($keysFile), true) : [];

$action = $_GET['action'] ?? '';
$hwid = $_GET['hwid'] ?? '';
$key = $_GET['key'] ?? '';
$name = $_GET['name'] ?? 'مستخدم بدون اسم';
$admin_secret = $_GET['secret'] ?? '';

$secretKey = "BDrAdmin123Secure";

// فحص الاتصال (Heartbeat)
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

    $licenses[$hwid]['last_ping'] = time();
    file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));

    echo json_encode(["status" => "success", "expiry" => $licenses[$hwid]['expiry']]);
    exit;
}

// تفعيل الكود مع اسم المشترك
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
    $genKeys[$key]['name'] = $name;
    file_put_contents($keysFile, json_encode($genKeys, JSON_PRETTY_PRINT));

    $licenses[$hwid] = [
        "key" => $key,
        "name" => $name,
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
        "name" => "",
        "created_at" => date('Y-m-d H:i:s')
    ];
    file_put_contents($keysFile, json_encode($genKeys, JSON_PRETTY_PRINT));
    echo json_encode(["status" => "success", "key" => $newKey]);
    exit;
}

if ($action === 'admin_get_data') {
    echo json_encode([
        "status" => "success",
        "keys" => $genKeys,
        "licenses" => $licenses
    ]);
    exit;
}

// حذف الكود / إعطاء باند نهائي
if ($action === 'admin_delete') {
    $targetKey = $_GET['target_key'] ?? '';
    
    // البحث في الأكواد وحذفها أو إلغاء تفعيلها
    foreach ($genKeys as $k => $data) {
        if ($k === $targetKey) {
            $matchedHwid = $data['hwid'] ?? '';
            // حذف الكود من الأكواد المولدة
            unset($genKeys[$k]);
            file_put_contents($keysFile, json_encode($genKeys, JSON_PRETTY_PRINT));
            
            // إذا كان مفعل على جهاز، احذفه من التراخيص (باند نهائي)
            if ($matchedHwid && isset($licenses[$matchedHwid])) {
                unset($licenses[$matchedHwid]);
                file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));
            }
            echo json_encode(["status" => "success"]);
            exit;
        }
    }
    echo json_encode(["status" => "error", "message" => "Key not found"]);
    exit;
}
