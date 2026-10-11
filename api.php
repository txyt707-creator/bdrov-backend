<?php
header('Content-Type: application/json');

$dbFile = 'licenses.json';
$keysFile = 'generated_keys.json';

$licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
$genKeys = file_exists($keysFile) ? json_decode(file_get_contents($keysFile), true) : [];

$action = $_GET['action'] ?? 'check';
$hwid = $_GET['hwid'] ?? '';
$key = $_GET['key'] ?? '';

if (empty($hwid)) {
    echo json_encode(["status" => "error", "message" => "HWID is missing"]);
    exit;
}

if ($action === 'activate') {
    if (empty($key)) {
        echo json_encode(["status" => "error", "message" => "Key is missing"]);
        exit;
    }

    // هل الكود موجود في قائمة الأكواد المولدة؟
    if (!isset($genKeys[$key])) {
        echo json_encode(["status" => "error", "message" => "Invalid License Key"]);
        exit;
    }

    // هل الكود تم استخدامه من قبل؟
    if ($genKeys[$key]['used'] == true) {
        echo json_encode(["status" => "error", "message" => "Key already used"]);
        exit;
    }

    // التحقق هل الجهاز مسجل بكود آخر
    foreach ($licenses as $savedHwid => $data) {
        if ($data['key'] === $key && $savedHwid !== $hwid) {
            echo json_encode(["status" => "error", "message" => "Key used on another device"]);
            exit;
        }
    }

    $durationDays = $genKeys[$key]['days'];
    $expiryTime = time() + ($durationDays * 24 * 60 * 60);

    // تحديث حالة الكود بأنه استخدم
    $genKeys[$key]['used'] = true;
    file_put_contents($keysFile, json_encode($genKeys, JSON_PRETTY_PRINT));

    // حفظ ترخيص الجهاز
    $licenses[$hwid] = [
        "key" => $key,
        "expiry" => $expiryTime
    ];
    file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));

    echo json_encode(["status" => "success", "message" => "Activated successfully", "expiry" => $expiryTime]);
    exit;
}

if ($action === 'check') {
    if (!isset($licenses[$hwid])) {
        echo json_encode(["status" => "unregistered", "message" => "Device not registered"]);
        exit;
    }

    $clientData = $licenses[$hwid];
    
    if (time() > $clientData['expiry']) {
        echo json_encode(["status" => "expired", "message" => "Subscription expired"]);
        exit;
    }

    echo json_encode(["status" => "success", "message" => "Access Granted", "expiry" => $clientData['expiry']]);
    exit;
}
