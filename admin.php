<?php
// حماية بسيطة بكلمة مرور لو حبيت (غيرها باللي تعجبك)
$admin_pass = "bdr123"; 

$dbFile = 'licenses.json';
$keysFile = 'generated_keys.json';

// تحميل البيانات
$licenses = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
$genKeys = file_exists($keysFile) ? json_decode(file_get_contents($keysFile), true) : [];

$message = "";

// توليد كود جديد
if (isset($_POST['generate'])) {
    $days = intval($_POST['days']);
    $newKey = "BDR-" . strtoupper(substr(md5(mt_rand()), 0, 4)) . "-" . strtoupper(substr(md5(mt_rand()), 0, 4)) . "-" . strtoupper(substr(md5(mt_rand()), 0, 4));
    
    $genKeys[$newKey] = [
        "days" => $days,
        "used" => false,
        "created_at" => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($keysFile, json_encode($genKeys, JSON_PRETTY_PRINT));
    $message = "تم توليد الكود بنجاح: <b>{$newKey}</b> (مدته: {$days} يوم)";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>BDr - Admin Panel</title>
    <style>
        body { background: #121212; color: #fff; font-family: Tahoma, sans-serif; padding: 20px; }
        h1, h2 { color: #a855f7; }
        .card { background: #1e1e1e; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border: 1px solid #333; text-align: center; }
        th { background: #27272a; color: #a855f7; }
        select, button { padding: 10px; background: #9333ea; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #7e22ce; }
        .alert { background: #065f46; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>لوحة تحكم BDr - إدارة الاشتراكات</h1>
    
    <?php if($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>توليد كود اشتراك جديد</h2>
        <form method="POST">
            <label>اختر مدة الاشتراك:</label>
            <select name="days">
                <option value="1">يومي (1 يوم)</option>
                <option value="7">أسبوعي (7 أيام)</option>
                <option value="30">شهري (30 يوم)</option>
                <option value="365">سنوي (365 يوم)</option>
            </select>
            <button type="submit" name="generate">توليد الكود</button>
        </form>
    </div>

    <div class="card">
        <h2>الأكواد المولدة والمتاحة</h2>
        <table>
            <tr>
                <th>الكود</th>
                <th>المدة بالأيام</th>
                <th>الحالة</th>
                <th>تاريخ الإنشاء</th>
            </tr>
            <?php foreach($genKeys as $k => $data): ?>
            <tr>
                <td><code><?php echo $k; ?></code></td>
                <td><?php echo $data['days']; ?> أيام</td>
                <td><?php echo $data['used'] ? '<span style="color:red">مستخدم</span>' : '<span style="color:green">متاح</span>'; ?></td>
                <td><?php echo $data['created_at']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h2>المستخدمون النشطون (الأجهزة المفعّلة - HWID)</h2>
        <table>
            <tr>
                <th>معرف الجهاز (HWID)</th>
                <th>الكود المستخدم</th>
                <th>تاريخ الانتهاء</th>
                <th>الحالة</th>
            </tr>
            <?php foreach($licenses as $hwid => $data): ?>
            <tr>
                <td><code><?php echo $hwid; ?></code></td>
                <td><?php echo $data['key']; ?></td>
                <td><?php echo date('Y-m-d H:i:s', $data['expiry']); ?></td>
                <td><?php echo (time() > $data['expiry']) ? '<ص樣 style="color:red">منتهي</spanD>' : '<span style="color:green">ساري</span>'; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
