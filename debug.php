<?php
header('Content-Type: text/plain');
echo "DB_SSL = " . var_export(getenv('DB_SSL'), true) . "\n";
echo "DB_HOST = " . var_export(getenv('DB_HOST'), true) . "\n";
echo "DB_NAME = " . var_export(getenv('DB_NAME'), true) . "\n";
echo "DB_USER = " . var_export(getenv('DB_USER'), true) . "\n";
echo "DB_PORT = " . var_export(getenv('DB_PORT'), true) . "\n";
echo "DB_SSL (from SERVER) = " . ($_SERVER['DB_SSL'] ?? 'NOT SET') . "\n";
echo "\nConfig:\n";
print_r(require __DIR__ . '/config/database.php');
echo "\nPHP version: " . PHP_VERSION . "\n";
echo "Loaded extensions: " . implode(', ', get_loaded_extensions()) . "\n";
