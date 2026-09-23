<?php

declare(strict_types=1);

use App\Core\Support\Env;

$basePath = rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
$fallbackAppName = (string) Env::get('APP_NAME', 'MZ90');
$siteName = (string) (($settings['site_name'] ?? '') !== '' ? $settings['site_name'] : $fallbackAppName);
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR" data-theme="mz90-ember">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#111416">
  <link rel="icon" id="browser-favicon" href="data:,">
  <title><?= h($siteName) ?></title>
  <meta name="description" content="Plataforma iGaming modular em PHP puro.">
  <link rel="stylesheet" href="<?= h($basePath) ?>/assets/app.css?v=20260923-v24">
</head>
<body>
<div class="mobile-stage">
  <div id="mz-drawer-backdrop" class="mz-drawer-backdrop hidden" aria-hidden="true"></div>
  <aside id="mz-home-drawer" class="mz-home-drawer" role="dialog" aria-modal="true" aria-label="Menu lateral" aria-hidden="true" inert tabindex="-1">
    <div class="mz-drawer-header"><div class="mz-drawer-brand"><img id="drawer-site-logo" class="drawer-site-logo hidden" alt="<?= h($siteName) ?>"><strong id="drawer-brand-name"><?= h($siteName) ?></strong></div><button type="button" id="mz-drawer-close" aria-label="Fechar menu">×</button></div>
    <div class="mz-drawer-scroll">
      <h2 class="mz-drawer-heading">Promoções</h2>
      <div class="mz-drawer-grid">
        <button type="button" class="mz-drawer-tile" data-drawer-promo="chests"><span class="mz-drawer-icon" aria-hidden="true">▣</span><span>Baú do Tesouro</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="rebate"><span class="mz-drawer-icon" aria-hidden="true">↺</span><span>Rebate</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="agency"><span class="mz-drawer-icon" aria-hidden="true">♧</span><span>Agente</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="coupons"><span class="mz-drawer-icon" aria-hidden="true">▤</span><span>Troca</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="checkin"><span class="mz-drawer-icon" aria-hidden="true">♛</span><span>Nível / Check-in</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="rescue"><span class="mz-drawer-icon" aria-hidden="true">✧</span><span>Resgatar</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="vip"><span class="mz-drawer-icon" aria-hidden="true">♕</span><span>VIP</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="cashwheel"><span class="mz-drawer-icon" aria-hidden="true">◉</span><span>Roleta de Saque</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="roulette"><span class="mz-drawer-icon" aria-hidden="true">◎</span><span>Giro da Sorte</span></button>
        <button type="button" class="mz-drawer-tile" data-drawer-promo="lottery"><span class="mz-drawer-icon" aria-hidden="true">✦</span><span>Sorteio</span></button>
      </div>
      <h2 class="mz-drawer-heading mz-drawer-separator">Sua conta</h2>
      <div class="mz-drawer-links">
        <button type="button" data-drawer-section="profile">☻ &nbsp; Meu perfil</button>
        <button type="button" data-drawer-promo="agency">♧ &nbsp; Convidar amigos</button>
        <button type="button" data-drawer-deposit="1">▣ &nbsp; Depósito</button>
        <button type="button" data-drawer-section="profile">↗ &nbsp; Saque / carteira</button>
      </div>
    </div>
  </aside>
  <header class="app-header">
    <button type="button" id="mz-drawer-toggle" class="mz-drawer-toggle" aria-label="Abrir menu lateral" aria-controls="mz-home-drawer" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h12M4 18h16"/></svg></button>
    <button class="brand-lockup" data-section="home" aria-label="Início">
      <img id="header-site-logo" class="header-site-logo hidden" alt="<?= h($siteName) ?>" width="166" height="58">
      <span class="brand-round">M</span>
      <span class="brand-text"><b><?= h($siteName) ?></b><small>CASINO</small></span>
    </button>
    <div id="guest-actions" class="header-actions">
      <button class="login-link" data-open-auth="login">Entrar</button>
      <button class="balance-pill" data-open-auth="register">Criar conta</button>
    </div>
    <div id="user-actions" class="header-actions hidden">
      <button class="reward-bell" id="reward-notification-toggle" type="button" aria-label="Recompensas disponíveis" aria-expanded="false"><span aria-hidden="true">🔔</span><b id="reward-notification-badge" class="reward-count hidden">0</b></button>
      <button class="balance-pill" data-section="profile"><span id="header-balance">R$ 0,00</span></button>
      <button class="profile-mini" id="user-avatar" type="button" aria-label="Abrir meu perfil" title="Meu perfil"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg></button>
    </div>
  </header>
  <aside id="reward-notification-panel" class="reward-notification-panel hidden" aria-label="Notificações de recompensas">
    <div class="reward-notification-head"><div><strong>Recompensas</strong><small>Benefícios disponíveis na sua conta</small></div><button type="button" id="reward-notification-close" aria-label="Fechar">×</button></div>
    <div id="reward-notification-list" class="reward-notification-list"><p>Nenhuma recompensa disponível.</p></div>
    <button type="button" class="reward-notification-all" id="reward-notification-all">Ver Central de Recompensas</button>
  </aside>

  <main class="content" id="content">
    <section class="page-section active" id="section-home">
      <section id="managed-banners" class="home-slider" aria-label="Carrossel principal" aria-roledescription="carrossel">
        <div class="slider-empty">Os destaques da plataforma aparecerão aqui.</div>
      </section>
      <section id="home-lobby" class="home-lobby" aria-label="Banners do lobby"></section>

      <section class="notice-row" aria-label="Novidades e lançamentos">
        <div class="notice-pill"><span class="notice-dot"></span><b>Novidades</b></div>
        <div class="news-window" id="news-window"><div class="news-track" id="news-track"><span>Novidades e lançamentos MZ90</span></div></div>
        <button class="search-square" id="home-search" aria-label="Buscar jogos" type="button"><span></span></button>
      </section>

      <section class="providers-section" aria-label="Provedores de jogos">
        <div class="section-title"><div class="provider-arrows"><button type="button" id="providers-prev" aria-label="Provedores anteriores">‹</button><button type="button" id="providers-next" aria-label="Próximos provedores">›</button></div></div>
        <div class="provider-scroll" id="provider-scroll"><p class="empty-state">Carregando provedores...</p></div>
      </section>

      <section class="category-strip" id="category-row"></section>

      <section class="section-block" id="featured-section" hidden>
        <div class="section-title"><h2>Jogos em destaque</h2><button data-section="casino">Ver todos</button></div>
        <div class="game-grid" id="featured-games"></div>
        <div id="home-provider-groups" class="home-provider-groups"></div>
      </section>

      <section class="vip-banner gold-frame">
        <div><span>CLUBE MZ90</span><h2>Recompensas VIP</h2><p>Benefícios exclusivos em uma experiência premium.</p><button class="vip-btn" data-section="promotions">CONHECER O CLUBE</button></div>
        <div class="vip-seal">90</div>
      </section>
    </section>

    <section class="page-section" id="section-casino">
      <div id="casino-managed-banners" class="managed-banners"></div>
      <div class="inner-head"><span>CATÁLOGO</span><h1>Cassino</h1><p>Escolha uma categoria e encontre seu próximo jogo.</p></div>
      <div class="casino-toolbar"><label class="casino-search-label" for="public-game-search">Buscar jogos<input id="public-game-search" type="search" placeholder="Nome do jogo..." autocomplete="off"></label><button type="button" id="clear-casino-filters" class="dark-btn">Limpar filtros</button></div>
      <section class="category-strip sticky-strip" id="casino-category-row"></section>
      <div class="casino-filter-summary" id="casino-filter-summary" aria-live="polite"></div>
      <div class="game-grid catalog" id="casino-games"></div>
    </section>

    <section class="page-section" id="section-game" aria-label="Página do jogo">
      <button type="button" class="dark-btn game-back" id="game-back">← Voltar ao cassino</button>
      <article class="game-detail-panel">
        <div class="game-detail-art" id="game-detail-art"></div>
        <div class="game-detail-content"><span class="game-detail-eyebrow">JOGO DO CATÁLOGO</span><h1 id="game-detail-name">Jogo</h1><p id="game-detail-provider"></p>
          <div class="game-availability" role="status">Este jogo do catálogo local ainda não possui integração de lançamento.</div>
          <p class="game-online-placeholder"><span aria-hidden="true" class="online-dot unavailable" id="detail-online-dot"></span> Acessos: <strong id="detail-online-count">—</strong></p>
        </div>
      </article>
    </section>

    <section class="page-section" id="section-live">
      <div class="inner-head"><span>LIVE CASINO</span><h1>Ao vivo</h1><p>Jogos publicados no catálogo. A execução estará disponível após a integração do provedor.</p></div>
      <div class="game-grid catalog" id="live-games"></div>
    </section>

    <section class="page-section" id="section-promotions">
      <div class="inner-head"><span>PROMOÇÕES</span><h1>Benefícios</h1><p>Área pronta para campanhas, VIP, cashback e bônus.</p></div>
      <div id="mz-rewards-center" class="mz-rewards-center" aria-label="Central de recompensas"></div>
      <div id="mz-promo-directory" class="mz-promo-directory" aria-label="Módulos de promoções"></div>
      <div id="mz-promo-detail" class="mz-promo-detail hidden" aria-live="polite"></div>
    </section>

    <section class="page-section" id="section-profile" aria-label="Meu perfil">
      <div class="inner-head"><span>MINHA CONTA</span><h1>Meu perfil</h1><p>Dados da conta, saldo e movimentações em um só lugar.</p></div>
      <article class="profile-panel">
        <div class="profile-panel-head"><span class="profile-panel-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg></span><div><h2 id="profile-display-name">Minha conta</h2><small id="profile-status">Conta ativa</small></div></div>
        <dl class="profile-details"><div><dt>ID público</dt><dd id="profile-public-id">—</dd></div><div><dt>Telefone</dt><dd id="profile-phone">—</dd></div><div><dt>E-mail</dt><dd id="profile-email">Não cadastrado</dd></div><div><dt>CPF</dt><dd id="profile-cpf">—</dd></div></dl>
      </article>
      <section class="profile-wallet-summary gold-frame">
        <small>Saldo disponível</small><strong id="wallet-total">R$ 0,00</strong>
        <div class="wallet-actions"><button class="gold-btn" id="deposit-placeholder" type="button">DEPOSITAR</button><button class="dark-btn" id="withdraw-placeholder" type="button">SACAR</button></div>
      </section>
      <section class="wallet-card profile-wallet-card"><h3>Carteira</h3><div id="wallet-overview" class="wallet-overview"></div><div id="wallet-accounts" class="account-list"><div class="empty-state">Entre para consultar a carteira.</div></div></section>
      <section class="wallet-card profile-wallet-card"><div class="wallet-card-head"><h3>Movimentações</h3><button id="refresh-wallet" type="button">Atualizar</button></div><div class="wallet-filter-row"><label>Filtrar<select id="transaction-filter"><option value="ALL">Todas</option><option value="DEPOSIT">Depósitos</option><option value="WITHDRAWAL">Saques</option><option value="PROMOTION">Promoções / bônus</option><option value="CASINO">Cassino</option><option value="AFFILIATE">Afiliado</option></select></label></div><div id="transaction-list" class="transaction-list"><div class="empty-state">Nenhuma movimentação.</div></div></section>
      <div class="profile-actions"><button type="button" class="dark-btn" id="profile-logout">Sair da conta</button></div>
    </section>

    <section class="page-section" id="section-invite" aria-label="Convide amigos">
      <div class="inner-head"><span>COMPARTILHE</span><h1>Convidar</h1><p>Um atalho para divulgar a plataforma e convidar novos jogadores.</p></div>
      <article class="profile-panel invite-panel">
        <div class="profile-panel-head"><span class="profile-panel-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><div><h2>Convide seus amigos</h2><small>Visual profissional com ícone dedicado no menu inferior</small></div></div>
        <p class="empty-state">Compartilhe sua plataforma com seu público. Esta área pode receber regras e benefícios do programa de indicação quando desejar.</p>
        <div class="profile-actions"><button type="button" class="gold-btn" data-section="home">Voltar para a home</button><button type="button" class="dark-btn" data-section="promotions">Ver promoções</button></div>
      </article>
    </section>
  </main>

  <footer id="platform-footer" class="platform-footer" aria-label="Rodapé da plataforma">
    <div class="footer-content">
      <div class="footer-brand"><img id="footer-logo" class="footer-logo hidden" alt="Logo da plataforma"><strong id="footer-brand-name"><?= h($siteName) ?></strong></div>
      <p id="footer-about" class="footer-about"></p>
      <div class="footer-contact-grid">
        <section class="footer-contact-section"><strong>Fale conosco</strong><div id="footer-contact" class="footer-links" aria-label="Canais de atendimento"></div><div id="footer-socials" class="footer-socials" aria-label="Redes sociais oficiais"></div></section>
        <section class="footer-info-section"><strong>Informações</strong><p><span class="footer-age" aria-label="Maiores de 18 anos">18+</span> Jogue com responsabilidade.</p></section>
      </div>
      <div class="footer-bottom"><div id="footer-copy" aria-label="Direitos autorais"><span>© <?= date('Y') ?> <?= h($siteName) ?>.</span><span>Todos os direitos reservados.</span></div></div>
    </div>
  </footer>

  <nav class="bottom-nav" aria-label="Navegação principal">
    <button class="bottom-item active" data-section="home"><i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg></i><span id="home-nav-label">Início</span></button>
    <button class="bottom-item" data-section="promotions"><b id="promotions-nav-badge" class="bottom-reward-badge hidden">0</b><i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h10l-1 6h3l-6 10 1-7H6l1-9Z"/></svg></i><span>Promoção</span></button>
    <button class="bottom-item center" id="deposit-nav" type="button"><i aria-hidden="true" class="bottom-center-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a3 3 0 0 1 3-3h11l4 4v9a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3Z"/><path d="M3 9h18"/><path d="M15 14h3"/></svg></i><span>Depósito</span></button>
    <button class="bottom-item" id="invite-agency-nav" type="button"><i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M17 8h4"/><path d="M19 6v4"/></svg></i><span>Convidar</span></button>
    <button class="bottom-item" id="profile-nav"><i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg></i><span>Perfil</span></button>
  </nav>
