<?php
// إعدادات قاعدة البيانات (قم بتغييرها بما يناسب استضافتك أو قاعدتك)
// ملاحظة: يمكنك تعديلها حسب نظام التخزين لديك (SQLite, MySQL, JSON file)

$action = isset($_GET['action']) ? $_GET['action'] : '';
$hwid = isset($_GET['hwid']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['hwid']) : '';
$key = isset($_GET['key']) ? trim($_GET['key']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';

// ==========================================
// 1. نظام إرسال الإعدادات السحابية والتحديثات (Cloud Config)
// ==========================================
if ($action === 'config') {
    $configData = [
        "status" => "success",
        // الوزنيات الافتراضية المثالية التي اعتمدناها
        "default_pull" => 114,
        "default_smooth" => 143,
        "default_fov" => 245,
        "default_vert" => -53,
        "default_horz" => -39,
        
        // التحكم في الميزات عن بعد (حالة التفعيل والرسالة مثل "قريباً !")
        "features" => [
            "combo" => [
                "enabled" => false,        // اجعلها true لتفعيلها، أو false لإلغائها وإظهار رسالة "قريباً !"
                "message" => "قريباً !"     // النص الذي سيظهر للمشتركين عند التعطيل
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
// 2. نظام التحقق من الترخيص (Check License)
// ==========================================
if ($action === 'check') {
    if (empty($hwid)) {
        echo json_encode(["status" => "error", "message" => "Invalid HWID"]);
        exit;
    }

    // [ملاحظة]: هنا يتم التحقق من قاعدة البيانات الخاصة بك إذا كان الـ HWID مفعل مسبقاً
    // هذا مثال افتراضي يعتمد على ملف JSON أو قاعدة بيانات
    $dbFile = 'licenses.json';
    $licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];

    if (isset($licenses[$hwid])) {
        $expiryTime = $licenses[$hwid]['expiry'];
        $userName = $licenses[$hwid]['name'];
        
        // التحقق إن لم ينتهِ الاشتراك
        if (time() < $expiryTime) {
            echo json_encode([
                "status" => "success",
                "name" => $userName,
                "expiry" => $expiryTime
            ]);
            exit;
        }
    }

    echo json_encode(["status" => "failed", "message" => "License not found or expired"]);
    exit;
}

// ==========================================
// 3. نظام تفعيل الاشتراك بالكود (Activate License)
// ==========================================
if ($action === 'activate') {
    if (empty($hwid) || empty($key) || empty($name)) {
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;
    }

    // [ملاحظة]: هنا تضيف الأكواد الصحيحة المعتمدة لديك أو تتحقق منها
    // مثال لأكواد التفعيل (يمكنك ربطها بقاعدة بيانات حقيقية)
    $validKeys = [
        "BDR-OV-VIP-2026" => 30, // الكود يمنح 30 يوم مثلاً
        "BDR-PRO-9999"    => 365 // الكود يمنح سنة
    ];

    if (isset($validKeys[$key])) {
        $days = $validKeys[$key];
        $expiryTime = time() + ($days * 86400);

        $dbFile = 'licenses.json';
        $licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];

        // حفظ ارتباط الـ HWID بالمشترك وتاريخ الانتهاء
        $licenses[$hwid] = [
            "name" => $name,
            "expiry" => $expiryTime,
            "key" => $key
        ];
        file_put_contents($dbFile, json_encode($licenses, JSON_PRETTY_PRINT));

        echo json_encode([
            "status" => "success",
            "name" => $name,
            "expiry" => $expiryTime
        ]);
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Invalid or used license key"]);
    exit;
}

// لو تم فتح الملف بدون أي Action صحيح
echo json_encode(["status" => "error", "message" => "Invalid action"]);
?>
