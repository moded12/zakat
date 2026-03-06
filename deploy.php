<?php

$output = shell_exec("cd /var/www/vhosts/shneler.com/httpdocs/zakat && git pull origin main 2>&1");

echo "<pre>$output</pre>";