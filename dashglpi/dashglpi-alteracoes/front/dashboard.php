<?php

/**
 * Plugin DashGLPI - Página principal do Dashboard
 *
 * Página standalone autenticada: exige login GLPI mas renderiza
 * seu próprio layout (sem header/footer do GLPI).
 */

require_once __DIR__ . '/../../../inc/includes.php';

// Exige usuário logado
Session::checkLoginUser();

global $CFG_GLPI;

// Caminho base do plugin para o JS
$pluginRoot = $CFG_GLPI['root_doc'] . '/plugins/dashglpi';

// Dados do usuário logado (para o sidebar)
$currentUser = Session::getLoginUserID();
$userName    = getUserName($currentUser);
$userInitials = '';
if (isset($_SESSION['glpiname'])) {
    $parts = explode(' ', $userName);
    $userInitials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
}
if (empty($userInitials)) {
    $userInitials = 'U';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard GLPI Pro</title>
    <link href="<?php echo $pluginRoot; ?>/vendor/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $pluginRoot; ?>/vendor/css/fontawesome.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $pluginRoot; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo $pluginRoot; ?>/css/style-extra.css">
    <script>
        // Passa o root do plugin para o JS
        const DASHGLPI_ROOT = '<?php echo $pluginRoot; ?>';
    </script>
</head>
<body>
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <div class="dg-toast-wrap" id="toastWrap" aria-live="polite"></div>

    <div class="tv-indicator">
        <i class="fas fa-tv"></i> MODO TV ATIVO
    </div>

    <button class="floating-menu-btn" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-terminal"></i>
            </div>
            <div class="sidebar-title">GLPI Pro</div>
            <button class="sidebar-close" onclick="toggleMenu()">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>

        <nav class="menu-nav">
            <a href="#" class="menu-link active" onclick="showPage('dashboard', this)">
                <i class="fas fa-th-large"></i>
                <span>Visão Geral</span>
            </a>
            <a href="#" class="menu-link" onclick="showPage('sla', this)">
                <i class="fas fa-clock"></i>
                <span>Monitor SLA</span>
            </a>
            <a href="#" class="menu-link" onclick="showPage('ranking', this)">
                <i class="fas fa-trophy"></i>
                <span>Ranking Técnicos</span>
            </a>
            <a href="#" class="menu-link" onclick="showPage('tickets', this)">
                <i class="fas fa-ticket-alt"></i>
                <span>Chamados</span>
            </a>
            <a href="#" class="menu-link" onclick="showPage('assets', this)">
                <i class="fas fa-microchip"></i>
                <span>Ativos (Grid)</span>
            </a>
            <div style="flex: 1;"></div>
            <a href="<?php echo $CFG_GLPI['root_doc']; ?>/front/central.php" class="menu-link" title="Voltar ao GLPI">
                <i class="fas fa-arrow-left"></i>
                <span>Voltar ao GLPI</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar"><?php echo htmlspecialchars($userInitials); ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($_SESSION['glpiactiveprofile']['name'] ?? 'Usuário'); ?></div>
                </div>
                <button class="theme-toggle" onclick="toggleTheme()">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>
    </aside>

    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3 class="notification-title">Notificações</h3>
            <button class="notification-clear" onclick="clearNotifications()">Limpar Tudo</button>
        </div>
        <div class="notification-list" id="notificationList">
        </div>
    </div>

    <div class="edit-mode-indicator" id="editModeIndicator">
        <i class="fas fa-edit"></i>
        <span>Modo de Edição Ativo</span>
        <button class="edit-mode-btn" onclick="saveLayout()">Salvar Layout</button>
        <button class="edit-mode-btn" onclick="toggleEditMode()">Sair</button>
    </div>

    <main class="main-content" id="mainContent">
        <div class="page-section active" id="dashboardSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Visão Geral do Serviço</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-clock"></i>
                        <span>Agora: <span id="clock">Carregando...</span></span>
                        <span class="sync-info" id="syncInfo" title="Os dados são atualizados a cada 60 segundos">dados de --:--:--</span>
                        <span class="live-pill" id="livePill"><i class="fas fa-circle"></i><span>Ao vivo</span></span>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="period-switch" role="group" aria-label="Período dos dados">
                        <button type="button" class="period-btn" data-days="7" onclick="setPeriod(7)">7 dias</button>
                        <button type="button" class="period-btn" data-days="30" onclick="setPeriod(30)">30 dias</button>
                        <button type="button" class="period-btn" data-days="90" onclick="setPeriod(90)">90 dias</button>
                        <button type="button" class="period-btn" data-days="0" onclick="setPeriod(0)">Tudo</button>
                    </div>
                    <div class="search-box">
                        <input type="search" id="quickSearch" placeholder="Filtrar atividade  ( / )" aria-label="Filtrar atividade recente">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="refresh-control" id="refreshControl">
                        <button class="icon-btn" id="refreshBtn" type="button" title="Atualizar agora (R)"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <button class="icon-btn" onclick="toggleTVMode()" title="Modo TV (T)">
                        <i class="fas fa-tv"></i>
                    </button>
                    <button class="icon-btn" onclick="toggleNotifications()" title="Notificações">
                        <i class="fas fa-bell"></i>
                        <span class="badge-notification" id="notificationBadge">3</span>
                    </button>
                    <button class="icon-btn" onclick="requestNotificationPermission()" title="Ativar Notificações Desktop">
                        <i class="fas fa-desktop"></i>
                    </button>
                    <button class="icon-btn" id="helpBtn" type="button" title="Atalhos de teclado (?)"><i class="fas fa-keyboard"></i></button>
                </div>
            </header>

            <div class="period-note" id="periodNote" style="display: none;"></div>

            <div class="grid-kpi">
                <div class="glass-card kpi-card glow-success">
                    <div>
                        <span class="kpi-icon success"><i class="fas fa-inbox"></i></span>
                        <div class="kpi-value" id="top-total"><span class="skeleton">00</span></div>
                    </div>
                    <div class="kpi-label">Total de Chamados</div>
                </div>
                <div class="glass-card kpi-card glow-primary">
                    <div>
                        <span class="kpi-icon primary"><i class="fas fa-tasks"></i></span>
                        <div class="kpi-value" id="top-andamento"><span class="skeleton">00</span></div>
                    </div>
                    <div class="kpi-label">Em Andamento</div>
                </div>
                <div class="glass-card kpi-card glow-success">
                    <div>
                        <span class="kpi-icon success"><i class="fas fa-check-circle"></i></span>
                        <div class="kpi-value" id="top-taxa"><span class="skeleton">00%</span></div>
                    </div>
                    <div class="kpi-label">Taxa de Conclusão</div>
                </div>
                <div class="glass-card kpi-card glow-danger">
                    <div>
                        <span class="kpi-icon danger"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="kpi-value" id="top-sla"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">SLA Vencido</div>
                </div>
                <div class="glass-card kpi-card glow-primary">
                    <div>
                        <span class="kpi-icon primary"><i class="fas fa-hourglass-half"></i></span>
                        <div class="kpi-value" id="top-tempo"><span class="skeleton">0h</span></div>
                    </div>
                    <div class="kpi-label">Tempo Médio</div>
                </div>
                <div class="glass-card kpi-card glow-warning">
                    <div>
                        <span class="kpi-icon warning"><i class="fas fa-redo"></i></span>
                        <div class="kpi-value" id="top-reabertos"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">Reabertos</div>
                </div>
            </div>

            <div class="grid-detail">
                <div class="glass-card detail-card glow-success">
                    <div class="detail-card-header">
                        <span class="kpi-icon success"><i class="fas fa-plus-circle"></i></span>
                    </div>
                    <div class="detail-value" id="bot-abertos">0</div>
                    <div class="detail-label">Novos Chamados</div>
                </div>
                <div class="glass-card detail-card glow-primary">
                    <div class="detail-card-header">
                        <span class="kpi-icon primary"><i class="fas fa-user-check"></i></span>
                    </div>
                    <div class="detail-value" id="bot-atribuidos">0</div>
                    <div class="detail-label">Com Técnico</div>
                </div>
                <div class="glass-card detail-card glow-warning">
                    <div class="detail-card-header">
                        <span class="kpi-icon warning"><i class="fas fa-pause-circle"></i></span>
                    </div>
                    <div class="detail-value" id="bot-pendentes">0</div>
                    <div class="detail-label">Aguardando</div>
                </div>
                <div class="glass-card detail-card glow-success">
                    <div class="detail-card-header">
                        <span class="kpi-icon success"><i class="fas fa-check-double"></i></span>
                    </div>
                    <div class="detail-value" id="bot-finalizados">0</div>
                    <div class="detail-label">Resolvidos</div>
                </div>
            </div>

            <div class="grid-insights">
                <div class="glass-card insight-card">
                    <div class="chart-header"><h3 class="chart-title">Saúde do serviço</h3></div>
                    <div class="health-body">
                        <div class="health-ring" id="healthRing" role="img" aria-label="Pontuação de saúde do serviço">
                            <svg viewBox="0 0 120 120" aria-hidden="true">
                                <circle class="ring-bg" cx="60" cy="60" r="52"></circle>
                                <circle class="ring-fg" id="ringFg" cx="60" cy="60" r="52"></circle>
                            </svg>
                            <div class="health-score"><strong id="healthScore">--</strong><small>de 100</small></div>
                        </div>
                        <div class="health-text">
                            <div class="health-label" id="healthLabel">Calculando…</div>
                            <p id="healthHint">Combina taxa de conclusão e chamados com SLA vencido.</p>
                        </div>
                    </div>
                </div>
                <div class="glass-card insight-card">
                    <div class="chart-header"><h3 class="chart-title">Situação dos chamados</h3></div>
                    <div class="chart-container chart-sm"><canvas id="statusChart"></canvas></div>
                </div>
                <div class="glass-card insight-card">
                    <div class="chart-header"><h3 class="chart-title">Proporção sobre o total</h3></div>
                    <div class="meter-list" id="meterList"></div>
                </div>
            </div>

            <div class="grid-charts">
                <div class="glass-card chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Fluxo de Criação</h3>
                        <span id="trendLabel" style="font-size: 0.8rem; color: var(--text-muted);">Últimos 30 dias</span>
                    </div>
                    <div class="chart-container"><canvas id="lineChart"></canvas></div>
                </div>
                <div class="glass-card chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Top Categorias</h3>
                    </div>
                    <div class="chart-container"><canvas id="barChart"></canvas></div>
                </div>
                <div class="glass-card chart-card chart-wide">
                    <div class="chart-header">
                        <h3 class="chart-title">Abertos vs Solucionados (Últimos 6 Meses)</h3>
                    </div>
                    <div class="chart-container"><canvas id="monthlyChart"></canvas></div>
                </div>
            </div>

            <div class="glass-card table-card">
                <div class="table-header">
                    <h3 class="table-title">Atividade Recente</h3>
                    <a href="#" class="table-link" onclick="showPage('tickets', document.querySelectorAll('.menu-link')[3])">
                        Ver Todos os Chamados
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>ID & Título</th>
                                <th>Categoria</th>
                                <th>Criado</th>
                                <th style="text-align: right;">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="recent-tickets-body">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--text-muted);"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SLA Section -->
        <div class="page-section" id="slaSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Monitor de SLA</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-stopwatch"></i>
                        <span>Acompanhamento em tempo real</span>
                    </div>
                </div>
            </header>
            <div class="sla-stats">
                <div class="glass-card sla-stat sla-critical">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="sla-num" id="slaCritical">0</div>
                    <div class="sla-lbl">Crítico</div>
                </div>
                <div class="glass-card sla-stat sla-warning">
                    <i class="fas fa-clock"></i>
                    <div class="sla-num" id="slaWarning">0</div>
                    <div class="sla-lbl">Atenção</div>
                </div>
                <div class="glass-card sla-stat sla-ok">
                    <i class="fas fa-check-circle"></i>
                    <div class="sla-num" id="slaOk">0</div>
                    <div class="sla-lbl">No prazo</div>
                </div>
            </div>
            <div class="glass-card sla-list-card">
                <h3 class="sla-list-title">Chamados próximos ao vencimento</h3>
                <div class="sla-widget" id="slaList"></div>
            </div>
        </div>

        <!-- Ranking Section -->
        <div class="page-section" id="rankingSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Ranking de Técnicos</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-trophy"></i>
                        <span>Performance e Gamificação</span>
                    </div>
                </div>
            </header>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="glass-card h-100">
                        <div class="chart-header">
                            <h3 class="chart-title">Líderes de Atendimento</h3>
                        </div>
                        <div id="leaderboard-container" class="leaderboard-container mt-3">
                            <div class="text-center p-5">
                                <i class="fas fa-spinner fa-spin fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="glass-card text-center mb-4" style="border-color: var(--gold) !important;">
                        <i class="fas fa-crown fa-3x mb-3" style="color: var(--warning);"></i>
                        <h5 style="color: var(--text-main);">Técnico do Mês</h5>
                        <h2 id="top-tech-name" class="fw-bold mt-2" style="color: var(--text-main);">--</h2>
                        <p style="color: var(--text-sec); font-size: 0.85rem;">Maior pontuação acumulada</p>
                    </div>
                    <div class="glass-card">
                        <h5 class="mb-3" style="color: var(--text-main);">Como pontuar?</h5>
                        <ul class="list-unstyled" style="color: var(--text-sec); font-size: 0.85rem;">
                            <li class="mb-2"><i class="fas fa-check me-2" style="color: var(--success);"></i> Chamado Resolvido: +10 pts</li>
                            <li class="mb-2"><i class="fas fa-clock me-2" style="color: var(--primary);"></i> SLA no Prazo: +5 pts</li>
                            <li class="mb-2"><i class="fas fa-star me-2" style="color: var(--warning);"></i> Avaliação 5 estrelas: +20 pts</li>
                            <li><i class="fas fa-minus me-2" style="color: var(--text-muted);"></i> Reabertura: ainda não pontua</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Section -->
        <div class="page-section" id="ticketsSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Todos os Chamados</h1>
                    <div class="page-subtitle"><i class="fas fa-list"></i><span id="ticketCount">Carregando…</span></div>
                </div>
                <div class="header-actions">
                    <button class="icon-btn text-btn" id="exportCsv" type="button"><i class="fas fa-file-csv"></i><span>Exportar CSV</span></button>
                </div>
            </header>
            <div class="glass-card table-card">
                <div class="table-toolbar">
                    <div class="search-box wide">
                        <input type="search" id="ticketFilter" placeholder="Filtrar por ID, título ou técnico  ( / )" aria-label="Filtrar chamados">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="chip-group" id="ticketChips" role="group" aria-label="Filtrar por status"></div>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Status</th>
                                <th>Prioridade</th>
                                <th>Técnico</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody id="tickets-full-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assets Section -->
        <div class="page-section" id="assetsSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Inventário de Ativos</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-network-wired"></i>
                        <span>Monitoramento de Hardware & Grid</span>
                    </div>
                </div>
            </header>
            <div class="table-toolbar">
                <div class="search-box wide">
                    <input type="search" id="assetFilter" placeholder="Filtrar ativos por nome, tipo ou serial  ( / )" aria-label="Filtrar ativos">
                    <i class="fas fa-search"></i>
                </div>
                <div class="chip-group" id="assetChips" role="group" aria-label="Filtrar por situação"></div>
                <span class="toolbar-count" id="assetCount"></span>
            </div>
            <div id="assets-grid" class="assets-grid-container">
                <div class="text-center p-5">
                    <i class="fas fa-circle-notch fa-spin fa-2x"></i> Carregando grid...
                </div>
            </div>
        </div>
    </main>

    <!-- Cyberpunk Asset Modal -->
    <div id="cyber-modal" class="cyber-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-name">
        <div class="cyber-hud">
            <button class="cyber-close" onclick="closeCyberModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="cyber-header">
                <div class="cyber-id-badge">ID: <span id="modal-id">000</span></div>
                <h2 id="modal-name" class="cyber-glitch-text">ASSET_NAME</h2>
                <div class="cyber-sub" id="modal-sub">MODELO / TIPO</div>
            </div>
            <div class="cyber-grid-layout">
                <div class="cyber-col-left">
                    <div class="cyber-box">
                        <div class="cyber-label">SYSTEM STATUS</div>
                        <div class="cyber-status-text" id="modal-status">ONLINE</div>
                    </div>
                    <div class="cyber-box">
                        <div class="cyber-label">OPERATING SYSTEM</div>
                        <div class="cyber-os-display" id="modal-os">
                            <i class="fab fa-windows"></i> Windows 11
                        </div>
                    </div>
                    <div class="cyber-box">
                        <div class="cyber-label">LOCATION DATA</div>
                        <div class="cyber-loc-text" id="modal-loc">TI > SERVIDOR</div>
                    </div>
                </div>
                <div class="cyber-col-right">
                    <div class="cyber-stat-row">
                        <div class="stat-label"><i class="fas fa-microchip"></i> CPU CORE</div>
                        <div class="stat-value-text" id="modal-cpu">Intel Core i7</div>
                        <div class="cyber-progress-bg">
                            <div class="cyber-progress-fill" style="width: 80%"></div>
                        </div>
                    </div>
                    <div class="cyber-stat-row">
                        <div class="stat-label"><i class="fas fa-memory"></i> MEMORY MODULE</div>
                        <div class="stat-value-text" id="modal-ram">16 GB</div>
                        <div class="cyber-progress-bg">
                            <div class="cyber-progress-fill" id="bar-ram" style="width: 40%"></div>
                        </div>
                    </div>
                    <div class="cyber-stat-row">
                        <div class="stat-label"><i class="fas fa-hdd"></i> STORAGE UNIT</div>
                        <div class="stat-value-text" id="modal-hdd">512 GB SSD</div>
                        <div class="cyber-progress-bg">
                            <div class="cyber-progress-fill" id="bar-hdd" style="width: 60%"></div>
                        </div>
                    </div>
                    <div class="cyber-stat-row">
                        <div class="stat-label"><i class="fas fa-barcode"></i> SERIAL KEY</div>
                        <div class="stat-value-text mono" id="modal-serial">CN-0X554-...</div>
                        <button class="copy-btn" type="button" data-copy="#modal-serial"><i class="far fa-copy"></i> Copiar serial</button>
                    </div>
                </div>
            </div>
            <div class="cyber-footer">
                <div class="cyber-scanline"></div>
                <span>SECURE CONNECTION ESTABLISHED // ACCESS GRANTED</span>
            </div>
        </div>
    </div>

    <div class="dg-overlay" id="shortcutsOverlay" hidden role="dialog" aria-modal="true" aria-labelledby="shortcutsTitle">
        <div class="dg-dialog">
            <div class="dg-dialog-head">
                <h3 id="shortcutsTitle">Atalhos de teclado</h3>
                <button type="button" class="icon-btn" data-close aria-label="Fechar"><i class="fas fa-times"></i></button>
            </div>
            <dl class="shortcut-list">
                <div><dt><kbd>1</kbd> a <kbd>5</kbd></dt><dd>Trocar de página</dd></div>
                <div><dt><kbd>/</kbd></dt><dd>Ir para a busca da página</dd></div>
                <div><dt><kbd>R</kbd></dt><dd>Atualizar agora</dd></div>
                <div><dt><kbd>T</kbd></dt><dd>Modo TV</dd></div>
                <div><dt><kbd>M</kbd></dt><dd>Abrir ou fechar o menu</dd></div>
                <div><dt><kbd>Esc</kbd></dt><dd>Fechar janelas</dd></div>
            </dl>
        </div>
    </div>

    <script src="<?php echo $pluginRoot; ?>/vendor/js/chart.umd.min.js"></script>
    <script src="<?php echo $pluginRoot; ?>/js/script.js"></script>
    <script src="<?php echo $pluginRoot; ?>/js/script-extra.js"></script>
</body>
</html>
