const NexaOps = (() => {
    let currentView = 'dashboard';
    let logsOffset  = 0;

    function init() {
        setupNav();
        loadDashboard();
    }

    // ── Navigation ───────────────────────────────────────────
    function setupNav() {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const view = item.dataset.view;
                if (!view) return;
                switchView(view);
            });
        });
    }

    function switchView(view) {
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.querySelector(`.nav-item[data-view="${view}"]`)?.classList.add('active');

        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        const el = document.getElementById('view-' + view);
        if (el) el.classList.add('active');

        document.getElementById('pageTitle').textContent =
            document.querySelector(`.nav-item[data-view="${view}"] span`)?.textContent || view;

        currentView = view;
        loadView(view);
    }

    function loadView(view) {
        switch (view) {
            case 'dashboard':    loadDashboard(); break;
            case 'companies':    loadCompanies(); break;
            case 'apps':         loadApps(); break;
            case 'logs':         loadLogs(); break;
            case 'ai':           loadAI(); break;
            case 'integrations': loadIntegrations(); break;
        }
    }

    async function loadIntegrations() {
        const sel = document.getElementById('integrationAppSelect');
        sel.innerHTML = '<option value="">— Choose an app —</option>';
        const data = await api('/apps');
        (data?.apps || []).forEach(a => {
            sel.innerHTML += `<option value="${a.id}">${esc(a.name)} (${esc(a.company_name || '—')})</option>`;
        });
        document.getElementById('integrationDetails').style.display = 'none';
    }

    // ── API ──────────────────────────────────────────────────
    async function api(path, opts = {}) {
        try {
            const resp = await fetch(API_BASE + path, {
                headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json', ...opts.headers },
                ...opts,
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || `HTTP ${resp.status}`);
            }
            return await resp.json();
        } catch (err) {
            console.error('API Error:', err);
            document.getElementById('statusDot').style.background = '#ef4444';
            return null;
        }
    }

    // ── Dashboard ────────────────────────────────────────────
    async function loadDashboard() {
        const data = await api('/dashboard/overview');
        if (!data) return;

        document.getElementById('statusDot').style.background = '#22c55e';
        const t = data.totals || {};

        setText('statApps',      t.apps || 0);
        setText('statCompanies', t.companies || 0);
        setText('statLogs',      fmt(t.logs_7d || 0));
        setText('statAICalls',   fmt(t.ai_calls_7d || 0));
        setText('statAICost',    '$' + (t.ai_cost_7d || 0).toFixed(2));
        setText('statUsers',     t.active_users || 0);

        const tbody = document.querySelector('#appStatsTable tbody');
        tbody.innerHTML = '';
        (data.app_stats || []).forEach(row => {
            tbody.innerHTML += `<tr class="clickable" onclick="NexaOps.viewApp(${row.id})">
                <td>${esc(row.name)}</td>
                <td>${fmt(row.log_count)}</td>
                <td>${fmt(row.unique_users)}</td>
                <td>${fmt(row.unique_actions)}</td>
            </tr>`;
        });

        const actionsDiv = document.getElementById('topActionsList');
        actionsDiv.innerHTML = '';
        (data.top_actions || []).forEach(row => {
            actionsDiv.innerHTML += `<div class="action-bar" style="cursor:pointer" onclick="document.getElementById('logSearch').value='${esc(row.action)}';NexaOps.switchView('logs')">
                <span class="action-name">${esc(row.action)}</span>
                <span class="badge badge-type">${esc(row.app_name || '—')}</span>
                <span class="action-count">${fmt(row.cnt)}</span>
            </div>`;
        });

        renderLogFeed('liveFeed', data.recent_logs || []);
    }

    // ════════════════════════════════════════════════════════════
    //  COMPANIES
    // ════════════════════════════════════════════════════════════

    async function loadCompanies() {
        const data = await api('/companies');
        if (!data) return;

        const tbody = document.querySelector('#companiesTable tbody');
        tbody.innerHTML = '';
        (data.companies || []).forEach(c => {
            tbody.innerHTML += `<tr class="clickable" onclick="NexaOps.viewCompany(${c.id})">
                <td><strong>${esc(c.name)}</strong></td>
                <td class="text-dim text-sm">${esc(c.domain || '—')}</td>
                <td class="text-dim text-sm">${esc(c.industry || '—')}</td>
                <td>${c.app_count || 0}</td>
                <td>
                    <button class="btn btn-xs" onclick="event.stopPropagation();NexaOps.showCompanyForm(${c.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-xs" onclick="event.stopPropagation();NexaOps.confirmDelete('company', ${c.id}, '${esc(c.name)}')"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        });
    }

    async function showCompanyForm(id = null) {
        document.getElementById('companyModalTitle').textContent = id ? 'Edit Company' : 'Add Company';
        document.getElementById('companyId').value = id || '';
        document.getElementById('companyName').value = '';
        document.getElementById('companyDomain').value = '';
        document.getElementById('companyIndustry').value = '';

        if (id) {
            const data = await api(`/companies/${id}`);
            if (data && !data.error) {
                document.getElementById('companyName').value = data.name || '';
                document.getElementById('companyDomain').value = data.domain || '';
                document.getElementById('companyIndustry').value = data.industry || '';
            }
        }
        openModal('companyModal');
    }

    async function saveCompany(e) {
        e.preventDefault();
        const id = document.getElementById('companyId').value;
        const body = {
            name: document.getElementById('companyName').value,
            domain: document.getElementById('companyDomain').value,
            industry: document.getElementById('companyIndustry').value,
        };

        const method = id ? 'PUT' : 'POST';
        const url = id ? `/companies/${id}` : '/companies';
        const res = await api(url, { method, body: JSON.stringify(body) });
        if (res) { closeModal(); loadCompanies(); }
    }

    function confirmDelete(type, id, label) {
        document.getElementById('confirmMessage').textContent = `Delete "${label}"? This cannot be undone.`;
        const btn = document.getElementById('confirmBtn');
        btn.onclick = async () => {
            closeModal();
            const res = await api(`/${type}s/${id}`, { method: 'DELETE' });
            if (res) loadView(currentView);
        };
        openModal('confirmModal');
    }

    async function viewCompany(id) {
        const data = await api(`/companies/${id}`);
        if (!data || data.error) return;
        const c = data;
        const appsData = await api('/apps');
        const apps = (appsData?.apps || []).filter(a => Number(a.company_id) === Number(id));

        switchView('company-detail');
        document.getElementById('pageTitle').textContent = c.name;
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.getElementById('companyDetailName').textContent = c.name;

        const body = document.getElementById('companyDetailBody');
        body.innerHTML = `
            <div class="stats-grid" style="margin-bottom:16px">
                <div class="stat-card">
                    <div class="stat-icon teal"><i class="fas fa-building"></i></div>
                    <div>
                        <span class="stat-value">${esc(c.name)}</span>
                        <span class="stat-label">${esc(c.domain || '—')} · ${esc(c.industry || '—')}</span>
                    </div>
                </div>
            </div>
            <div class="card" style="border:1px solid var(--border);border-radius:12px;overflow:hidden">
                <div class="card-header">
                    <h3><i class="fas fa-cubes"></i> Applications (${apps.length})</h3>
                    <button class="btn btn-sm" onclick="NexaOps.showAppForm(null, ${id})"><i class="fas fa-plus"></i> Add App</button>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead><tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Logs</th>
                            <th>AI Calls</th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                            ${apps.map(a => `<tr class="clickable" onclick="NexaOps.viewApp(${a.id})">
                                <td><strong>${esc(a.name)}</strong><br><small class="text-dim">${esc(a.slug)}</small></td>
                                <td><span class="badge badge-type">${esc(a.type)}</span></td>
                                <td><span class="badge ${a.status === 'active' ? 'badge-active' : 'badge-inactive'}">${esc(a.status)}</span></td>
                                <td>${fmt(a.total_logs)}</td>
                                <td>${fmt(a.ai_calls)}</td>
                                <td>
                                    <button class="btn btn-xs" onclick="event.stopPropagation();NexaOps.showAppForm(${a.id})"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-xs" onclick="event.stopPropagation();NexaOps.confirmDelete('app', ${a.id}, '${esc(a.name)}')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>`).join('') || '<tr><td colspan="6" class="text-dim" style="text-align:center;padding:20px">No apps for this company.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    function backToCompanies() { switchView('companies'); loadCompanies(); }

    // ════════════════════════════════════════════════════════════
    //  APPS
    // ════════════════════════════════════════════════════════════

    async function loadApps() {
        const data = await api('/apps');
        if (!data) return;

        populateCompanyFilter('appCompanyFilter', '');

        const tbody = document.querySelector('#appsTable tbody');
        tbody.innerHTML = '';
        const select = document.getElementById('logAppFilter');
        select.innerHTML = '<option value="">All Apps</option>';

        (data.apps || []).forEach(app => {
            const sc = app.status === 'active' ? 'badge-active' : 'badge-inactive';
            tbody.innerHTML += `<tr class="clickable" onclick="NexaOps.viewApp(${app.id})">
                <td><strong>${esc(app.name)}</strong><br><small class="text-dim">${esc(app.slug)}</small></td>
                <td><span class="badge badge-type">${esc(app.type)}</span></td>
                <td class="text-sm text-dim">${esc(app.company_name || '—')}</td>
                <td>${fmt(app.total_logs)}</td>
                <td>${fmt(app.ai_calls)}</td>
                <td><span class="badge ${sc}">${esc(app.status)}</span></td>
                <td>
                    <button class="btn btn-xs" onclick="event.stopPropagation();NexaOps.showAppForm(${app.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-xs" onclick="event.stopPropagation();NexaOps.confirmDelete('app', ${app.id}, '${esc(app.name)}')"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
            select.innerHTML += `<option value="${app.id}">${esc(app.name)}</option>`;
        });
    }

    async function showAppForm(id = null, presetCompanyId = null) {
        document.getElementById('appModalTitle').textContent = id ? 'Edit Application' : 'Add Application';
        document.getElementById('appId').value = id || '';
        document.getElementById('appName').value = '';
        document.getElementById('appSlug').value = '';
        document.getElementById('appType').value = 'web';
        document.getElementById('appDescription').value = '';
        document.getElementById('appBaseUrl').value = '';

        const sel = document.getElementById('appCompanyId');
        sel.innerHTML = '<option value="">Select company...</option>';
        const companies = await api('/companies');
        (companies?.companies || []).forEach(c => {
            sel.innerHTML += `<option value="${c.id}">${esc(c.name)}</option>`;
        });
        if (presetCompanyId) sel.value = presetCompanyId;

        if (id) {
            const data = await api(`/apps/${id}`);
            if (data?.app) {
                document.getElementById('appName').value = data.app.name || '';
                document.getElementById('appSlug').value = data.app.slug || '';
                document.getElementById('appType').value = data.app.type || 'web';
                document.getElementById('appDescription').value = data.app.description || '';
                document.getElementById('appBaseUrl').value = data.app.base_url || '';
                if (data.app.company_id) sel.value = data.app.company_id;
            }
        }
        openModal('appModal');
    }

    async function saveApp(e) {
        e.preventDefault();
        const id = document.getElementById('appId').value;
        const body = {
            company_id: document.getElementById('appCompanyId').value,
            name: document.getElementById('appName').value,
            slug: document.getElementById('appSlug').value,
            type: document.getElementById('appType').value,
            description: document.getElementById('appDescription').value,
            base_url: document.getElementById('appBaseUrl').value,
        };

        const method = id ? 'PUT' : 'POST';
        const url = id ? `/apps/${id}` : '/apps';
        const res = await api(url, { method, body: JSON.stringify(body) });
        if (res) { closeModal(); loadView(currentView); }
    }

    // ── App Detail (drill-down) ─────────────────────────────
    async function viewApp(id) {
        const data = await api(`/apps/${id}`);
        if (!data?.app) return;
        const a = data.app;

        switchView('app-detail');
        document.getElementById('pageTitle').textContent = a.name;
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.getElementById('appDetailName').textContent = a.name;

        const body = document.getElementById('appDetailBody');
        body.innerHTML = `
            <div class="stats-grid" style="margin-bottom:16px">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-cube"></i></div>
                    <div>
                        <span class="stat-value">${esc(a.name)}</span>
                        <span class="stat-label">${esc(a.slug)} · ${esc(a.type)} · ${esc(a.company_name || '—')}</span>
                    </div>
                </div>
            </div>
            <div class="card" style="border:1px solid var(--border);border-radius:12px;overflow:hidden">
                <div class="card-header">
                    <h3><i class="fas fa-scroll"></i> Recent Logs</h3>
                    <span class="text-dim text-sm">API Key: <code style="color:var(--accent)">${esc(a.api_key || '—')}</code></span>
                </div>
                <div class="card-body" style="padding:0">
                    <div class="log-feed" id="appDetailLogFeed"></div>
                </div>
            </div>
            <div class="card" style="margin-top:12px;border:1px solid var(--border);border-radius:12px;overflow:hidden">
                <div class="card-header">
                    <h3><i class="fas fa-brain"></i> AI Usage</h3>
                </div>
                <div class="card-body">
                    <div id="appDetailAI"></div>
                </div>
            </div>
        `;

        const logsData = await api(`/logs/search?limit=50&app_id=${id}`);
        renderLogFeed('appDetailLogFeed', logsData?.logs || []);

        const ai = await api(`/ai/stats?app_id=${id}&days=7`);
        const aidiv = document.getElementById('appDetailAI');
        if (ai?.totals) {
            aidiv.innerHTML = `
                <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
                    <div class="stat-card"><span class="stat-value">${fmt(Number(ai.totals.total_calls) || 0)}</span><span class="stat-label">Calls</span></div>
                    <div class="stat-card"><span class="stat-value">${fmt(Number(ai.totals.total_tokens) || 0)}</span><span class="stat-label">Tokens</span></div>
                    <div class="stat-card"><span class="stat-value">$${(Number(ai.totals.total_cost) || 0).toFixed(4)}</span><span class="stat-label">Cost</span></div>
                </div>`;
        } else {
            aidiv.innerHTML = '<p class="text-dim">No AI usage yet.</p>';
        }
    }

    function backToApps() { switchView('apps'); loadApps(); }

    // ── Logs ─────────────────────────────────────────────────
    async function loadLogs() {
        logsOffset = 0;
        populateCompanyFilter('logCompanyFilter', '');
        await searchLogs();
    }

    async function searchLogs() {
        const search = document.getElementById('logSearch').value;
        const appId  = document.getElementById('logAppFilter').value;
        const level  = document.getElementById('logLevelFilter').value;
        const companyId = document.getElementById('logCompanyFilter').value;

        let params = new URLSearchParams({ limit: 100, offset: logsOffset });
        if (search)   params.set('q', search);
        if (appId)    params.set('app_id', appId);
        if (level)    params.set('level', level);
        if (companyId) params.set('company_id', companyId);

        const data = await api('/logs/search?' + params.toString());
        if (!data) return;

        renderLogFeed('logsFeed', data.logs || []);

        const total = data.total || 0;
        const pag = document.getElementById('logsPagination');
        pag.innerHTML = `
            <span style="color:var(--text-dim);font-size:0.82rem">
                Showing ${logsOffset + 1}–${Math.min(logsOffset + 100, total)} of ${fmt(total)}
            </span>
            <button class="btn btn-xs" onclick="NexaOps.prevPage()" ${logsOffset === 0 ? 'disabled' : ''}>Prev</button>
            <button class="btn btn-xs" onclick="NexaOps.nextPage()" ${logsOffset + 100 >= total ? 'disabled' : ''}>Next</button>
        `;
    }

    function nextPage() { logsOffset += 100; searchLogs(); }
    function prevPage() { logsOffset = Math.max(0, logsOffset - 100); searchLogs(); }

    // ── AI Usage ─────────────────────────────────────────────
    async function loadAI() {
        const data = await api('/ai/global?days=7');
        if (!data) return;

        const t = data.totals || {};
        setText('aiStatCalls',   fmt(t.total_calls || 0));
        setText('aiStatTokens',  fmt(t.total_tokens || 0));
        setText('aiStatCost', '$' + (Number(t.total_cost) || 0).toFixed(4));
        setText('aiStatLatency', Math.round(Number(t.avg_latency) || 0) + 'ms');

        const provDiv = document.getElementById('aiByProvider');
        provDiv.innerHTML = '';
        (data.by_provider || []).forEach(row => {
            provDiv.innerHTML += `<div class="action-bar">
                <span class="action-name">${esc(row.provider)}</span>
                <span class="action-count">${fmt(row.calls)} calls</span>
            </div>`;
        });

        const appDiv = document.getElementById('aiByApp');
        appDiv.innerHTML = '';
        (data.by_app || []).forEach(row => {
            appDiv.innerHTML += `<div class="action-bar">
                <span class="action-name">${esc(row.app_name || 'Unknown')}</span>
                <span style="color:var(--text-dim);font-size:0.8rem">${fmt(row.tokens)} tokens</span>
                <span class="action-count">${fmt(row.calls)}</span>
            </div>`;
        });

        const chartDiv = document.getElementById('aiDailyChart');
        chartDiv.innerHTML = '';
        const daily = data.daily || [];
        if (daily.length === 0) {
            chartDiv.innerHTML = '<p style="color:var(--text-dim)">No AI usage data in the past 7 days.</p>';
            return;
        }
        const maxCalls = Math.max(...daily.map(d => d.calls), 1);
        daily.forEach(d => {
            const pct = Math.round((d.calls / maxCalls) * 100);
            chartDiv.innerHTML += `<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                <span style="width:80px;font-size:0.8rem;color:var(--text-dim)">${d.day}</span>
                <div style="flex:1;background:var(--bg-dark);border-radius:4px;height:24px;overflow:hidden">
                    <div style="height:100%;width:${pct}%;background:linear-gradient(90deg,var(--accent),var(--purple));border-radius:4px;transition:width 0.5s"></div>
                </div>
                <span style="width:60px;text-align:right;font-size:0.8rem;font-weight:600">${fmt(d.calls)}</span>
                <span style="width:60px;text-align:right;font-size:0.75rem;color:var(--text-dim)">$${(d.cost || 0).toFixed(3)}</span>
            </div>`;
        });
    }

    // ── Sync ─────────────────────────────────────────────────
    async function syncLogs() {
        const data = await api('/collect/sync');
        if (data) {
            alert(`Sync complete: ${data.synced} logs ingested from ${data.sources} sources.`);
            loadView(currentView);
        }
    }

    // ── Integrations ────────────────────────────────────────
    function showIntegration() {
        const el = document.getElementById('integrationAppSelect');
        const id = el.value;
        const details = document.getElementById('integrationDetails');
        if (!id) { details.style.display = 'none'; return; }

        api('/apps').then(data => {
            const app = (data?.apps || []).find(a => Number(a.id) === Number(id));
            if (!app) return;
            details.style.display = 'block';
            const key = app.api_key || '—';
            const endpoint = API_BASE.replace('/api', '');
            document.getElementById('integrationApiKey').textContent = key;
            document.getElementById('integrationEndpoint').textContent = endpoint + '/api/collect/log';

            const escCode = (s) => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            document.getElementById('integrationCodePhp').innerHTML = escCode(
`require_once 'integrations/php/NexaOpsClient.php';

$nexa = new NexaOpsClient('${key}');
$nexa->log('LOGIN', 'User john@example.com logged in');
$nexa->aiUsage([
    'model' => 'gpt-4o',
    'tokens_prompt' => 500,
    'tokens_completion' => 200,
    'cost_usd' => 0.008,
]);`
            );
            document.getElementById('integrationCodePy').innerHTML = escCode(
`import requests

logs = [{"action": "LOGIN", "description": "User john@example.com logged in"}]
ai = [{"model": "gpt-4o", "tokens_prompt": 500, "tokens_completion": 200, "cost_usd": 0.008}]

requests.post("${endpoint}/api/collect/log",
    json={"logs": logs},
    headers={"X-API-Key": "${key}", "Content-Type": "application/json"})

requests.post("${endpoint}/api/collect/ai-usage",
    json={"usage": ai},
    headers={"X-API-Key": "${key}", "Content-Type": "application/json"})`
            );
            document.getElementById('integrationCodeCurl').innerHTML = escCode(
`curl -X POST ${endpoint}/api/collect/log \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${key}" \\
  -d '{"logs":[{"action":"DEPLOY","description":"v2.1 deployed"}]}'`
            );
        });
    }

    // ── Modal Mgmt ──────────────────────────────────────────
    function openModal(id) {
        document.getElementById('modalOverlay').classList.add('open');
        document.getElementById(id).classList.add('open');
    }

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('open');
        document.querySelectorAll('.modal').forEach(m => m.classList.remove('open'));
    }

    // ── Helpers ──────────────────────────────────────────────
    function populateCompanyFilter(selectId, selectedVal) {
        const sel = document.getElementById(selectId);
        if (!sel) return;
        sel.innerHTML = '<option value="">All Companies</option>';
        api('/companies').then(data => {
            (data?.companies || []).forEach(c => {
                sel.innerHTML += `<option value="${c.id}" ${String(c.id) === String(selectedVal) ? 'selected' : ''}>${esc(c.name)}</option>`;
            });
        });
    }

    function renderLogFeed(containerId, logs) {
        const el = document.getElementById(containerId);
        if (!el) return;
        el.innerHTML = '';
        if (!logs.length) {
            el.innerHTML = '<p style="padding:20px;color:var(--text-dim)">No logs yet.</p>';
            return;
        }
        logs.forEach(log => {
            const lc = log.level === 'error' ? 'badge-error'
                : log.level === 'warning' ? 'badge-warning' : 'badge-info';
            el.innerHTML += `<div class="log-line">
                <span class="log-time">${esc(log.created_at || '')}</span>
                <span class="log-app" ${log.app_id ? `style="cursor:pointer" onclick="NexaOps.viewApp(${log.app_id})"` : ''}>${esc(log.app_name || 'system')}</span>
                <span class="badge ${lc}">${esc(log.level || 'info')}</span>
                <span class="log-action">${esc(log.action || '')}</span>
                <span class="log-msg">${esc(log.description || log.user_id || '')}</span>
            </div>`;
        });
    }

    function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }

    function fmt(n) {
        if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
        return String(n);
    }

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function refresh() { loadView(currentView); }

    document.addEventListener('DOMContentLoaded', init);

    return {
        refresh, syncLogs, searchLogs, switchView,
        loadRecentLogs: () => api('/logs/recent?limit=30').then(d => renderLogFeed('liveFeed', d?.logs || [])),
        nextPage, prevPage,
        // Companies
        loadCompanies, showCompanyForm, saveCompany, viewCompany, backToCompanies,
        // Apps
        showAppForm, saveApp, viewApp, backToApps,
        // Logs
        loadLogs: () => { logsOffset = 0; populateCompanyFilter('logCompanyFilter', ''); searchLogs(); },
        loadAI,
        // Modals
        openModal, closeModal, confirmDelete,
    };
})();
