<?php

$git = "/usr/bin/git"; // المسار الكامل لـ git
$path = "/var/www/vhosts/shneler.com/httpdocs/zakat";

echo "<pre>";

echo "Testing git...\n";
echo shell_exec("$git --version 2>&1");

echo "\nTesting directory...\n";
echo shell_exec("cd $path && pwd 2>&1");

echo "\nTesting fetch...\n";
echo shell_exec("cd $path && $git fetch origin 2>&1");

echo "\nTesting reset...\n";
echo shell_exec("cd $path && $git reset --hard origin/main 2>&1");

echo "</pre>";