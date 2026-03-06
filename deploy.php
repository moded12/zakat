<?php

$secret = "zakat_deploy_secret";
$repo = "/var/www/vhosts/shneler.com/httpdocs/zakat";
$logfile = $repo . "/deploy.log";

/* قراءة البيانات القادمة من GitHub */
$payload = file_get_contents("php://input");
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

/* إنشاء التوقيع المتوقع */
$hash = 'sha1=' . hash_hmac('sha1', $payload, $secret);

/* التحقق من صحة الطلب */
if (!hash_equals($hash, $signature)) {
    file_put_contents($logfile, date("Y-m-d H:i:s")." Invalid signature\n", FILE_APPEND);
    die("Invalid signature");
}

/* أوامر التحديث */
$commands = [
    "cd $repo",
    "git fetch origin",
    "git reset --hard origin/main",
    "git clean -f"
];

$output = "";

foreach ($commands as $cmd) {
    $result = shell_exec($cmd . " 2>&1");
    $output .= "$cmd\n$result\n";
}

/* حفظ log */
file_put_contents($logfile, date("Y-m-d H:i:s")."\n".$output."\n", FILE_APPEND);

echo "<pre>$output</pre>";