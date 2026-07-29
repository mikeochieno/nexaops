<?php
header('Content-Type: text/plain');
$cfg = require __DIR__ . '/config/database.php';
$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$ca = '/etc/ssl/certs/ca-certificates.crt';
if (file_exists($ca)) $opts[PDO::MYSQL_ATTR_SSL_CA] = $ca;
$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset=utf8mb4";
$pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $opts);
echo "SHOW DATABASES:\n";
foreach ($pdo->query("SHOW DATABASES") as $r) {
    echo "  - " . $r[0] . "\n";
}
echo "\nCurrent DB: " . $cfg['database'] . "\n";
$pdo->exec("USE {$cfg['database']}");
echo "SHOW TABLES:\n";
foreach ($pdo->query("SHOW TABLES") as $r) {
    echo "  - " . $r[0] . "\n";
}
