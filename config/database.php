<?php
/**
 * Database Configuration
 * Reads from .env or uses defaults for local dev.
 */
return [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_NAME') ?: 'app_manager',
    'user'     => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: 'root',
    'port'     => getenv('DB_PORT') ?: 3306,
    'charset'  => 'utf8mb4',
    'ssl_ca'   => getenv('DB_SSL_CA') ?: '',
    'ssl_verify' => getenv('DB_SSL_VERIFY') ?: '',
];
