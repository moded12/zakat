<?php

$secret = "123456";

$payload = file_get_contents("php://input");
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

$hash = 'sha1=' . hash_hmac('sha1', $payload, $secret);

if (!hash_equals($hash, $signature)) {
    die("Invalid signature");
}

$output = shell_exec("cd /var/www/vhosts/shneler.com/httpdocs/zakat && git pull origin main 2>&1");

echo "<pre>$output</pre>";