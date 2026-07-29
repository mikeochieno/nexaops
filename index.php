<?php
/**
 * NexaOps — App & AI Command Center
 * Dashboard entry point.
 */
$config = require __DIR__ . '/config/app.php';
$apiBase = $config['base_url'] . '/api';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['name']) ?></title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <!-- ── Sidebar ─────────────────────────────────────────────── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-bolt"></i>
            <span>NexaOps</span>
        </div>
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active" data-view="dashboard">
                <i class="fas fa-chart-line"></i><span>Dashboard</span>
            </a>
            <a href="#" class="nav-item" data-view="companies">
                <i class="fas fa-building"></i><span>Companies</span>
            </a>
            <a href="#" class="nav-item" data-view="apps">
                <i class="fas fa-cubes"></i><span>Applications</span>
            </a>
            <a href="#" class="nav-item" data-view="logs">
                <i class="fas fa-scroll"></i><span>Activity Logs</span>
            </a>
            <a href="#" class="nav-item" data-view="ai">
                <i class="fas fa-brain"></i><span>AI Usage</span>
            </a>
            <a href="#" class="nav-item" data-view="integrations">
                <i class="fas fa-plug"></i><span>Integrations</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <small>v1.0.0</small>
        </div>
    </aside>

    <!-- ── Main Content ────────────────────────────────────────── -->
    <main class="main-content">
        <!-- Top bar -->
        <header class="topbar">
            <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title" id="pageTitle">Dashboard</h1>
            <div class="topbar-actions">
                <button class="btn btn-sm" onclick="NexaOps.syncLogs()" title="Sync Logs">
                    <i class="fas fa-sync-alt"></i> Sync
                </button>
                <button class="btn btn-sm" onclick="NexaOps.refresh()" title="Refresh">
                    <i class="fas fa-redo"></i>
                </button>
                <span class="status-dot" id="statusDot"></span>
            </div>
        </header>

        <!-- Views -->
        <div class="content-area">
            <!-- ═══ Dashboard View ═══ -->
            <section class="view active" id="view-dashboard">
                <div class="stats-grid" id="statsGrid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-cubes"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statApps">—</span>
                            <span class="stat-label">Applications</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-building"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statCompanies">—</span>
                            <span class="stat-label">Companies</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fas fa-scroll"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statLogs">—</span>
                            <span class="stat-label">Logs (7d)</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-brain"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statAICalls">—</span>
                            <span class="stat-label">AI Calls (7d)</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-coins"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statAICost">—</span>
                            <span class="stat-label">AI Cost (7d)</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon teal"><i class="fas fa-users"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="statUsers">—</span>
                            <span class="stat-label">Active Users</span>
                        </div>
                    </div>
                </div>

                <!-- Two-column layout -->
                <div class="dashboard-grid">
                    <!-- App Stats Table -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> App Activity (7d)</h3>
                        </div>
                        <div class="card-body">
                            <table class="data-table" id="appStatsTable">
                                <thead>
                                    <tr>
                                        <th>Application</th>
                                        <th>Logs</th>
                                        <th>Users</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-fire"></i> Top Actions (7d)</h3>
                        </div>
                        <div class="card-body">
                            <div id="topActionsList"></div>
                        </div>
                    </div>
                </div>

                <!-- Live Log Feed -->
                <div class="card full-width">
                    <div class="card-header">
                        <h3><i class="fas fa-stream"></i> Live Activity Feed</h3>
                        <button class="btn btn-xs" onclick="NexaOps.loadRecentLogs()">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                    <div class="card-body log-feed" id="liveFeed"></div>
                </div>
            </section>

            <!-- ═══ Apps View ═══ -->
            <section class="view" id="view-apps">
                <div class="card full-width">
                    <div class="card-header">
                        <h3><i class="fas fa-cubes"></i> Registered Applications</h3>
                        <button class="btn btn-sm" onclick="NexaOps.showAppForm()">
                            <i class="fas fa-plus"></i> Add App
                        </button>
                    </div>
                    <div class="card-body">
                        <table class="data-table" id="appsTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Company</th>
                                    <th>Logs</th>
                                    <th>AI Calls</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ═══ Logs View ═══ -->
            <section class="view" id="view-logs">
                <div class="card full-width">
                    <div class="card-header">
                        <h3><i class="fas fa-scroll"></i> Activity Logs</h3>
                        <div class="filter-bar">
                            <input type="text" id="logSearch" placeholder="Search logs..." class="input-sm">
                            <select id="logCompanyFilter" class="input-sm"><option value="">All Companies</option></select>
                            <select id="logAppFilter" class="input-sm"><option value="">All Apps</option></select>
                            <select id="logLevelFilter" class="input-sm">
                                <option value="">All Levels</option>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                            </select>
                            <button class="btn btn-sm" onclick="NexaOps.searchLogs()">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="log-feed" id="logsFeed"></div>
                        <div class="pagination" id="logsPagination"></div>
                    </div>
                </div>
            </section>

            <!-- ═══ AI Usage View ═══ -->
            <section class="view" id="view-ai">
                <div class="stats-grid" id="aiStatsGrid">
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-phone"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="aiStatCalls">—</span>
                            <span class="stat-label">Total Calls</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-microchip"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="aiStatTokens">—</span>
                            <span class="stat-label">Tokens Used</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="aiStatCost">—</span>
                            <span class="stat-label">Total Cost</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-tachometer-alt"></i></div>
                        <div class="stat-body">
                            <span class="stat-value" id="aiStatLatency">—</span>
                            <span class="stat-label">Avg Latency</span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-server"></i> By Provider</h3></div>
                        <div class="card-body"><div id="aiByProvider"></div></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-cube"></i> By App</h3></div>
                        <div class="card-body"><div id="aiByApp"></div></div>
                    </div>
                </div>

                <div class="card full-width">
                    <div class="card-header"><h3><i class="fas fa-chart-area"></i> Daily AI Usage (7d)</h3></div>
                    <div class="card-body"><div id="aiDailyChart" class="chart-area"></div></div>
                </div>
            </section>

            <!-- ═══ Companies View ═══ -->
            <section class="view" id="view-companies">
                <div class="card full-width">
                    <div class="card-header">
                        <h3><i class="fas fa-building"></i> Companies</h3>
                        <button class="btn btn-sm" onclick="NexaOps.showCompanyForm()">
                            <i class="fas fa-plus"></i> Add Company
                        </button>
                    </div>
                    <div class="card-body">
                        <table class="data-table" id="companiesTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Domain</th>
                                    <th>Industry</th>
                                    <th>Apps</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ═══ Company Detail View ═══ -->
            <section class="view" id="view-company-detail">
                <div class="card full-width">
                    <div class="card-header">
                        <h3><i class="fas fa-building"></i> <span id="companyDetailName">Company</span></h3>
                        <button class="btn btn-sm" onclick="NexaOps.backToCompanies()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                    </div>
                    <div class="card-body" id="companyDetailBody"></div>
                </div>
            </section>

            <!-- ═══ App Detail View ═══ -->
            <section class="view" id="view-app-detail">
                <div class="card full-width">
                    <div class="card-header">
                        <h3><i class="fas fa-cube"></i> <span id="appDetailName">App</span></h3>
                        <button class="btn btn-sm" onclick="NexaOps.backToApps()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                    </div>
                    <div class="card-body" id="appDetailBody"></div>
                </div>
            </section>

            <!-- ═══ Integrations View ═══ -->
            <section class="view" id="view-integrations">
                <div class="card full-width">
                    <div class="card-header">
                        <h3><i class="fas fa-plug"></i> Integration Guide</h3>
                    </div>
                    <div class="card-body">
                        <div class="integration-guide">
                            <h4>PHP Integration (Drop-in)</h4>
                            <pre class="code-block"><code>require_once 'NexaOpsClient.php';

