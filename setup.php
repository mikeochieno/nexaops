<?php
/**
 * NexaOps Setup Script
 * Creates the database, tables, and seeds demo data.
 * Run once: php setup.php
 */

$config = require __DIR__ . '/config/database.php';

echo "╔══════════════════════════════════════════════════╗\n";
echo "║   NexaOps — App & AI Management Platform Setup  ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "✓ Connected to MySQL\n";

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '{$config['database']}' created\n";
    $pdo->exec("USE `{$config['database']}`");

    // ── Create tables ────────────────────────────────────────
    $statements = [
        // Companies
        "CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            domain VARCHAR(255) DEFAULT NULL,
            logo_url VARCHAR(500) DEFAULT NULL,
            industry VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        // Apps
        "CREATE TABLE IF NOT EXISTS apps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            type ENUM('web','mobile','api','ai_service','android') NOT NULL DEFAULT 'web',
            description TEXT DEFAULT NULL,
            base_url VARCHAR(500) DEFAULT NULL,
            repo_url VARCHAR(500) DEFAULT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            status ENUM('active','inactive','maintenance') DEFAULT 'active',
            version VARCHAR(50) DEFAULT '1.0.0',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            INDEX idx_slug (slug),
            INDEX idx_api_key (api_key)
        ) ENGINE=InnoDB",

        // App Logs
        "CREATE TABLE IF NOT EXISTS app_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_id INT NOT NULL,
            user_id VARCHAR(100) DEFAULT 'system',
            action VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            level ENUM('debug','info','warning','error','critical') DEFAULT 'info',
            metadata JSON DEFAULT NULL,
            source_file VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_app_action (app_id, action),
            INDEX idx_app_date (app_id, created_at),
            INDEX idx_created (created_at),
            INDEX idx_level (level)
        ) ENGINE=InnoDB",

        // AI Usage
        "CREATE TABLE IF NOT EXISTS ai_usage (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_id INT NOT NULL,
            provider VARCHAR(50) NOT NULL DEFAULT 'openai',
            model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
            operation VARCHAR(50) DEFAULT 'chat',
            tokens_prompt INT DEFAULT 0,
            tokens_completion INT DEFAULT 0,
            tokens_used INT DEFAULT 0,
            cost_usd DECIMAL(10,6) DEFAULT 0,
            latency_ms INT DEFAULT 0,
            success TINYINT(1) DEFAULT 1,
            user_id VARCHAR(100) DEFAULT 'system',
            metadata JSON DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ai_app_date (app_id, created_at),
            INDEX idx_ai_provider (provider),
            INDEX idx_ai_model (model)
        ) ENGINE=InnoDB",

        // Log Sources
        "CREATE TABLE IF NOT EXISTS log_sources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            app_id INT NOT NULL,
            source_type ENUM('php_action_log','python_log','android_log','api_push','file') NOT NULL,
            source_path VARCHAR(500) DEFAULT NULL,
            source_url VARCHAR(500) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            last_sync_at DATETIME DEFAULT NULL,
            last_sync_count INT DEFAULT 0,
            last_offset BIGINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
    echo "✓ All tables created\n";

    // ── Seed companies ───────────────────────────────────────
    $stmt = $pdo->prepare("INSERT IGNORE INTO companies (id, name, domain, industry) VALUES (?, ?, ?, ?)");
    $stmt->execute([1, 'Excelle Insights', 'excelleinsights.com', 'Real Estate Tech']);
    $stmt->execute([2, 'NexaOps Platform', 'nexaops.local', 'AI & Software']);
    echo "✓ 2 companies seeded\n";

    // ── Seed apps ────────────────────────────────────────────
    $apps = [
        [1, 'Excelle PRO — Sales App',   'excelle-sales',          'mobile',     'Sales agent mobile app API',     'http://demo.local/apps/sales/',     'sales_app_key_2026_demo'],
        [1, 'Excelle PRO — CRM System',  'excelle-crm',            'web',        'Main CRM/ERP web application',   'http://demo.local/',                 'crm_app_key_2026_demo'],
        [1, 'AI Assistant (Demo)',        'demo-ai-assistant',      'ai_service', 'NL-to-SQL AI assistant',         'http://localhost:8001',              'ai_app_key_2026_demo'],
        [2, 'NexaOps Dashboard',          'nexaops-dashboard',      'web',        'App management dashboard',       'http://localhost/app_manager',       'nexaops_key_2026_demo'],
        [1, 'Client Portal App',          'excelle-client-portal',  'mobile',     'Client-facing mobile app',       'http://demo.local/apps/clients/',    'client_app_key_2026_demo'],
        [1, 'WhatsApp Integration',       'excelle-whatsapp',       'api',        'WhatsApp Business API',          'http://demo.local/apps/whatsapp/',   'whatsapp_key_2026_demo'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO apps (company_id, name, slug, type, description, base_url, api_key) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($apps as $app) {
        $stmt->execute($app);
    }
    echo "✓ 6 apps seeded\n";

    // ── Seed log sources ─────────────────────────────────────
    $salesId = $pdo->query("SELECT id FROM apps WHERE slug='excelle-sales'")->fetchColumn();
    $aiId    = $pdo->query("SELECT id FROM apps WHERE slug='demo-ai-assistant'")->fetchColumn();

    if ($salesId) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO log_sources (app_id, source_type, source_path, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$salesId, 'php_action_log', '/Library/WebServer/Sites/demo/apps/sales/action_log.php', 1]);
    }
    if ($aiId) {
        $stmt->execute([$aiId, 'python_log', '/Library/WebServer/Sites/ai_app/storage/ai_activity.log', 1]);
    }
    echo "✓ Log sources configured\n";

    // ── Verify ───────────────────────────────────────────────
    $apps     = $pdo->query("SELECT COUNT(*) FROM apps")->fetchColumn();
    $companies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    $sources   = $pdo->query("SELECT COUNT(*) FROM log_sources")->fetchColumn();

    echo "\n📊 Seeded data:\n";
    echo "  Companies:   {$companies}\n";
    echo "  Apps:        {$apps}\n";
    echo "  Log Sources: {$sources}\n";

    echo "\n✅ Setup complete!\n";
    echo "   Dashboard: http://localhost/app_manager\n";
    echo "   API Health: http://localhost/app_manager/api/health\n\n";

} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