</div>

<div class="modal-backdrop hidden" id="auth-modal" role="dialog" aria-modal="true">
  <div class="auth-modal">
    <button class="modal-close" id="close-auth">×</button>
    <div class="auth-brand"><span class="brand-round">M</span><div><strong><?= h($siteName) ?></strong><small>CASINO</small></div></div>
    <div class="auth-tabs"><button data-auth-tab="login" class="active">Entrar</button><button data-auth-tab="register">Criar conta</button></div>
    <div class="alert hidden" id="auth-alert"></div>
    <form id="login-form" class="auth-form"><label>CPF, telefone ou e-mail<input type="text" name="identifier" autocomplete="username" required placeholder="Digite seu CPF, celular ou e-mail"></label><label>Senha<input type="password" name="password" autocomplete="current-password" required></label><button class="gold-btn full" type="submit">ENTRAR</button></form>
    <form id="register-form" class="auth-form hidden"><label>CPF<input type="text" name="cpf" inputmode="numeric" maxlength="14" required placeholder="000.000.000-00" autocomplete="off"></label><label>Celular<input type="tel" name="phone" inputmode="tel" maxlength="15" required placeholder="(11) 99999-9999" autocomplete="tel"></label><label>Senha<input type="password" name="password" minlength="12" autocomplete="new-password" required></label><button class="gold-btn full" type="submit">CRIAR CONTA</button></form>
  </div>
