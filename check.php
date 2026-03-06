<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<h3>Check</h3>";
echo "PHP VERSION: " . PHP_VERSION . "<br>";

require_once __DIR__ . '/config.php';
echo "CONFIG OK. DB_NAME=" . htmlspecialchars(DB_NAME) . "<br>";

require_once __DIR__ . '/db.php';

try {
  $pdo = db();
  echo "DB CONNECT OK.<br>";

  $t = $pdo->query("SHOW TABLES LIKE 'families'")->fetch();
  echo $t ? "TABLE families EXISTS.<br>" : "TABLE families NOT FOUND.<br>";

  if ($t) {
    $cols = $pdo->query("SHOW COLUMNS FROM families")->fetchAll();
    echo "<pre>";
    foreach ($cols as $c) echo $c['Field'] . "\n";
    echo "</pre>";
  }
} catch (Throwable $e) {
  echo "DB ERROR:<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}