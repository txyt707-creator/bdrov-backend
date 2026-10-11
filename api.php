<?php
$action = isset($_GET['action']) ? $_GET['action'] : '';
$secret = isset($_GET['secret']) ? $_GET['secret'] : '';
$hwid = isset($_GET['hwid']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['hwid']) : '';
$key = isset($_GET['key']) ? trim($_GET['key']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$targetKey = isset($_GET['target_key']) ? trim($_GET['target_key']) : '';

$dbFile = 'licenses.json';
$licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
if (!is_array($licenses)) {
    $licenses = [];
}

// ==========================================
// 1. نظام إرسال الإعدادات السحابية (Cloud Config للعملاء)
// ==========================================
if ($action === 'config') {
    $configData = [
        "status" => "success",
        "default_pull" => 114,
        "default_smooth" => 143,
        "default_fov" => 245,
        "default_vert" => -53,
        "default_horz" => -39,
        "features" => [
            "combo" => [
                "enabled" => false,
                "message" => "قريباً !"
            ],
            "ultra_sticky" => [
                "enabled" => true,
                "message" => "قريباً !"
            ]
        ]
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($configData);
    exit;
}

// ==========================================
// 2. لوحة التحكم: جلب البيانات (admin_get_data)
// ==========================================
if ($action === 'admin_get_data') {
    if ($secret !== 'BDrAdmin123Secure') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }
    
    // بناء استجابة JSON تتطابق بدقة مع ما تنتظره لوحة الأدمن الخاصة بك
    $response = ["status" => "success"];
    foreach ($licenses as $k => $data) {
        $response[$k] = [
            "days" => isset($data['days']) ? intval($data['days']) : 30,
            "used" => isset($data['used']) ? boolval($data['used']) : false,
            "name" => isset($data['name']) ? $data['name'] : "-",
            "created_at" => isset($data['created_at']) ? $data['created_at'] : "-"
        ];
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// ==========================================
// 3. لوحة التحكم: توليد كود جديد (admin_generate)
// ==========================================
if ($action === 'admin_generate') {
    if ($secret !== 'BDrAdmin123Secure') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }
    
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    $prefix = isset($_GET['prefix']) ? $_GET['prefix'] : 'BDR';
    
    $newKey = $prefix . '-' . strtoupper(substr(md5(mt_rand()), 0, 4) . '-' . substr(md5(mt_rand()), 0, 4) . '-' . substr(md5(mt_rand()), 0, 4));
    
    $licenses[$newKey] = [
        "days" => $days,
        "used" => false,
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
// 4. لوحة التحكم: حذف كود أو باند (admin_delete)
// ==========================================
if ($action === 'admin_delete') {
    if ($secret !== 'BDrAdmin123Secure') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }
    
    if (!empty($targetKey) && isset($licenses[$targetKey])) {
        unset($licenses[$targetKey]);
        file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "success"]);
        exit;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "error", "message" => "Key not found"]);
    exit;
}

// ==========================================
// 5. التحقق من الترخيص للعميل (check)
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
// 6. تفعيل الكود للعميل (activate)
// ==========================================
if ($action === 'activate') {
    if (empty($hwid) || empty($key) || empty($name)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;
    }

    if (isset($licenses[$key])) {
        if (isset($licenses[$key]['used']) && $licenses[$key]['used'] === true) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status" => "error", "message" => "Key already used"]);
            exit;
        }

        $days = isset($licenses[$key]['days']) ? $licenses[$key]['days'] : 30;
        $expiryTime = time() + ($days * 86400);

        $licenses[$key]['name'] = $name;
        $licenses[$key]['hwid'] = $hwid;
        $licenses[$key]['expiry'] = $expiryTime;
        $licenses[$key]['used'] = true;

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