$nexa = new NexaOpsClient('your_api_key');
$nexa->log('LOGIN', 'User john@example.com logged in');
$nexa->aiUsage([
    'model' => 'gpt-4o',
    'tokens_prompt' => 500,
    'tokens_completion' => 200,
    'cost_usd' => 0.008,
]);</code></pre>

                            <h4>Python Integration</h4>
                            <pre class="code-block"><code>from nexaops_agent import NexaOpsHandler
import logging

handler = NexaOpsHandler(api_key='your_key', app_name='ai_app')
logging.getLogger().addHandler(handler)
logging.info('AI query completed')</code></pre>

                            <h4>REST API Push</h4>
                            <pre class="code-block"><code>curl -X POST http://localhost/app_manager/api/collect/log \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key" \
  -d '{"logs":[{"action":"DEPLOY","description":"v2.1 deployed"}]}'</code></pre>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- ── Modals ──────────────────────────────────────────────── -->
    <div class="modal-overlay" id="modalOverlay" onclick="NexaOps.closeModal()"></div>
    <div class="modal" id="companyModal">
        <div class="modal-header">
            <h3 id="companyModalTitle">Add Company</h3>
            <button class="modal-close" onclick="NexaOps.closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="companyForm" onsubmit="NexaOps.saveCompany(event)">
                <input type="hidden" id="companyId">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="companyName" required class="input-full">
                </div>
                <div class="form-group">
                    <label>Domain</label>
                    <input type="text" id="companyDomain" class="input-full" placeholder="example.com">
                </div>
                <div class="form-group">
                    <label>Industry</label>
                    <input type="text" id="companyIndustry" class="input-full" placeholder="Real Estate Tech">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="NexaOps.closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="appModal">
        <div class="modal-header">
            <h3 id="appModalTitle">Add Application</h3>
            <button class="modal-close" onclick="NexaOps.closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="appForm" onsubmit="NexaOps.saveApp(event)">
                <input type="hidden" id="appId">
                <div class="form-group">
                    <label>Company</label>
                    <select id="appCompanyId" required class="input-full"></select>
                </div>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="appName" required class="input-full">
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" id="appSlug" required class="input-full" placeholder="my-app">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="appType" class="input-full">
                        <option value="web">Web</option>
                        <option value="mobile">Mobile</option>
                        <option value="api">API</option>
                        <option value="ai_service">AI Service</option>
                        <option value="android">Android</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="appDescription" class="input-full" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Base URL</label>
                    <input type="url" id="appBaseUrl" class="input-full" placeholder="https://example.com">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="NexaOps.closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="confirmModal">
        <div class="modal-header">
            <h3>Confirm</h3>
            <button class="modal-close" onclick="NexaOps.closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p id="confirmMessage">Are you sure?</p>
            <div class="form-actions">
                <button type="button" class="btn" onclick="NexaOps.closeModal()">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmBtn" onclick="NexaOps.closeModal()">Delete</button>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '<?= $apiBase ?>';
        const API_KEY  = '<?= $config['api_key'] ?>';
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
