<?php

if (isset($_GET['force'])) {
    $output = shell_exec("cd /var/www/vhosts/shneler.com/httpdocs/zakat && git fetch origin && git reset --hard origin/main 2>&1");
    echo "<pre>$output</pre>";
    exit;
}

$secret = "zakat_deploy_secret";

$payload = file_get_contents("php://input");
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

$hash = 'sha1=' . hash_hmac('sha1', $payload, $secret);

if (!hash_equals($hash, $signature)) {
    exit("Invalid signature");
}

$output = shell_exec("cd /var/www/vhosts/shneler.com/httpdocs/zakat && git fetch origin && git reset --hard origin/main 2>&1");

echo "<pre>$output</pre>";c