</div>
<div class="modal-backdrop hidden" id="payment-modal" role="dialog" aria-modal="true">
  <div class="payment-modal">
    <button class="modal-close" id="close-payment" type="button">×</button>
    <div class="payment-head"><span>CARTEIRA</span><h2>Depositar via PIX</h2><p>Escolha o valor. Gere um PIX para adicionar saldo à sua carteira.</p></div>
    <div class="alert hidden" id="payment-alert"></div>
    <form id="deposit-form" class="payment-form">
      <label>Valor do depósito
        <div class="money-input"><span>R$</span><input type="number" name="amount" min="1" step="0.01" value="20.00" required></div>
      </label>
      <label>Gateway<select name="gateway_code" id="gateway-select" required></select></label>
      <button class="gold-btn full" id="generate-pix" type="submit"><span class="btn-label">GERAR PIX</span><span class="btn-spinner" aria-hidden="true"></span></button>
    </form>
    <div id="pix-result" class="pix-result hidden">
      <div class="pix-status"><span class="status-dot"></span><div><small>Status</small><strong id="pix-status">Aguardando pagamento</strong></div></div>
      <div class="qr-wrap"><div id="pix-qr" class="pix-qr" aria-label="QR Code PIX"></div><small id="qr-help">Escaneie o QR Code com o aplicativo do seu banco</small></div>
      <label>Código copia e cola<textarea id="pix-code" readonly></textarea></label>
      <div class="payment-actions"><button class="dark-btn" id="copy-pix" type="button">COPIAR PIX</button><button class="gold-btn hidden" id="sandbox-confirm" type="button">SIMULAR PAGAMENTO</button></div>
      <small class="sandbox-note" id="sandbox-note"></small>
    </div>
  </div>
</div>


<div class="toast-stack" id="toast-stack"></div>
<script>window.IGAMING = <?= json_encode(['basePath'=>$basePath,'appName'=>$siteName], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="<?= h($basePath) ?>/assets/app.js?v=20260923-v24"></script>
<script src="<?= h($basePath) ?>/assets/promotions.js?v=20260923-v24" defer></script>
<script src="<?= h($basePath) ?>/assets/sidebar.js?v=20260923-v24" defer></script>
</body>
</html>
