<?php

$secret = "zakat_deploy_secret";

/* قراءة البيانات القادمة من GitHub */
$payload = file_get_contents("php://input");
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

/* إنشاء التوقيع المتوقع */
$hash = 'sha1=' . hash_hmac('sha1', $payload, $secret);

/* التحقق من صحة الطلب */
if (!hash_equals($hash, $signature)) {
    die("Invalid signature");
}

/* تنفيذ التحديث */
$output = shell_exec("cd /var/www/vhosts/shneler.com/httpdocs/zakat && git pull origin main 2>&1");

echo "<pre>$output</pre>";