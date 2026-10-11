<?php
$action = isset($_GET['action']) ? $_GET['action'] : '';
$hwid = isset($_GET['hwid']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['hwid']) : '';
$key = isset($_GET['key']) ? trim($_GET['key']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';

$dbFile = 'licenses.json';
$licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
if (!is_array($licenses)) {
    $licenses = [];
}

// ==========================================
// 1. نظام توليد الأكواد (Admin Panel)
// ==========================================
if ($action === 'generate') {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    $prefix = isset($_GET['prefix']) ? $_GET['prefix'] : 'BDR';
    
    $newKey = $prefix . '-' . strtoupper(substr(md5(mt_rand()), 0, 4) . '-' . substr(md5(mt_rand()), 0, 4) . '-' . substr(md5(mt_rand()), 0, 4));
    
    $licenses[$newKey] = [
        "key" => $newKey,
        "days" => $days,
        "status" => "available",
        "name" => "-",
        "hwid" => "",
        "expiry" => 0,
        "created_at" => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "success", "key" => $newKey]);
    exit;
}

// ==========================================
// 2. نظام عرض قائمة الأكواد (للوحة التحكم)
// ==========================================
if ($action === 'list') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "success", "licenses" => $licenses]);
    exit;
}

// ==========================================
// 3. نظام التحقق من الترخيص (للسكربت الأساسي)
// ==========================================
if ($action === 'check') {
    if (empty($hwid)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Invalid HWID"]);
        exit;
    }

    foreach ($licenses as $k => $data) {
        if (isset($data['hwid']) && $data['hwid'] === $hwid) {
            if (time() < $data['expiry']) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    "status" => "success",
                    "name" => $data['name'],
                    "expiry" => $data['expiry']
                ]);
                exit;
            }
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "failed", "message" => "License not found or expired"]);
    exit;
}

// ==========================================
// 4. نظام تفعيل الاشتراك بالكود (للسكربت الأساسي)
// ==========================================
if ($action === 'activate') {
    if (empty($hwid) || empty($key) || empty($name)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;
    }

    if (isset($licenses[$key])) {
        if (isset($licenses[$key]['status']) && $licenses[$key]['status'] === 'active') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status" => "error", "message" => "Key already used"]);
            exit;
        }

        $days = isset($licenses[$key]['days']) ? $licenses[$key]['days'] : 30;
        $expiryTime = time() + ($days * 86400);

        $licenses[$key]['name'] = $name;
        $licenses[$key]['hwid'] = $hwid;
        $licenses[$key]['expiry'] = $expiryTime;
        $licenses[$key]['status'] = 'active';

        file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "status" => "success",
            "name" => $name,
            "expiry" => $expiryTime
        ]);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "error", "message" => "Invalid license key"]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(["status" => "error", "message" => "Invalid action"]);
?>
