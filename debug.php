<?php
header('Content-Type: text/plain');
$cfg = require __DIR__ . '/config/database.php';
$host = $cfg['host'];
$port = $cfg['port'];
$user = $cfg['user'];
$pass = $cfg['password'];
$name = $cfg['database'];
$ca = '/etc/ssl/certs/ca-certificates.crt';
echo "CA exists: " . (file_exists($ca) ? 'YES' : 'NO') . "\n";
echo "CA size: " . filesize($ca) . "\n\n";

// Test 1: PDO with SSL_CA
echo "--- Test 1: PDO with SSL_CA ---\n";
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=sys;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => $ca,
    ]);
    echo "SUCCESS\n";
    $pdo = null;
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 2: PDO with SSL_CA + SSL_VERIFY
echo "\n--- Test 2: PDO with SSL_CA + SSL_VERIFY ---\n";
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=sys;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => $ca,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ]);
    echo "SUCCESS\n";
    $pdo = null;
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 3: mysqli with SSL
echo "\n--- Test 3: mysqli with ssl_set ---\n";
try {
    $mysqli = new mysqli();
    $mysqli->ssl_set(null, null, $ca, null, null);
    $mysqli->real_connect($host, $user, $pass, 'sys', $port, null, MYSQLI_CLIENT_SSL);
    echo "SUCCESS\n";
    $mysqli->close();
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 4: mysqli without SSL
echo "\n--- Test 4: mysqli without SSL ---\n";
try {
    $mysqli = new mysqli($host, $user, $pass, 'sys', $port);
    echo "SUCCESS\n";
    $mysqli->close();
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
