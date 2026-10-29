/* DashGLPI - funcionalidades extras (carregar DEPOIS de script.js)
   Não substitui o script.js: lê os valores que ele já escreve na tela e
   reaproveita updateData(), renderLeaderboard() e loadFullAssets(). */
(function () {
    'use strict';

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const store = {
        get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* ignora */ } }
    };
    const clean = (t) => (t || '').replace(/\s+/g, ' ').trim();

    /* ---------- Toasts ---------- */
    function toast(msg, type) {
        const wrap = $('#toastWrap');
        if (!wrap) return;
        const t = document.createElement('div');
        t.className = 'dg-toast ' + (type || '');
        t.textContent = msg;
        wrap.appendChild(t);
        setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 250); }, 2800);
    }
    window.dgToast = toast;

    /* ---------- Navegação: lembra a última página ---------- */
    const PAGES = ['dashboard', 'sla', 'ranking', 'tickets', 'assets'];
    function go(name) {
        const i = PAGES.indexOf(name);
        if (i < 0 || typeof window.showPage !== 'function') return;
        window.showPage(name, $$('.menu-link')[i]);
    }
    function rememberPage() {
        const original = window.showPage;
        if (typeof original !== 'function') return;
        window.showPage = function (name) {
            // não grava durante a rotação automática do Modo TV
            if (typeof tvMode === 'undefined' || !tvMode) store.set('dg_page', name);
            return original.apply(this, arguments);
        };
    }

    /* ---------- Chips reutilizáveis ---------- */
    function renderChips(box, values, current, onPick) {
        if (!box) return;
        box.innerHTML = '';
        [''].concat(values).forEach((v) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'chip' + (v === current ? ' active' : '');
            b.setAttribute('aria-pressed', v === current);
            b.textContent = v || 'Todos';
            b.addEventListener('click', () => onPick(v));
            box.appendChild(b);
        });
    }

    /* ---------- Insights: saúde, rosca e proporções ---------- */
    const SRC = {
        total: 'top-total', taxa: 'top-taxa', sla: 'top-sla',
        novos: 'bot-abertos', atrib: 'bot-atribuidos', pend: 'bot-pendentes', fin: 'bot-finalizados'
    };
    let donut = null;
    const lastText = {};

    function readNum(id) {
        const el = document.getElementById(id);
        if (!el || el.querySelector('.skeleton') || el.classList.contains('skeleton')) return null;
        const n = parseFloat(el.textContent.replace(',', '.').replace(/[^\d.]/g, ''));
        return isNaN(n) ? null : n;
    }

    function flashChanges() {
        Object.values(SRC).forEach((id) => {
            const el = document.getElementById(id);
            if (!el || el.querySelector('.skeleton')) return;
            const txt = el.textContent.trim();
            if (lastText[id] !== undefined && lastText[id] !== txt) {
                const card = el.closest('.glass-card');
                if (card) { card.classList.remove('kpi-updated'); void card.offsetWidth; card.classList.add('kpi-updated'); }
            }
            lastText[id] = txt;
        });
    }

    function updateInsights() {
        flashChanges();
        const d = {};
        Object.keys(SRC).forEach((k) => { d[k] = readNum(SRC[k]); });
        if (d.total === null || d.taxa === null) return;

        const total = Math.max(d.total, 1);
        // SLA vencido é de todos os chamados abertos; compara com o total geral, não com o do período
        const base = Math.max(typeof window.dashTotalAll === 'number' ? window.dashTotalAll : total, 1);
        const slaPct = ((d.sla || 0) / base) * 100;
        const score = Math.round(Math.max(0, Math.min(100, d.taxa - slaPct)));

        const ring = $('#healthRing'), fg = $('#ringFg');
        if (ring && fg) {
            fg.style.strokeDashoffset = String(326.73 * (1 - score / 100));
            ring.classList.toggle('state-warn', score < 80 && score >= 50);
            ring.classList.toggle('state-bad', score < 50);
            $('#healthScore').textContent = score;
            $('#healthLabel').textContent = score >= 80 ? 'Excelente' : score >= 65 ? 'Estável' : score >= 50 ? 'Requer atenção' : 'Crítico';
            $('#healthHint').textContent = (d.sla || 0) + ' chamados com SLA vencido (' + slaPct.toFixed(1).replace('.', ',') + '% do total) e ' + d.taxa + '% de conclusão.';
        }

        const meters = [
            ['Resolvidos', d.fin, 'success'],
            ['Com técnico', d.atrib, 'primary'],
            ['Aguardando', d.pend, 'warning'],
            ['SLA vencido', d.sla, 'danger']
        ];
        const list = $('#meterList');
        if (list) {
            list.innerHTML = meters.map((m) => {
                const v = m[1] || 0, pct = Math.min(100, (v / (m[2] === 'danger' ? base : total)) * 100);
                return '<div><div class="meter-top"><span>' + m[0] + '</span><b>' + v + ' <small>(' + pct.toFixed(0) + '%)</small></b></div>' +
                    '<div class="meter-track"><div class="meter-fill ' + m[2] + '" style="width:' + pct + '%"></div></div></div>';
            }).join('');
        }

        const parts = [d.novos, d.atrib, d.pend, d.fin];
        const canvas = $('#statusChart');
        if (canvas && typeof Chart !== 'undefined' && parts.every((v) => v !== null)) {
            if (donut) {
                donut.data.datasets[0].data = parts;
                donut.update();
            } else {
                donut = new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Novos', 'Com técnico', 'Aguardando', 'Resolvidos'],
                        datasets: [{ data: parts, borderWidth: 0, backgroundColor: ['#06b6d4', '#3b82f6', '#f59e0b', '#22c55e'] }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '68%',
                        plugins: { legend: { position: 'bottom', labels: { color: Chart.defaults.color, usePointStyle: true, boxWidth: 8 } } }
                    }
                });
            }
        }
    }

    function watchKpis() {
        let queued = false;
        const run = () => { queued = false; updateInsights(); };
        const obs = new MutationObserver(() => { if (!queued) { queued = true; requestAnimationFrame(run); } });
        Object.values(SRC).forEach((id) => {
            const el = document.getElementById(id);
            if (el) obs.observe(el, { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: ['class'] });
        });
        updateInsights();

        // o script.js recria os gráficos ao trocar de tema; a rosca acompanha
        let wasLight = document.body.classList.contains('light-mode');
        new MutationObserver(() => {
            const isLight = document.body.classList.contains('light-mode');
            if (isLight === wasLight) return;
            wasLight = isLight;
            if (donut) { donut.destroy(); donut = null; }
            setTimeout(updateInsights, 50);
        }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }

    /* ---------- Filtros de tabela ---------- */
    function setupTable(cfg) {
        const tbody = document.getElementById(cfg.tbody);
        const input = document.getElementById(cfg.input);
        if (!tbody || !input) return;
        const chipsBox = cfg.chips ? document.getElementById(cfg.chips) : null;
        const count = cfg.count ? document.getElementById(cfg.count) : null;
        let status = '';

        const rows = () => Array.from(tbody.rows).filter((r) => r.cells.length > 1);
        const statusOf = (r) => clean(r.cells[cfg.statusCol] && r.cells[cfg.statusCol].textContent);

        function apply() {
            const q = input.value.trim().toLowerCase();
            const all = rows();
            let shown = 0;
            all.forEach((r) => {
                const ok = (!q || r.textContent.toLowerCase().includes(q)) && (!status || statusOf(r) === status);
                r.hidden = !ok;
                if (ok) shown++;
            });
            if (count) count.textContent = all.length ? shown + ' de ' + all.length + ' chamados' : 'Nenhum chamado carregado';
        }

        function buildChips() {
            const values = Array.from(new Set(rows().map(statusOf).filter(Boolean)));
            if (status && !values.includes(status)) status = '';
            renderChips(chipsBox, values, status, (v) => { status = v; buildChips(); apply(); });
        }

        input.addEventListener('input', apply);
        // o script.js recria as linhas a cada atualização; o filtro é reaplicado
        new MutationObserver(() => { buildChips(); apply(); }).observe(tbody, { childList: true });
        buildChips();
        apply();
    }

    function setupAssetFilter() {
        const grid = $('#assets-grid'), input = $('#assetFilter'), count = $('#assetCount'), chipsBox = $('#assetChips');
        if (!grid || !input) return;
        let status = '';

        const data = () => (typeof assetsDataGlobal !== 'undefined' && Array.isArray(assetsDataGlobal)) ? assetsDataGlobal : [];
        const cards = () => Array.from(grid.querySelectorAll('.asset-card-cyber'));
        const assetOf = (card) => {
            const m = (card.getAttribute('onclick') || '').match(/openCyberModal\((\d+)\)/);
            return (m && data()[+m[1]]) || {};
        };
        const statusOf = (a) => a.status || 'Sem situação';

        function apply() {
            const q = input.value.trim().toLowerCase();
            const all = cards();
            let shown = 0;
            all.forEach((k) => {
                const a = assetOf(k);
                const hay = [a.name, a.model, a.manufacturer, a.serial, a.location, a.os_name, a.status].join(' ').toLowerCase();
                const ok = (!q || hay.includes(q)) && (!status || statusOf(a) === status);
                k.hidden = !ok;
                if (ok) shown++;
            });
            if (count) count.textContent = all.length ? shown + ' de ' + all.length + ' ativos' : '';
        }
        function buildChips() {
            const values = Array.from(new Set(cards().map((k) => statusOf(assetOf(k)))));
            if (status && !values.includes(status)) status = '';
            renderChips(chipsBox, values, status, (v) => { status = v; buildChips(); apply(); });
        }

        input.addEventListener('input', apply);
        new MutationObserver(() => { buildChips(); apply(); }).observe(grid, { childList: true });
        buildChips();
        apply();
    }

    /* ---------- Exportar CSV ---------- */
    function setupExport() {
        const btn = $('#exportCsv');
        if (!btn) return;
        btn.addEventListener('click', () => {
            const table = $('#ticketsSection table');
            const rows = table ? Array.from(table.rows).filter((r) => !r.hidden && r.cells.length > 1) : [];
            if (rows.length < 2) { toast('Não há chamados para exportar.', 'warn'); return; }
            const esc = (t) => '"' + clean(t).replace(/"/g, '""') + '"';
            const csv = rows.map((r) => Array.from(r.cells).map((c) => esc(c.textContent)).join(';')).join('\r\n');
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }));
            a.download = 'chamados-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            toast((rows.length - 1) + ' chamados exportados.', 'ok');
        });
    }

    /* ---------- Atualizar agora (sem recarregar a página) ---------- */
    function wrapUpdateData() {
        if (typeof window.updateData !== 'function') return;
        const original = window.updateData;
        window.updateData = async function () {
            const result = await original.apply(this, arguments);
            const el = $('#syncInfo');
            if (el) {
                const total = document.getElementById('top-total');
                const ok = !!total && !total.querySelector('.skeleton') && !total.classList.contains('skeleton');
                el.textContent = ok ? 'dados de ' + new Date().toLocaleTimeString('pt-BR') : 'falha ao carregar dados';
            }
            return result;
        };
    }

    function setupRefresh() {
        const btn = $('#refreshBtn'), box = $('#refreshControl');
        if (!btn) return null;

        async function refreshNow() {
            if (box.classList.contains('spinning')) return;
            box.classList.add('spinning');
            try {
                const jobs = [window.updateData()];
                const active = $('.page-section.active');
                if (active && active.id === 'rankingSection' && typeof renderLeaderboard === 'function') jobs.push(renderLeaderboard());
                if (active && active.id === 'assetsSection' && typeof loadFullAssets === 'function') jobs.push(loadFullAssets());
                await Promise.all(jobs);
                toast('Dados atualizados.', 'ok');
            } catch (e) {
                toast('Não foi possível atualizar. Tente de novo.', 'warn');
            } finally {
                box.classList.remove('spinning');
            }
        }
        btn.addEventListener('click', refreshNow);
        return refreshNow;
    }

    /* ---------- Conexão ---------- */
    function setupNetwork() {
        const pill = $('#livePill');
        if (!pill) return;
        const label = pill.querySelector('span');
        const sync = () => {
            pill.classList.toggle('off', !navigator.onLine);
            label.textContent = navigator.onLine ? 'Ao vivo' : 'Sem conexão';
        };
        window.addEventListener('online', sync);
        window.addEventListener('offline', sync);
        sync();
    }

    /* ---------- Atalhos e diálogo de ajuda ---------- */
    function setupShortcuts(refreshNow) {
        const overlay = $('#shortcutsOverlay');
        let opener = null;

        function openHelp() {
            if (!overlay) return;
            opener = document.activeElement;
            overlay.hidden = false;
            const c = $('[data-close]', overlay);
            if (c) c.focus();
        }
        function closeHelp() {
            if (!overlay || overlay.hidden) return;
            overlay.hidden = true;
            if (opener && opener.focus) opener.focus();
        }
        if (overlay) {
            overlay.addEventListener('click', (e) => { if (e.target === overlay || e.target.closest('[data-close]')) closeHelp(); });
        }
        const helpBtn = $('#helpBtn');
        if (helpBtn) helpBtn.addEventListener('click', openHelp);

        const searchFor = { dashboardSection: 'quickSearch', ticketsSection: 'ticketFilter', assetsSection: 'assetFilter' };

        document.addEventListener('keydown', (e) => {
            const tag = (e.target.tagName || '').toLowerCase();
            const typing = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;

            if (e.key === 'Escape') {
                if (typing) e.target.blur();
                closeHelp();
                const modal = $('#cyber-modal');
                if (modal && modal.classList.contains('active') && typeof window.closeCyberModal === 'function') window.closeCyberModal();
                return;
            }
            if (typing || e.ctrlKey || e.metaKey || e.altKey) return;

            const key = e.key.toLowerCase();
            if (/^[1-5]$/.test(key)) { go(PAGES[+key - 1]); return; }
            if (key === '/') {
                const section = $('.page-section.active');
                const target = section && document.getElementById(searchFor[section.id]);
                if (target) { e.preventDefault(); target.focus(); }
                return;
            }
            if (key === '?') { openHelp(); return; }
            if (key === 't' && typeof window.toggleTVMode === 'function') { window.toggleTVMode(); return; }
            if (key === 'm' && typeof window.toggleMenu === 'function') { window.toggleMenu(); return; }
            if (key === 'r' && refreshNow) { refreshNow(); }
        });
    }

    /* ---------- Copiar serial ---------- */
    function setupCopy() {
        document.addEventListener('click', (e) => {
            const b = e.target.closest('[data-copy]');
            if (!b) return;
            const src = $(b.getAttribute('data-copy'));
            const text = src ? src.textContent.trim() : '';
            if (!text || !navigator.clipboard) { toast('Não foi possível copiar.', 'warn'); return; }
            navigator.clipboard.writeText(text)
                .then(() => toast('Serial copiado.', 'ok'))
                .catch(() => toast('Não foi possível copiar.', 'warn'));
        });
    }

    /* ---------- Rótulos de acessibilidade ---------- */
    function labelButtons() {
        const map = { '.floating-menu-btn': 'Abrir menu', '.sidebar-close': 'Fechar menu', '.theme-toggle': 'Alternar tema', '.cyber-close': 'Fechar detalhes do ativo', '.notification-clear': 'Limpar notificações' };
        Object.keys(map).forEach((sel) => $$(sel).forEach((b) => { if (!b.getAttribute('aria-label')) b.setAttribute('aria-label', map[sel]); }));
        $$('.icon-btn[title]').forEach((b) => { if (!b.getAttribute('aria-label')) b.setAttribute('aria-label', b.title); });
    }

    /* ---------- Início ---------- */
    function init() {
        labelButtons();
        rememberPage();
        setupNetwork();
        watchKpis();
        setupTable({ tbody: 'recent-tickets-body', input: 'quickSearch', statusCol: 0 });
        setupTable({ tbody: 'tickets-full-body', input: 'ticketFilter', chips: 'ticketChips', count: 'ticketCount', statusCol: 2 });
        setupAssetFilter();
        setupExport();
        setupCopy();
        setupShortcuts(setupRefresh());

        const saved = store.get('dg_page');
        if (saved && saved !== 'dashboard') go(saved);
    }

    wrapUpdateData(); // precisa rodar antes do DOMContentLoaded do script.js
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
