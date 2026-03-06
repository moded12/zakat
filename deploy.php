<?php

$secret = "123456";

$headers = getallheaders();

if (!isset($headers['X-Hub-Signature'])) {
    die("No signature");
}

$payload = file_get_contents("php://input");
$hash = 'sha1=' . hash_hmac('sha1', $payload, $secret);

if ($hash !== $headers['X-Hub-Signature']) {
    die("Invalid signature");
}

$output = shell_exec("cd /var/www/vhosts/shneler.com/httpdocs/zakat && git pull origin main 2>&1");

echo "<pre>$output</pre>";