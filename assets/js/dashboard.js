/**
 * NexaOps Dashboard — Client JS
 */
const NexaOps = (() => {
    let currentView = 'dashboard';
    let logsOffset  = 0;

    // ── Init ─────────────────────────────────────────────────
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

                document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
                item.classList.add('active');

                document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
                const el = document.getElementById('view-' + view);
                if (el) el.classList.add('active');

                document.getElementById('pageTitle').textContent =
                    item.querySelector('span')?.textContent || view;

                currentView = view;
                loadView(view);
            });
        });
    }

    function loadView(view) {
        switch (view) {
            case 'dashboard':    loadDashboard(); break;
            case 'apps':         loadApps(); break;
            case 'logs':         loadLogs(); break;
            case 'ai':           loadAI(); break;
            case 'integrations': break;
        }
    }

    // ── API Helper ───────────────────────────────────────────
    async function api(path) {
        try {
            const resp = await fetch(API_BASE + path, {
                headers: { 'X-API-Key': API_KEY }
            });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
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

        setText('statApps',     t.apps || 0);
        setText('statCompanies', t.companies || 0);
        setText('statLogs',     fmt(t.logs_7d || 0));
        setText('statAICalls',  fmt(t.ai_calls_7d || 0));
        setText('statAICost',   '$' + (t.ai_cost_7d || 0).toFixed(2));
        setText('statUsers',    t.active_users || 0);

        // App stats table
        const tbody = document.querySelector('#appStatsTable tbody');
        tbody.innerHTML = '';
        (data.app_stats || []).forEach(row => {
            tbody.innerHTML += `<tr>
                <td>${esc(row.name)}</td>
                <td>${fmt(row.log_count)}</td>
                <td>${fmt(row.unique_users)}</td>
                <td>${fmt(row.unique_actions)}</td>
            </tr>`;
        });

        // Top actions
        const actionsDiv = document.getElementById('topActionsList');
        actionsDiv.innerHTML = '';
        (data.top_actions || []).forEach(row => {
            actionsDiv.innerHTML += `<div class="action-bar">
                <span class="action-name">${esc(row.action)}</span>
                <span class="badge badge-type">${esc(row.app_name || '—')}</span>
                <span class="action-count">${fmt(row.cnt)}</span>
            </div>`;
        });

        // Live feed
        renderLogFeed('liveFeed', data.recent_logs || []);
    }

    // ── Apps ─────────────────────────────────────────────────
    async function loadApps() {
        const data = await api('/apps');
        if (!data) return;

        const tbody = document.querySelector('#appsTable tbody');
        tbody.innerHTML = '';

        // Also populate log filter dropdown
        const select = document.getElementById('logAppFilter');
        select.innerHTML = '<option value="">All Apps</option>';

        (data.apps || []).forEach(app => {
            const statusClass = app.status === 'active' ? 'badge-active' : 'badge-inactive';
            tbody.innerHTML += `<tr>
                <td><strong>${esc(app.name)}</strong><br><small style="color:var(--text-dim)">${esc(app.slug)}</small></td>
                <td><span class="badge badge-type">${esc(app.type)}</span></td>
                <td>${esc(app.company_name || '—')}</td>
                <td>${fmt(app.total_logs)}</td>
                <td>${fmt(app.ai_calls)}</td>
                <td><span class="badge ${statusClass}">${esc(app.status)}</span></td>
                <td><button class="btn btn-xs" onclick="NexaOps.viewApp(${app.id})"><i class="fas fa-eye"></i></button></td>
            </tr>`;

            select.innerHTML += `<option value="${app.id}">${esc(app.name)}</option>`;
        });
    }

    // ── Logs ─────────────────────────────────────────────────
    async function loadLogs() {
        logsOffset = 0;
        await searchLogs();
    }

    async function searchLogs() {
        const search = document.getElementById('logSearch').value;
        const appId  = document.getElementById('logAppFilter').value;
        const level  = document.getElementById('logLevelFilter').value;

        let params = new URLSearchParams({
            limit: 100,
            offset: logsOffset,
        });
        if (search) params.set('q', search);
        if (appId)  params.set('app_id', appId);
        if (level)  params.set('level', level);

        const data = await api('/logs/search?' + params.toString());
        if (!data) return;

        renderLogFeed('logsFeed', data.logs || []);

        // Pagination
        const total = data.total || 0;
        const pag = document.getElementById('logsPagination');
        const pages = Math.ceil(total / 100);
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
        setText('aiStatCost',    '$' + (t.total_cost || 0).toFixed(4));
        setText('aiStatLatency', Math.round(t.avg_latency || 0) + 'ms');

        // By provider
        const provDiv = document.getElementById('aiByProvider');
        provDiv.innerHTML = '';
        (data.by_provider || []).forEach(row => {
            provDiv.innerHTML += `<div class="action-bar">
                <span class="action-name">${esc(row.provider)}</span>
                <span class="action-count">${fmt(row.calls)} calls</span>
            </div>`;
        });

        // By app
        const appDiv = document.getElementById('aiByApp');
        appDiv.innerHTML = '';
        (data.by_app || []).forEach(row => {
            appDiv.innerHTML += `<div class="action-bar">
                <span class="action-name">${esc(row.app_name || 'Unknown')}</span>
                <span style="color:var(--text-dim);font-size:0.8rem">${fmt(row.tokens)} tokens</span>
                <span class="action-count">${fmt(row.calls)}</span>
            </div>`;
        });

        // Daily chart (simple text-based bar chart)
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

    // ── Helpers ──────────────────────────────────────────────
    function renderLogFeed(containerId, logs) {
        const el = document.getElementById(containerId);
        el.innerHTML = '';
        if (!logs.length) {
            el.innerHTML = '<p style="padding:20px;color:var(--text-dim)">No logs yet. Logs appear here when apps start sending data.</p>';
            return;
        }
        logs.forEach(log => {
            const levelClass = log.level === 'error' ? 'badge-error'
                : log.level === 'warning' ? 'badge-warning' : 'badge-info';
            el.innerHTML += `<div class="log-line">
                <span class="log-time">${esc(log.created_at || '')}</span>
                <span class="log-app">${esc(log.app_name || 'system')}</span>
                <span class="badge ${levelClass}">${esc(log.level || 'info')}</span>
                <span class="log-action">${esc(log.action || '')}</span>
                <span class="log-msg">${esc(log.description || log.user_id || '')}</span>
            </div>`;
        });
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

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
    function viewApp(id) { alert('App detail view — coming soon (ID: ' + id + ')'); }

    // ── Public API ───────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', init);

    return {
        refresh,
        syncLogs,
        searchLogs,
        loadRecentLogs: () => api('/logs/recent?limit=30').then(d => renderLogFeed('liveFeed', d?.logs || [])),
        nextPage,
        prevPage,
        viewApp,
    };
})();
