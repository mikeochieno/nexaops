-- ============================================================
-- NexaOps — App & AI Management Platform
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS app_manager
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE app_manager;

-- ── Companies ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS companies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    domain      VARCHAR(255) DEFAULT NULL,
    logo_url    VARCHAR(500) DEFAULT NULL,
    industry    VARCHAR(100) DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Apps ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS apps (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    company_id  INT DEFAULT NULL,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    type        ENUM('web','mobile','api','ai_service','android') NOT NULL DEFAULT 'web',
    description TEXT DEFAULT NULL,
    base_url    VARCHAR(500) DEFAULT NULL,
    repo_url    VARCHAR(500) DEFAULT NULL,
    api_key     VARCHAR(64) NOT NULL UNIQUE,
    status      ENUM('active','inactive','maintenance') DEFAULT 'active',
    version     VARCHAR(50) DEFAULT '1.0.0',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_api_key (api_key)
) ENGINE=InnoDB;

-- ── App Activity Logs ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS app_logs (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    app_id      INT NOT NULL,
    user_id     VARCHAR(100) DEFAULT 'system',
    action      VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    level       ENUM('debug','info','warning','error','critical') DEFAULT 'info',
    metadata    JSON DEFAULT NULL,
    source_file VARCHAR(500) DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    INDEX idx_app_action (app_id, action),
    INDEX idx_app_date (app_id, created_at),
    INDEX idx_created (created_at),
    INDEX idx_level (level)
) ENGINE=InnoDB;

-- ── AI Usage Tracking ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_usage (
    id                BIGINT AUTO_INCREMENT PRIMARY KEY,
    app_id            INT NOT NULL,
    provider          VARCHAR(50) NOT NULL DEFAULT 'openai',
    model             VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
    operation         VARCHAR(50) DEFAULT 'chat',
    tokens_prompt     INT DEFAULT 0,
    tokens_completion  INT DEFAULT 0,
    tokens_used       INT DEFAULT 0,
    cost_usd          DECIMAL(10,6) DEFAULT 0,
    latency_ms        INT DEFAULT 0,
    success           TINYINT(1) DEFAULT 1,
    user_id           VARCHAR(100) DEFAULT 'system',
    metadata          JSON DEFAULT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    INDEX idx_app_date (app_id, created_at),
    INDEX idx_provider (provider),
    INDEX idx_model (model)
) ENGINE=InnoDB;

-- ── Log Sources (for sync-based collection) ────────────────────
CREATE TABLE IF NOT EXISTS log_sources (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    app_id           INT NOT NULL,
    source_type      ENUM('php_action_log','python_log','android_log','api_push','file') NOT NULL,
    source_path      VARCHAR(500) DEFAULT NULL,
    source_url       VARCHAR(500) DEFAULT NULL,
    is_active        TINYINT(1) DEFAULT 1,
    last_sync_at     DATETIME DEFAULT NULL,
    last_sync_count  INT DEFAULT 0,
    last_offset      BIGINT DEFAULT 0,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Seed demo companies ────────────────────────────────────────
INSERT INTO companies (name, domain, industry) VALUES
    ('Excelle Insights', 'excelleinsights.com', 'Real Estate Tech'),
    ('NexaOps Platform', 'nexaops.local', 'AI & Software')
ON DUPLICATE KEY UPDATE name = name;

-- ── Seed demo apps ─────────────────────────────────────────────
INSERT INTO apps (company_id, name, slug, type, description, base_url, api_key) VALUES
    (1, 'Excelle PRO — Sales App', 'excelle-sales', 'mobile',
     'Sales agent mobile app API for lead management, client bookings, and payments.',
     'http://demo.local/apps/sales/',
     'sales_app_key_2026_demo'),

    (1, 'Excelle PRO — CRM System', 'excelle-crm', 'web',
     'Main CRM/ERP web application for real estate management.',
     'http://demo.local/',
     'crm_app_key_2026_demo'),

    (1, 'AI Assistant (Demo)', 'demo-ai-assistant', 'ai_service',
     'Natural-language-to-SQL AI assistant with multi-provider LLM fallback.',
     'http://localhost:8001',
     'ai_app_key_2026_demo'),

    (2, 'NexaOps Dashboard', 'nexaops-dashboard', 'web',
     'This app management dashboard itself.',
     'http://localhost/app_manager',
     'nexaops_key_2026_demo'),

    (1, 'Client Portal App', 'excelle-client-portal', 'mobile',
     'Client-facing mobile app for property listings, payments, and documents.',
     'http://demo.local/apps/clients/',
     'client_app_key_2026_demo'),

    (1, 'WhatsApp Integration', 'excelle-whatsapp', 'api',
     'WhatsApp Business API integration for messaging and notifications.',
     'http://demo.local/apps/whatsapp/',
     'whatsapp_key_2026_demo')

ON DUPLICATE KEY UPDATE name = name;

-- ── Seed log sources for sync ──────────────────────────────────
INSERT INTO log_sources (app_id, source_type, source_path, is_active) VALUES
    ((SELECT id FROM apps WHERE slug='excelle-sales' LIMIT 1),
     'php_action_log',
     '/Library/WebServer/Sites/demo/apps/sales/action_log.php',
     1),
    ((SELECT id FROM apps WHERE slug='demo-ai-assistant' LIMIT 1),
     'python_log',
     '/Library/WebServer/Sites/ai_app/storage/ai_activity.log',
     1)
ON DUPLICATE KEY UPDATE source_path = source_path;
