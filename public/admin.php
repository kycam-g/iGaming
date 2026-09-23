<?php

declare(strict_types=1);
use App\Core\Support\Env;
$fallbackAppName=(string)Env::get('APP_NAME','MZ90');
$siteName=(string)((($settings['site_name'] ?? '') !== '') ? $settings['site_name'] : $fallbackAppName);
function ah(string $v): string { return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" id="browser-favicon" href="data:,">
<title><?=ah($siteName)?> Admin</title>
<link rel="stylesheet" href="/admin-assets/admin.css?v=20260922-v23-completa">
</head>
<body>
<div class="admin-shell login-mode" id="admin-shell">
<aside class="sidebar hidden" id="admin-sidebar">
    <div class="admin-brand admin-brand-textonly"><strong id="admin-brand-name" class="admin-brand-word"><span class="admin-brand-first"><?=ah(substr($siteName,0,1))?></span><span class="admin-brand-rest"><?=ah(substr($siteName,1))?></span></strong><small class="admin-brand-subtitle">ADMIN</small></div>
    <nav class="admin-nav">
        <button class="nav-item nav-active" data-page="dashboard"><span>⌂</span> Dashboard</button>
        <div class="nav-group"><span>GESTÃO</span></div>
        <button class="nav-item" data-page="users"><span>♙</span> Usuários</button>
        <button class="nav-item" data-page="finance"><span>◈</span> Financeiro</button>
        <button class="nav-item" data-page="gateways"><span>▣</span> Gateways</button>
        <div class="nav-group"><span>PLATAFORMA</span></div>
        <div class="casino-nav-group collapsed"><button class="nav-item casino-nav-parent" type="button" data-page="casino" aria-expanded="false" aria-controls="casino-admin-submenu"><span>♠</span> Jogos <svg class="casino-nav-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button><div class="casino-subnav" id="casino-admin-submenu"><button type="button" class="casino-subitem" data-casino-tab="games">♧ Catálogo de jogos</button><button type="button" class="casino-subitem" data-casino-tab="categories">◈ Categorias</button><button type="button" class="casino-subitem" data-casino-tab="providers">▣ Provedores</button><button type="button" class="casino-subitem" data-casino-tab="credentials">⚿ Credenciais das APIs</button><button type="button" class="casino-subitem" data-casino-tab="bets" disabled title="Depende de integração real">◴ Histórico de apostas <small>Em preparação</small></button><button type="button" class="casino-subitem" data-casino-tab="rtp" disabled title="Depende de apostas liquidadas">▥ RTP observado <small>Em preparação</small></button></div></div>
        <div class="promotion-admin-group collapsed"><button class="nav-item promotion-admin-parent" type="button" data-page="promotions" aria-expanded="false" aria-controls="promotion-admin-submenu"><span>◆</span> Promoções <svg class="casino-nav-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button><div class="promotion-admin-subnav" id="promotion-admin-submenu"><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="vip">♛ Níveis VIP</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="coupons">▣ Cupons</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="checkin">▦ Check-in diário</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="roulette">◌ Giro da Sorte</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="envelope">✉ Envelope vermelho</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="chests">⬡ Baús e indicações</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="agency">♧ Agência</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="rebate">↺ Rebate</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="rescue">▤ Fundos de Resgate</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="cashwheel">◉ Roleta de Saque</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="lottery">✧ Sorteio</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="bonus-history">♙ Histórico de bônus</button><button type="button" class="promotion-admin-subitem" data-admin-promotion-tab="level-history">☷ Histórico de níveis</button></div></div>
        <button class="nav-item" data-page="affiliates"><span>♧</span> Afiliados</button>
        <button class="nav-item" data-page="appearance" type="button"><span>◉</span> Aparência</button>
        <div class="nav-group"><span>SISTEMA</span></div>
        <button class="nav-item" data-page="settings"><span>⚙</span> Configurações</button>
        <button class="nav-item" data-page="audit"><span>▤</span> Logs / Auditoria</button>
    </nav>
    <button id="admin-logout" class="logout hidden">Sair</button>
</aside>
<main>
<section id="admin-login" class="login-card">
    <span>PAINEL ADMINISTRATIVO</span><h1>Entrar</h1><p>Use sua conta administrativa.</p>
    <div id="login-alert" class="alert hidden"></div>
    <form id="admin-login-form"><label>E-mail<input name="email" type="email" required></label><label>Senha<input name="password" type="password" required></label><button>ENTRAR</button></form>
</section>
<section id="admin-app" class="hidden">
    <header class="admin-topbar">
        <button id="menu-toggle" class="menu-toggle" aria-label="Menu">☰</button>
        <div class="admin-welcome"><strong>Bem-vindo, Admin!</strong><small>Gerencie sua plataforma de forma simples e eficiente.</small></div>
        <div class="admin-topbar-actions"><a class="visit-site" href="/" target="_blank" rel="noopener noreferrer">Ver site ↗</a><div class="admin-profile"><span class="admin-profile-avatar">A</span><span class="admin-profile-text"><strong id="admin-name"></strong><small>Administrador</small></span></div></div>
    </header>
    <div class="page-heading"><span id="page-kicker">VISÃO GERAL</span><h1 id="page-title">Dashboard</h1><p id="page-description">Resumo operacional da plataforma.</p></div>

    <section id="page-dashboard" class="admin-page">
        <div id="dashboard-alert" class="alert hidden"></div>
        <div class="dashboard-actions"><span id="dashboard-updated">Atualizando...</span><button id="refresh-dashboard" class="secondary">Atualizar</button></div>
        <div class="metric-grid">
            <article class="metric-card"><span>Usuários Online</span><strong id="m-users-online">—</strong></article>
            <article class="metric-card"><span>Total cadastros</span><strong id="m-users-total">—</strong></article>
            <article class="metric-card"><span>Cadastros hoje</span><strong id="m-users-today">—</strong></article>
            <article class="metric-card"><span>Cadastros 90D</span><strong id="m-users-90d">—</strong></article>
            <article class="metric-card"><span>Total de Saldos dos Usuários</span><strong id="m-balances">—</strong></article>
            <article class="metric-card"><span>Lucro</span><strong id="m-profit">—</strong><small id="m-profit-note" class="metric-note hidden">Aguardando integração dos jogos</small></article>
            <article class="metric-card"><span>Depósitos Sem Link</span><strong id="m-deposit-no-link">—</strong></article>
            <article class="metric-card"><span>Depósitos Blogueiros</span><strong id="m-deposit-bloggers">—</strong></article>
            <article class="metric-card"><span>Saques Blogueiros</span><strong id="m-withdraw-bloggers">—</strong></article>
            <article class="metric-card"><span>Saques Sem Link</span><strong id="m-withdraw-no-link">—</strong></article>
            <article class="metric-card"><span>Acessos total</span><strong id="m-accesses">—</strong></article>
            <article class="metric-card"><span>Lugar Mais Acessado</span><strong class="location" id="m-location">—</strong></article>
        </div>

        <div class="chart-grid">
            <article class="panel"><div class="panel-title"><h2>Saques Diários</h2><span>Últimos 14 dias</span></div><div id="withdrawals-chart" class="bar-chart"></div></article>
            <article class="panel"><div class="panel-title"><h2>Depósitos Diários</h2><span>Últimos 14 dias</span></div><div id="deposits-chart" class="bar-chart"></div></article>
        </div>

        <div class="table-grid">
            <article class="panel"><div class="panel-title"><h2>Saques Aprovados</h2><span>Últimos 5 saques aprovados.</span></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Usuário</th><th>Data/Hora</th><th>Valor</th></tr></thead><tbody id="latest-withdrawals"></tbody></table></div></article>
            <article class="panel"><div class="panel-title"><h2>Depósitos Pagos</h2><span>Últimos 5 depósitos pagos.</span></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Usuário</th><th>Data/Hora</th><th>Valor</th></tr></thead><tbody id="latest-deposits"></tbody></table></div></article>
        </div>
    </section>

    <section id="page-users" class="admin-page hidden">
        <div id="users-alert" class="alert hidden"></div>
        <div class="users-toolbar">
            <div class="search-box"><input id="users-search" type="search" placeholder="Buscar por ID, usuário, e-mail, CPF ou telefone"><button id="search-users">Buscar</button></div>
            <select id="users-status"><option value="">Todos os status</option><option value="ACTIVE">Ativos</option><option value="SUSPENDED">Suspensos</option><option value="BLOCKED">Bloqueados</option></select>
            <button id="refresh-users" class="secondary">Atualizar</button>
        </div>
        <article class="panel users-panel">
            <div class="panel-title inline"><div><h2>Usuários</h2><span id="users-count">Carregando...</span></div></div>
            <div class="table-wrap"><table class="users-table"><thead><tr><th>ID</th><th>Usuário</th><th>CPF / Telefone</th><th>Status</th><th>Saldo</th><th>Bônus</th><th>Total depositado</th><th>Último acesso</th><th>Cadastro</th><th></th></tr></thead><tbody id="users-table-body"></tbody></table></div>
            <div class="pagination"><button id="users-prev" class="secondary">← Anterior</button><span id="users-page">Página 1</span><button id="users-next" class="secondary">Próxima →</button></div>
        </article>
    </section>

    <section id="page-casino" class="admin-page hidden">
      <div id="casino-alert" class="alert hidden"></div>
      <div class="dashboard-actions"><span>Catálogo, abertura de jogos e callback PlayFiver integrados ao ledger.</span><button id="casino-refresh" class="secondary-action">Atualizar</button></div>
      <article class="panel casino-panel" id="casino-providers-panel" style="margin-bottom:20px"><div class="panel-title"><h2>Provedores</h2><span>Cadastro e ativação independente.</span><button type="button" class="gold-button" id="new-provider">+ Novo provedor</button></div>
      
      <div class="table-wrap"><table><thead><tr><th>Logo</th><th>Código</th><th>Nome</th><th>Modo</th><th>API</th><th>Status</th><th></th></tr></thead><tbody id="casino-providers-body"></tbody></table></div></article>
      <article class="panel casino-panel hidden" id="casino-categories-panel" style="margin-bottom:20px"><div class="panel-title"><div><h2>Categorias de jogos</h2><span>Gerencie Slots, Pescaria, SportBet, Roleta e outras categorias exibidas na home.</span></div><button type="button" class="gold-button" id="new-category">+ Nova categoria</button></div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Nome</th><th>Ícone</th><th>Ordem</th><th>Status</th><th>Ações</th></tr></thead><tbody id="casino-categories-body"></tbody></table></div></article>
      <article class="panel casino-panel hidden" id="casino-credentials-panel"><div class="panel-title"><h2>PlayFiver · Integração</h2><span>Credenciais criptografadas, lançamento de jogos e callback financeiro. O catálogo PlayFiver é local e versionado por migration.</span></div>
      <div id="playfiver-alert" class="alert hidden"></div>
      <p id="playfiver-status">Carregando configuração...</p>
      <form id="playfiver-form" class="casino-form" autocomplete="off">
        <label>URL da API de jogos<input name="base_url" type="url" required value="https://api.playfivers.com"></label>
        <label>Agent Code<input name="agent_code" maxlength="1024" autocomplete="off" placeholder="Em branco: preservar credencial salva"></label>
        <label>Agent Token<input name="agent_token" type="password" maxlength="1024" autocomplete="new-password" placeholder="Em branco: preservar credencial salva"></label>
        <label>Agent Secret<input name="agent_secret" type="password" maxlength="1024" autocomplete="new-password" placeholder="Em branco: preservar credencial salva"></label>
        <label class="casino-check"><input name="enabled" type="checkbox"> Ativar integração PlayFiver</label>
        <button type="submit" class="gold-button casino-save">SALVAR INTEGRAÇÃO</button>
      </form>
      <div class="playfiver-callback-box"><strong>Callback PlayFiver</strong><code id="playfiver-callback-url">/api/webhooks/casino/playfiver</code><small>Em produção, configure este caminho em um domínio HTTPS público.</small></div>
      </article>
      <article class="panel casino-panel games-api-panel" id="casino-games-panel">
        <div class="panel-title games-api-title"><div><h2>Gerenciamento de Jogos API</h2><span>Catálogo local PlayFiver + jogos manuais. As logos dos provedores continuam cadastradas manualmente.</span></div><div class="games-api-title-actions"><button type="button" class="gold-button" id="new-game">＋ Adicionar Jogo</button></div></div><div class="casino-sync-status">O catálogo PlayFiver é instalado e atualizado localmente por migration. As logos dos provedores permanecem manuais.</div>
        <form id="casino-game-filters" class="games-api-filters" autocomplete="off">
          <div class="games-api-search"><input id="casino-game-search" type="search" aria-label="Buscar por nome ou código" placeholder="Buscar por nome do jogo ou código..."><button type="submit" class="gold-button">⌕ Buscar</button></div>
          <select id="casino-game-api-filter" aria-label="Filtrar API"><option value="">Todas as APIs</option></select>
          <select id="casino-game-provider-filter" aria-label="Filtrar provedor"><option value="">Todos os Provedores</option></select>
        </form>
        <div class="table-wrap games-api-table-wrap"><table class="games-api-table"><thead><tr><th>Banner</th><th>Nome</th><th>Código</th><th>Provider</th><th>API</th><th>Acessos</th><th>Status</th><th>Popular</th><th>Ações</th></tr></thead><tbody id="casino-games-body"></tbody></table></div>
        <div class="games-api-footer"><span id="casino-games-count" aria-live="polite">Carregando jogos...</span><div class="games-api-pagination"><button type="button" class="secondary" id="casino-games-prev" aria-label="Página anterior">‹</button><span id="casino-games-page">Página 1 de 1</span><button type="button" class="secondary" id="casino-games-next" aria-label="Próxima página">›</button></div><label>Exibir <select id="casino-games-limit"><option value="8">8</option><option value="15">15</option><option value="25">25</option><option value="50">50</option></select> por página</label></div>
        <div class="games-api-info"><strong>ⓘ Informações</strong><div><span>Os jogos PlayFiver documentados são mantidos no banco pelas migrations do projeto.</span><span>Use a busca para localizar jogos rapidamente.</span><span>Marque como Popular para destacar na home.</span><span>Desativar o status oculta o jogo do catálogo público.</span></div></div>
      </article>
    </section>

    <section id="page-finance" class="admin-page hidden">
        <div id="finance-alert" class="alert hidden"></div>
        <div class="finance-metrics">
            <article class="mini-card"><span>TOTAL PAGO</span><strong id="fin-paid">R$ 0,00</strong><small id="fin-paid-count">0 depósitos</small></article>
            <article class="mini-card"><span>AGUARDANDO</span><strong id="fin-pending">R$ 0,00</strong><small id="fin-pending-count">0 depósitos</small></article>
            <article class="mini-card"><span>FALHAS / REVISÃO</span><strong id="fin-failed">0</strong><small>transações</small></article>
            <article class="mini-card"><span>EXPIRADOS</span><strong id="fin-expired">0</strong><small>transações</small></article>
        </div>
        <div class="finance-toolbar">
            <input id="finance-search" type="search" placeholder="ID, external ID, usuário #ID, CPF ou telefone">
            <select id="finance-status"><option value="">Todos os status</option><option value="PAID">Pago</option><option value="PENDING">Pendente</option><option value="PROCESSING">Processando</option><option value="EXPIRED">Expirado</option><option value="FAILED">Falhou</option><option value="REVIEW">Revisão</option><option value="CANCELLED">Cancelado</option></select>
            <select id="finance-gateway"><option value="">Todos os gateways</option></select>
            <input id="finance-from" type="date" title="Data inicial"><input id="finance-to" type="date" title="Data final">
            <button id="finance-filter" class="gold-button">FILTRAR</button><button id="finance-refresh" class="secondary">Atualizar</button>
        </div>
        <article class="panel users-panel" style="margin-bottom:20px"><div class="panel-title inline"><div><h2>Auditoria de conciliação local</h2><span>Somente leitura • não consulta a Pixup • não altera saldo</span></div><button id="reconcile-refresh" class="secondary">VERIFICAR DIVERGÊNCIAS</button></div><p id="reconcile-note" class="muted">Clique para verificar pendências e lançamentos locais.</p><div class="table-wrap"><table class="finance-table"><thead><tr><th>Depósito</th><th>Usuário</th><th>Status</th><th>Valor</th><th>Motivo</th><th></th></tr></thead><tbody id="reconcile-body"><tr><td colspan="6">Aguardando verificação.</td></tr></tbody></table></div></article>
        <article class="panel users-panel"><div class="panel-title inline"><div><h2>Depósitos</h2><span id="finance-count">Carregando...</span></div></div>
            <div class="table-wrap"><table class="finance-table"><thead><tr><th>ID</th><th>Usuário</th><th>Gateway</th><th>Valor</th><th>Status</th><th>External ID</th><th>Criado</th><th></th></tr></thead><tbody id="finance-body"></tbody></table></div>
            <div class="pagination"><button id="finance-prev" class="secondary">← Anterior</button><span id="finance-page">Página 1</span><button id="finance-next" class="secondary">Próxima →</button></div>
        </article>
    </section>


<section id="page-settings" class="admin-page hidden"><article class="panel"><div class="panel-title"><h2>Configurações gerais</h2><span>Identidade e disponibilidade da plataforma.</span></div><div id="settings-alert" class="alert hidden"></div><form id="settings-form" class="platform-form"><label>Nome do site<input name="site_name" maxlength="120" required></label><label>E-mail de suporte<input name="support_email" type="email"></label><label>Cor principal<input name="accent_color" type="color"></label><label class="checkline"><input name="maintenance" type="checkbox"> Ativar modo manutenção (site público indisponível)</label><button type="submit" class="gold-button">Salvar configurações</button></form></article></section>
<section id="page-appearance" class="admin-page hidden"><div class="appearance-intro"><h2>Aparência</h2><p>Gerencie a identidade visual e os elementos da plataforma.</p></div><div class="appearance-tiles"><button type="button" data-appearance-tab="identity"><span>▧</span><strong>Identidade visual</strong><small>Logo e favicon</small></button><button type="button" data-appearance-tab="banners" class="selected"><span>▧</span><strong>Banners da plataforma</strong><small>Carrossel, lobby e lateral</small></button><button type="button" data-appearance-tab="announcements"><span>✦</span><strong>Novidades</strong><small>Textos em movimento</small></button><button type="button" data-appearance-tab="themes"><span>▦</span><strong>Temas e layout</strong><small>Configurações visuais</small></button><button type="button" data-appearance-tab="popups"><span>▣</span><strong>Pop-ups e modais</strong><small>Anúncios configuráveis</small></button><button type="button" data-appearance-tab="floats"><span>▤</span><strong>Ícones flutuantes</strong><small>Atalhos e links</small></button><button type="button" data-appearance-tab="app"><span>↓</span><strong>Download do aplicativo</strong><small>Área do app</small></button></div>
<div class="appearance-content" data-appearance-panel="banners"><article class="panel appearance-panel"><div class="panel-title"><h2>Banners da plataforma</h2><span>Carrossel principal e banners do lobby são gerenciados separadamente.</span></div><div class="appearance-tabs"><button type="button" class="active" data-banner-filter="home">Carrossel principal (Home)</button><button type="button" data-banner-filter="casino">Banners do lobby (3 posições)</button></div><div id="banner-section-note" class="appearance-section-note"></div><div id="banner-size-guide" class="asset-size-guide">Carrossel principal: recomendado 1920 × 600 px (proporção 16:5). PNG, JPG ou WebP, até 2 MB. Ajuste a área importante ao centro para telas menores.</div><div id="banners-list" class="platform-list appearance-banner-list"></div><div class="appearance-form-heading"><h3 id="banner-form-title">Novo banner do carrossel</h3><button type="button" class="secondary" id="banner-form-toggle">+ Novo banner</button></div><div id="banners-alert" class="alert hidden"></div></article></div><div class="appearance-content hidden" data-appearance-panel="identity"><article class="panel appearance-panel"><div class="panel-title"><h2>Identidade visual</h2><span>Personalize a identidade da plataforma diretamente aqui.</span></div><div id="identity-alert" class="alert hidden"></div><form id="identity-form" class="identity-form-v10">
<div class="identity-fields"><label>Nome da plataforma<input name="site_name" maxlength="120" required></label><label>E-mail de suporte<input name="support_email" type="email" placeholder="suporte@exemplo.com"></label><label>Cor principal<input name="accent_color" type="color"></label><label class="identity-maintenance"><input name="maintenance" type="checkbox"> Ativar modo manutenção (site público indisponível)</label></div>
<div class="identity-images"><div class="identity-upload-card"><label for="identity-logo-file">Logo da plataforma</label><small>Recomendado: 600 × 200 px (proporção 3:1). PNG transparente, JPG ou WebP; até 2 MB.</small><input id="identity-logo-file" name="logo_file" type="file" accept="image/png,image/jpeg,image/webp"><input type="hidden" name="logo_path"><div class="identity-preview-frame"><img id="identity-logo-preview" class="identity-preview hidden" alt="Prévia da logo"></div></div><div class="identity-upload-card"><label for="identity-favicon-file">Favicon</label><small>Recomendado: 512 × 512 px (quadrado). PNG, JPG ou WebP; até 2 MB.</small><input id="identity-favicon-file" name="favicon_file" type="file" accept="image/png,image/jpeg,image/webp"><input type="hidden" name="favicon_path"><div class="identity-preview-frame"><img id="identity-favicon-preview" class="identity-preview identity-favicon hidden" alt="Prévia do favicon"></div></div></div>
<div class="footer-editor"><h3>Rodapé, contato e redes sociais</h3><p class="hint">Preencha apenas os canais oficiais. Links devem começar com https://. Nenhum selo de certificação é exibido sem comprovação.</p><div class="footer-editor-grid"><label>Texto de apresentação do rodapé ("Conheça a MZ90...")<textarea name="footer_about" maxlength="500" rows="4" placeholder="Conheça a MZ90: entretenimento online com responsabilidade..."></textarea><small>Este texto é exibido logo abaixo da sua logo na Home. Altere e salve para atualizar sem mexer no código.</small></label><label>Telefone de contato<input name="contact_phone" maxlength="24" placeholder="(21) 99999-9999"></label><label>WhatsApp (URL HTTPS)<input name="social_whatsapp" type="url" maxlength="255" placeholder="https://wa.me/..."></label><label>Telegram (URL HTTPS)<input name="social_telegram" type="url" maxlength="255" placeholder="https://t.me/..."></label><label>Instagram (URL HTTPS)<input name="social_instagram" type="url" maxlength="255" placeholder="https://www.instagram.com/..."></label><label>Facebook (URL HTTPS)<input name="social_facebook" type="url" maxlength="255" placeholder="https://www.facebook.com/..."></label></div></div><div class="identity-form-actions"><button type="submit" class="gold-button">Salvar identidade visual e rodapé</button></div></form></article></div>

<div class="appearance-content hidden" data-appearance-panel="announcements"><article class="panel appearance-panel"><div class="panel-title"><div><h2>Novidades e lançamentos</h2><span>Textos exibidos em sequência na faixa animada da home. Só novidades ativas aparecem para o jogador.</span></div><button type="button" id="new-announcement" class="gold-button">+ Nova novidade</button></div><div id="announcements-alert" class="alert hidden"></div><div id="announcements-list" class="platform-list"></div></article></div>
<div class="appearance-content hidden" data-appearance-panel="themes"><article class="panel appearance-panel"><h2>Temas e layout</h2><p class="hint">O tema Ember vermelho e laranja está ativo. A troca de layouts ainda não foi implementada.</p></article></div>
<div class="appearance-content hidden" data-appearance-panel="popups"><article class="panel appearance-panel"><h2>Pop-ups e modais</h2><p class="hint">Módulo em preparação. Nenhum anúncio será exibido automaticamente.</p></article></div>
<div class="appearance-content hidden" data-appearance-panel="floats"><article class="panel appearance-panel"><h2>Ícones flutuantes</h2><p class="hint">Módulo em preparação. Nenhum ícone será publicado sem configuração.</p></article></div>
<div class="appearance-content hidden" data-appearance-panel="app"><article class="panel appearance-panel"><h2>Download do aplicativo</h2><p class="hint">Ainda não há aplicativo disponível para download. Esta seção será ativada quando houver um pacote validado.</p></article></div></section>
<section id="page-promotions" class="admin-page hidden">
<article class="panel mz-admin-promo-overview" id="mz-promo-oversight">
  <div class="panel-title"><div><h2>Central administrativa de promoções</h2><span>Campanhas, registros financeiros e histórico • consulta somente leitura</span></div><button type="button" class="secondary" id="mz-oversight-refresh">ATUALIZAR</button></div>
  <p id="mz-oversight-message" class="hint" role="status">Consulte os dados registrados no banco.</p>
  <div id="mz-oversight-stats" class="mz-admin-promo-stats"></div>
  <details class="mz-admin-promo-details"><summary>Campanhas por módulo</summary><div class="table-wrap"><table><thead><tr><th>Módulo</th><th>Cadastradas</th><th>Habilitadas</th></tr></thead><tbody id="mz-oversight-campaigns"></tbody></table></div></details>
  <details class="mz-admin-promo-details"><summary>Resgates por estado</summary><div class="table-wrap"><table><thead><tr><th>Módulo</th><th>Status</th><th>Registros</th><th>Valor nominal</th></tr></thead><tbody id="mz-oversight-redemptions"></tbody></table></div></details>
  <details class="mz-admin-promo-details"><summary>Últimos resgates</summary><div class="table-wrap"><table><thead><tr><th>Jogador</th><th>Campanha</th><th>Status</th><th>Valor</th><th>Registrado em</th></tr></thead><tbody id="mz-oversight-recent"></tbody></table></div></details>
</article>
<article class="panel mz-admin-promo-audit" id="mz-promo-financial-audit">
  <div class="panel-title"><div><h2>Diagnóstico financeiro de promoções</h2><span>Somente leitura • amostras de inconsistências do ledger e dos resgates</span></div><button type="button" class="secondary" id="mz-promo-audit-refresh">EXECUTAR DIAGNÓSTICO</button></div>
  <p id="mz-promo-audit-message" class="hint" role="status">Este diagnóstico não valida gateways externos nem movimenta dinheiro.</p>
  <div id="mz-promo-audit-results" class="mz-admin-promo-audit-results"></div>
</article><article id="admin-promotion-detail" class="panel hidden" aria-live="polite"><div class="promotion-manager-head"><div><h2 id="admin-promotion-detail-title"></h2><p id="admin-promotion-detail-description" class="hint"></p></div><button type="button" id="promotion-config-new" class="gold-button">+ Adicionar novo</button></div><div id="promotion-config-alert" class="alert hidden" role="alert"></div><div class="table-wrap"><table><thead id="promotion-config-head"></thead><tbody id="promotion-config-body"></tbody></table></div><p class="hint promotion-config-note">Configuração administrativa: o cadastro não concede recompensas automaticamente. Cupons, check-in, VIP e baús de indicação possuem resgates próprios no aplicativo.</p></article><article id="admin-promotion-campaigns" class="panel"><div class="panel-title"><h2>Promoções</h2><span>Somente campanhas e imagens promocionais. Os banners do carrossel e lobby permanecem em Aparência.</span><button type="button" class="gold-button" id="new-promotion">+ Nova promoção</button></div><div id="promotions-alert" class="alert hidden"></div><div id="promotions-list" class="platform-list"></div></article></section>
<section id="page-affiliates" class="admin-page hidden"><article class="panel"><div class="panel-title"><h2>Links de indicação</h2><span>Rastreamento de visitas; atribuição de cadastros e comissões ainda não habilitadas.</span><button type="button" class="gold-button" id="new-affiliate">+ Novo link</button></div><div id="affiliates-alert" class="alert hidden"></div><div id="affiliates-list" class="platform-list"></div></article></section>
<section id="page-audit" class="admin-page hidden"><article class="panel"><div class="panel-title"><h2>Auditoria</h2><span>Eventos administrativos recentes, sem credenciais ou payloads sensíveis.</span></div><div class="toolbar"><input id="audit-search" placeholder="Filtrar ação"><button id="audit-refresh" class="secondary">Buscar</button><button id="audit-prev" class="secondary">Anterior</button><button id="audit-next" class="secondary">Próxima</button><span id="audit-page">Página 1</span></div><div class="table-wrap"><table><thead><tr><th>Data</th><th>Ator</th><th>Ação</th><th>Entidade</th></tr></thead><tbody id="audit-body"></tbody></table></div></article></section>

    <section id="page-gateways" class="admin-page hidden">
        <div id="save-alert" class="alert hidden"></div>
        <div class="toolbar"><button id="refresh-gateways" class="secondary">Atualizar</button></div>
        <div id="gateway-list" class="gateway-list"></div>
    </section>
</section>
</main>
</div>
<div id="deposit-modal" class="modal-backdrop hidden"><section class="deposit-modal-card">
    <header class="modal-head"><div><span>FINANCEIRO / DEPÓSITO</span><h2 id="dep-title">Depósito</h2><p id="dep-subtitle"></p></div><button id="close-deposit-modal" class="icon-button">×</button></header>
    <div id="deposit-alert" class="alert hidden"></div>
    <div class="deposit-summary-grid"><article class="mini-card"><span>VALOR</span><strong id="dep-amount">—</strong></article><article class="mini-card"><span>STATUS</span><strong id="dep-status">—</strong></article><article class="mini-card"><span>USUÁRIO</span><strong id="dep-user">—</strong></article><article class="mini-card"><span>GATEWAY</span><strong id="dep-gateway">—</strong></article></div>
    <div class="detail-grid"><article class="panel"><div class="panel-title"><h2>Dados da transação</h2><span>Identificadores e datas do pagamento.</span></div><div id="dep-fields" class="detail-fields"></div></article><article class="panel"><div class="panel-title"><h2>PIX copia e cola</h2><span>Código retornado pelo gateway.</span></div><textarea id="dep-pix" readonly rows="7"></textarea><button id="copy-dep-pix" class="gold-button form-action">COPIAR PIX</button></article></div>
    <article class="panel"><div class="panel-title"><h2>Eventos / Webhooks</h2><span>Eventos recebidos para esta transação.</span></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Evento</th><th>External ID</th><th>Recebido</th><th>Processado</th></tr></thead><tbody id="dep-webhooks"></tbody></table></div></article>
</section></div>
<div id="user-modal" class="modal-backdrop hidden">
    <section class="user-modal-card user-profile-modal">
        <header class="user-profile-hero">
            <div class="user-avatar" id="ud-avatar">U</div>
            <div class="user-identity"><span>USUÁRIO</span><h2 id="user-detail-title">Usuário</h2><p id="user-detail-email"></p><p id="user-detail-contact"></p><strong id="ud-public-id">#—</strong></div>
            <div class="hero-stat"><span>Dep. total</span><strong id="ud-deposits">R$ 0,00</strong></div>
            <div class="hero-stat"><span>Saques total</span><strong id="ud-withdrawals">R$ 0,00</strong></div>
            <div class="hero-stat"><span>Saldo atual</span><strong id="ud-cash">R$ 0,00</strong></div>
            <div class="hero-stat"><span>Bônus</span><strong id="ud-bonus">R$ 0,00</strong></div>
            <div class="hero-actions"><button id="toggle-user-block" class="danger-soft">BLOQUEAR</button><button id="open-balance-adjust" class="gold-button">AJUSTAR SALDO</button></div>
            <button id="close-user-modal" class="icon-button hero-close" aria-label="Fechar">×</button>
        </header>
        <div id="user-detail-alert" class="alert hidden"></div>
        <nav class="user-tabs">
            <button class="user-tab active" data-user-tab="info">Informações do usuário</button>
            <button class="user-tab" data-user-tab="accounts">Contas de recebimento</button>
            <button class="user-tab" data-user-tab="deposits">Registros de depósitos</button>
            <button class="user-tab" data-user-tab="withdrawals">Registros de retiradas</button>
            <button class="user-tab" data-user-tab="edit">Editar usuário</button>
        </nav>
        <section class="user-tab-panel" data-user-panel="info">
            <div class="user-summary-grid">
                <article class="mini-card"><span>SALDO ATUAL</span><strong id="ud-cash-card">R$ 0,00</strong></article>
                <article class="mini-card"><span>TOTAL DEPOSITADO</span><strong id="ud-deposits-card">R$ 0,00</strong></article>
                <article class="mini-card"><span>TOTAL SACADO</span><strong id="ud-withdrawals-card">R$ 0,00</strong></article>
                <article class="mini-card"><span>SESSÕES ATIVAS</span><strong id="ud-sessions">0</strong></article>
            </div>
            <div class="detail-grid">
                <article class="panel"><div class="panel-title"><h2>Ledger</h2><span>Movimentações imutáveis da carteira.</span></div><div class="table-wrap"><table><thead><tr><th>Conta</th><th>Movimento</th><th>Valor</th><th>Saldo final</th><th>Data</th></tr></thead><tbody id="ud-ledger"></tbody></table></div></article>
                <article class="panel"><div class="panel-title"><h2>Sessões</h2><span>Últimas sessões do usuário.</span></div><div class="table-wrap"><table><thead><tr><th>Sessão</th><th>Status</th><th>Criada</th><th>Último uso</th><th>Expira</th></tr></thead><tbody id="ud-session-list"></tbody></table></div></article>
            </div>
        </section>
        <section class="user-tab-panel hidden" data-user-panel="accounts">
            <article class="panel"><div class="panel-title"><h2>Contas de recebimento</h2><span>Chaves cadastradas para futuros saques.</span></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Nome</th><th>Tipo</th><th>Chave</th><th>Cadastro</th></tr></thead><tbody id="ud-payout-accounts"></tbody></table></div></article>
        </section>
        <section class="user-tab-panel hidden" data-user-panel="deposits">
            <article class="panel"><div class="panel-title"><h2>Registros de depósitos</h2><span>Histórico de depósitos do usuário.</span></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Gateway</th><th>Valor</th><th>Data/Hora</th><th>Status</th></tr></thead><tbody id="ud-deposit-list"></tbody></table></div></article>
        </section>
        <section class="user-tab-panel hidden" data-user-panel="withdrawals">
            <article class="panel"><div class="panel-title"><h2>Registros de retiradas</h2><span>Histórico de saques do usuário.</span></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Gateway</th><th>Valor</th><th>Data/Hora</th><th>Status</th></tr></thead><tbody id="ud-withdrawal-list"></tbody></table></div></article>
        </section>
        <section class="user-tab-panel hidden" data-user-panel="edit">
            <div class="edit-user-grid">
                <article class="panel"><div class="panel-title"><h2>Dados do usuário</h2><span>Edite somente informações administrativas permitidas.</span></div>
                    <div class="grid two"><label>Nome de usuário<input id="ud-edit-username"></label><label>E-mail<input id="ud-edit-email" type="email"></label></div>
                    <div class="grid two"><label>CPF<input id="ud-edit-cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00"></label><label>Celular<input id="ud-edit-phone" type="tel" maxlength="15" placeholder="(11) 99999-9999"></label></div>
                    <label>Status<select id="ud-status"><option value="ACTIVE">Ativo</option><option value="SUSPENDED">Suspenso</option><option value="BLOCKED">Bloqueado</option></select></label>
                    <button id="save-user-profile" class="gold-button form-action">ATUALIZAR USUÁRIO</button>
                </article>
                <article class="panel"><div class="panel-title"><h2>Redefinir senha</h2><span>A senha atual nunca é exibida. Ao redefinir, as sessões são encerradas.</span></div>
                    <label>Nova senha<input id="ud-new-password" type="password" autocomplete="new-password" minlength="8"></label>
                    <button id="reset-user-password" class="secondary-action form-action">REDEFINIR SENHA</button>
                </article>
            </div>
        </section>
        <div id="balance-adjust-modal" class="nested-dialog hidden">
            <div class="nested-card"><div class="panel-title inline"><div><h2>Ajustar saldo</h2><span>O ajuste será registrado no ledger e na auditoria.</span></div><button id="close-balance-adjust" class="icon-button">×</button></div>
                <div class="grid two"><label>Conta<select id="adjust-account"><option value="CASH">CASH</option><option value="BONUS">BÔNUS</option></select></label><label>Movimento<select id="adjust-direction"><option value="CREDIT">Crédito</option><option value="DEBIT">Débito</option></select></label></div>
                <label>Valor (R$)<input id="adjust-amount" type="number" min="0.01" step="0.01"></label>
                <label>Motivo<input id="adjust-reason" maxlength="190" placeholder="Ex.: correção aprovada pelo suporte"></label>
                <button id="save-balance-adjust" class="gold-button form-action">CONFIRMAR AJUSTE</button>
            </div>
        </div>
    </section>
</div>
<template id="gateway-template">
<article class="gateway-card">
<div class="gateway-title"><div><span class="status-pill"></span><h2></h2><small class="gateway-code"></small></div><label class="switch-row">Ativo <input type="checkbox" name="enabled"></label></div>
<div class="grid two"><label>Nome<input name="name"></label><label>Ambiente<select name="mode"><option value="PRODUCTION">Produção</option><option value="SANDBOX">Sandbox</option></select></label></div>
<div class="operation-box"><h3>Depósitos</h3><div class="grid four"><label>Habilitado<input type="checkbox" name="deposit_enabled"></label><label>Prioridade<input type="number" name="priority_deposit" min="1"></label><label>Mínimo (R$)<input type="number" name="min_deposit" step="0.01" min="0"></label><label>Máximo (R$)<input type="number" name="max_deposit" step="0.01" min="0"></label></div></div>
<div class="operation-box"><h3>Saques</h3><div class="grid four"><label>Habilitado<input type="checkbox" name="withdrawal_enabled"></label><label>Prioridade<input type="number" name="priority_withdrawal" min="1"></label><label>Mínimo (R$)<input type="number" name="min_withdrawal" step="0.01" min="0"></label><label>Máximo (R$)<input type="number" name="max_withdrawal" step="0.01" min="0"></label></div></div>
<div class="pixup-fields hidden"><h3>Credenciais Pixup</h3><div class="grid two"><label>Client ID<input name="client_id" autocomplete="off" placeholder="deixe vazio para manter"></label><label>Client Secret<input name="client_secret" type="password" autocomplete="new-password" placeholder="deixe vazio para manter"></label></div><div class="grid two"><label>Webhook Secret<input name="webhook_secret" type="password" autocomplete="new-password" placeholder="opcional"></label><label>Base URL<input name="base_url" value="https://api.pixupbr.com"></label></div><label class="checkline"><input type="checkbox" name="verify_webhook_signature"> Validar assinatura do webhook quando configurada</label><label>Webhook URL (opcional)<input name="webhook_url" placeholder="https://seusite.com/api/webhooks/payments/pixup"></label><p class="hint">Os segredos são criptografados antes de ir ao banco e nunca voltam para o navegador.</p></div>
<footer><span class="credential-state"></span><button class="save-gateway">SALVAR GATEWAY</button></footer>
</article>
</template>
<div class="admin-editor-overlay hidden" id="editor-announcements-form" role="dialog" aria-modal="true" aria-label="Editar novidade"><div class="admin-editor-card"><header class="admin-editor-head"><h2>Nova novidade</h2><button type="button" class="admin-editor-close" aria-label="Fechar">×</button></header><div class="admin-editor-error alert hidden" role="alert"></div><form id="announcements-form" class="platform-form admin-modal-form"><input type="hidden" name="id"><label>Texto da novidade<textarea name="message" maxlength="240" rows="3" required placeholder="Novidade ou lançamento da plataforma"></textarea><small>Até 240 caracteres. Não inclua promessas não verificadas.</small></label><label>Ordem<input name="sort_order" type="number" min="0" max="100000" value="100" required></label><label class="checkline"><input name="enabled" type="checkbox" checked> Exibir na home</label><button class="gold-button" type="submit">Salvar novidade</button><button type="button" class="secondary modal-cancel">Cancelar</button></form></div></div>
<div class="admin-editor-overlay hidden" id="editor-casino-category-form" role="dialog" aria-modal="true" aria-label="Editar categoria"><div class="admin-editor-card"><header class="admin-editor-head"><h2 id="editor-title-casino-category-form">Nova categoria</h2><button type="button" class="admin-editor-close" aria-label="Fechar">×</button></header><div class="admin-editor-error alert hidden" role="alert"></div><form id="casino-category-form" class="casino-form admin-modal-form"><input type="hidden" name="id"><label>Código<input name="code" required pattern="[A-Z0-9_]{2,60}" maxlength="60" placeholder="SLOTS"></label><label>Nome<input name="name" required maxlength="120" placeholder="Slots"></label><label>Ícone<select name="icon_key"><option value="slots">Slots</option><option value="fish">Pescaria</option><option value="sport">SportBet</option><option value="roulette">Roleta</option><option value="live">Ao vivo</option><option value="table">Mesa</option><option value="other">Outro</option></select></label><label>Ordem<input name="sort_order" type="number" min="0" max="100000" value="100" required></label><label class="casino-check"><input name="enabled" type="checkbox" checked> Exibir na home</label><button type="submit" class="gold-button casino-save">SALVAR CATEGORIA</button><button type="button" class="secondary modal-cancel">Cancelar</button></form></div></div>
<div class="admin-editor-overlay hidden" id="editor-casino-provider-form" role="dialog" aria-modal="true" aria-label="Editar provedor"><div class="admin-editor-card"><header class="admin-editor-head"><h2 id="editor-title-casino-provider-form">Novo provedor</h2><button type="button" class="admin-editor-close" aria-label="Fechar">×</button></header><div class="admin-editor-error alert hidden" role="alert"></div><form id="casino-provider-form" class="casino-form admin-modal-form"><input type="hidden" name="id"><label>Código<input name="code" required pattern="[a-z0-9_-]{2,60}" placeholder="provedor_demo"></label><label>Nome<input name="name" required maxlength="120"></label><label class="provider-upload-label">Logo do provedor (150 × 60 px)<input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG ou WebP; até 2 MB. A imagem é exibida em 150 × 60 px na home.</small><img id="provider-logo-preview" class="provider-logo-preview hidden" alt="Prévia da logo"></label><input type="hidden" name="logo_path"><label>Modo<select name="mode"><option value="DEMO">Demonstração</option><option value="PRODUCTION">Produção (sem lançamento)</option></select></label><label>API de origem<select name="api_source"><option value="MANUAL">Catálogo local / Manual</option><option value="PLAYFIVER">PlayFiver (cadastro manual)</option></select></label><label class="casino-check"><input name="enabled" type="checkbox"> Ativo</label><button type="submit" class="gold-button casino-save">SALVAR PROVEDOR</button><button type="button" class="secondary modal-cancel">Cancelar</button></form></div></div>
<div class="admin-editor-overlay hidden" id="editor-casino-game-form" role="dialog" aria-modal="true" aria-label="Editar jogo"><div class="admin-editor-card"><header class="admin-editor-head"><h2 id="editor-title-casino-game-form">Novo jogo</h2><button type="button" class="admin-editor-close" aria-label="Fechar">×</button></header><div class="admin-editor-error alert hidden" role="alert"></div><form id="casino-game-form" class="casino-form admin-modal-form"><input type="hidden" name="id"><label>Provedor<select name="provider_id" id="casino-provider-select" required></select></label><label>ID externo<input name="external_id" required maxlength="190"></label><label>Nome<input name="name" required maxlength="190"></label><label>Categoria<select name="category"><option value="SLOTS">Slots</option><option value="LIVE">Ao vivo</option><option value="TABLE">Mesa</option><option value="OTHER">Outros</option></select></label><label>Imagem HTTPS<input name="image_url" type="url" maxlength="500" placeholder="https://... (capa do jogo)"></label><label>Ordem de exibição<input name="sort_order" type="number" min="0" max="100000" value="100" required></label><label>Acessos<input name="access_count" type="number" min="0" max="999999999" value="250" required></label><label class="casino-check"><input name="enabled" type="checkbox"> Ativo</label><label class="casino-check"><input name="featured" type="checkbox"> Destaque</label><button type="submit" class="gold-button casino-save">SALVAR JOGO</button><button type="button" class="secondary modal-cancel">Cancelar</button></form></div></div>
<div class="admin-editor-overlay hidden" id="editor-banners-form" role="dialog" aria-modal="true" aria-label="Editar banner"><div class="admin-editor-card"><header class="admin-editor-head"><h2 id="editor-title-banners-form">Novo banner</h2><button type="button" class="admin-editor-close" aria-label="Fechar">×</button></header><div class="admin-editor-error alert hidden" role="alert"></div><form id="banners-form" class="platform-form appearance-edit-form admin-modal-form"><input type="hidden" name="id"><label>Título<input name="title" maxlength="140" required></label><label>Subtítulo<input name="subtitle" maxlength="255"></label><label>Imagem<input type="file" name="image" accept="image/png,image/jpeg,image/webp"><small id="banner-modal-size-guide">Carrossel: recomendado 1920 × 600 px; até 2 MB.</small></label><input type="hidden" name="image_path"><label>Destino interno<input name="target_path" value="/cassino" required></label><input type="hidden" name="position" value="home"><label>Ordem<input name="sort_order" type="number" min="1" value="1"></label><label class="checkline"><input name="enabled" type="checkbox"> Publicar banner</label><button class="gold-button">Salvar banner</button><button type="button" class="secondary modal-cancel">Cancelar</button></form></div></div>
<div class="admin-editor-overlay hidden" id="editor-promotions-form" role="dialog" aria-modal="true" aria-label="Editar promoção"><div class="admin-editor-card"><header class="admin-editor-head"><h2 id="editor-title-promotions-form">Novo promoção</h2><button type="button" class="admin-editor-close" aria-label="Fechar">×</button></header><div class="admin-editor-error alert hidden" role="alert"></div><form id="promotions-form" class="platform-form admin-modal-form"><input type="hidden" name="id"><label>Título<input name="title" maxlength="140" required></label><label>Descrição<textarea name="description" rows="4" required></textarea></label><label>Imagem<input type="file" name="image" accept="image/png,image/jpeg,image/webp"><small id="banner-modal-size-guide">Carrossel: recomendado 1920 × 600 px; até 2 MB.</small></label><input type="hidden" name="image_path"><label>Início<input name="starts_at" type="datetime-local"></label><label>Fim<input name="ends_at" type="datetime-local"></label><label class="checkline"><input name="enabled" type="checkbox"> Publicar campanha</label><button class="gold-button">Salvar promoção</button><button type="button" class="secondary modal-cancel">Cancelar</button></form></div></div>
<div class="admin-editor-overlay hidden" id="editor-affiliates-form" role="dialog" aria-modal="true" aria-label="Editar link de indicação"><div class="admin-editor-card"><header class="admin-editor-head"><h2 id="editor-title-affiliates-form">Novo link de indicação</h2><button type="button" class="admin-editor-close" aria-label="Fechar">×</button></header><div class="admin-editor-error alert hidden" role="alert"></div><form id="affiliates-form" class="platform-form admin-modal-form"><input type="hidden" name="id"><label>Código<input name="code" pattern="[A-Za-z0-9_-]{4,32}" maxlength="32" required></label><label>Identificação<input name="label" maxlength="120" required></label><label class="checkline"><input name="enabled" type="checkbox" checked> Link ativo</label><button class="gold-button">Salvar link</button><button type="button" class="secondary modal-cancel">Cancelar</button></form></div></div>
<div class="admin-editor-overlay hidden" id="promotion-config-overlay" role="dialog" aria-modal="true" aria-labelledby="promotion-config-modal-title"><div class="admin-editor-card"><header class="admin-editor-head"><h2 id="promotion-config-modal-title">Adicionar configuração</h2><button class="admin-editor-close" id="promotion-config-close" type="button" aria-label="Fechar">×</button></header><div id="promotion-config-modal-error" class="alert hidden" role="alert"></div><form id="promotion-config-form" class="casino-form admin-modal-form"><input name="id" type="hidden"><label>Nome<input name="title" maxlength="120" required></label><div id="promotion-config-fields" class="promotion-config-fields"></div><label class="checkline"><input name="enabled" type="checkbox"> Habilitar configuração (resgates de cupons, check-in, VIP e baús são validados pelo servidor)</label><div class="promotion-config-actions"><button class="gold-button" type="submit">Salvar alterações</button><button type="button" id="promotion-config-cancel" class="secondary">Cancelar</button></div></form></div></div>
<script src="/admin-assets/admin.js?v=20260922-v23-completa"></script>





</body></html>
