<?php
$action = isset($_GET['action']) ? $_GET['action'] : '';
$hwid = isset($_GET['hwid']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['hwid']) : '';
$key = isset($_GET['key']) ? trim($_GET['key']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';

$dbFile = 'licenses.json';
$licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];

// ==========================================
// 1. نظام إرسال الإعدادات السحابية (Cloud Config)
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
// 2. نظام توليد الأكواد (للوحة التحكم Admin Panel)
// ==========================================
if ($action === 'generate') {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    $prefix = isset($_GET['prefix']) ? $_GET['prefix'] : 'BDR';
    
    // توليد كود عشوائي فريد
    $newKey = $prefix . '-' . strtoupper(substr(md5(mt_rand()), 0, 4) . '-' . substr(md5(mt_rand()), 0, 4) . '-' . substr(md5(mt_rand()), 0, 4));
    
    // حفظ الكود في قاعدة البيانات كحالة "متاح" (غير مفعل بعد)
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
    
    echo json_encode([
        "status" => "success",
        "key" => $newKey
    ]);
    exit;
}

// ==========================================
// 3. نظام التحقق من الترخيص (Check License)
// ==========================================
if ($action === 'check') {
    if (empty($hwid)) {
        echo json_encode(["status" => "error", "message" => "Invalid HWID"]);
        exit;
    }

    foreach ($licenses as $k => $data) {
        if (isset($data['hwid']) && $data['hwid'] === $hwid) {
            if (time() < $data['expiry']) {
                echo json_encode([
                    "status" => "success",
                    "name" => $data['name'],
                    "expiry" => $data['expiry']
                ]);
                exit;
            }
        }
    }

    echo json_encode(["status" => "failed", "message" => "License not found or expired"]);
    exit;
}

// ==========================================
// 4. نظام تفعيل الاشتراك بالكود (Activate License)
// ==========================================
if ($action === 'activate') {
    if (empty($hwid) || empty($key) || empty($name)) {
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;
    }

    if (isset($licenses[$key])) {
        // التحقق إن كان الكود مستخدم من قبل
        if (isset($licenses[$key]['status']) && $licenses[$key]['status'] === 'active') {
            echo json_encode(["status" => "error", "message" => "Key already used"]);
            exit;
        }

        $days = isset($licenses[$key]['days']) ? $licenses[$key]['days'] : 30;
        $expiryTime = time() + ($days * 86400);

        // تحديث بيانات الكود بربطه بالمشترك والـ HWID
        $licenses[$key]['name'] = $name;
        $licenses[$key]['hwid'] = $hwid;
        $licenses[$key]['expiry'] = $expiryTime;
        $licenses[$key]['status'] = 'active';

        file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));

        echo json_encode([
            "status" => "success",
            "name" => $name,
            "expiry" => $expiryTime
        ]);
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Invalid license key"]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
v?>
