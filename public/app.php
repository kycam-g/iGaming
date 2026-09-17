<?php

declare(strict_types=1);

use App\Core\Support\Env;

$basePath = rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
$appName = (string) Env::get('APP_NAME', 'MZ90');
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR" data-theme="mz90-ember">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#080908">
  <title><?= h($appName) ?></title>
  <meta name="description" content="Plataforma iGaming modular em PHP puro.">
  <link rel="stylesheet" href="<?= h($basePath) ?>/assets/app.css?v=20260916-slider3">
</head>
<body>
<div class="mobile-stage">
  <header class="app-header">
    <button class="brand-lockup" data-section="home" aria-label="Início">
      <span class="brand-round">M</span>
      <span class="brand-text"><b><?= h($appName) ?></b><small>CASINO</small></span>
    </button>
    <div id="guest-actions" class="header-actions">
      <button class="login-link" data-open-auth="login">Entrar</button>
      <button class="balance-pill" data-open-auth="register">Criar conta</button>
    </div>
    <div id="user-actions" class="header-actions hidden">
      <button class="balance-pill" data-section="wallet"><span id="header-balance">R$ 0,00</span></button>
      <button class="profile-mini" id="user-avatar">U</button>
    </div>
  </header>

  <main class="content" id="content">
    <section class="page-section active" id="section-home">
      <section id="managed-banners" class="home-slider" aria-label="Carrossel principal" aria-roledescription="carrossel">
        <div class="slider-empty">Os destaques da plataforma aparecerão aqui.</div>
      </section>
      <section id="home-lobby" class="home-lobby" aria-label="Banners do lobby"></section>

      <section class="notice-row">
        <div class="notice-pill"><span class="notice-dot"></span><b>Novidades e lançamentos MZ90</b></div>
        <button class="search-square" aria-label="Buscar"><span></span></button>
      </section>

      <section class="category-strip" id="category-row"></section>

      <section class="section-block">
        <div class="section-title"><h2>Jogos em destaque</h2><button data-section="casino">Ver todos</button></div>
        <div class="game-grid" id="featured-games"></div>
      </section>

      <section class="vip-banner gold-frame">
        <div><span>CLUBE MZ90</span><h2>Recompensas VIP</h2><p>Benefícios exclusivos em uma experiência premium.</p><button class="vip-btn" data-section="promotions">CONHECER O CLUBE</button></div>
        <div class="vip-seal">90</div>
      </section>
    </section>

    <section class="page-section" id="section-casino">
      <div id="casino-managed-banners" class="managed-banners"></div>
      <div class="inner-head"><span>CATÁLOGO</span><h1>Cassino</h1><p>Escolha uma categoria e encontre seu próximo jogo.</p></div>
      <section class="category-strip sticky-strip" id="casino-category-row"></section>
      <div class="game-grid catalog" id="casino-games"></div>
    </section>

    <section class="page-section" id="section-live">
      <div class="inner-head"><span>LIVE CASINO</span><h1>Ao vivo</h1><p>Jogos publicados no catálogo. A execução estará disponível após a integração do provedor.</p></div>
      <div class="game-grid catalog" id="live-games"></div>
    </section>

    <section class="page-section" id="section-promotions">
      <div id="managed-promotions" class="managed-promotions"></div>
      <div class="inner-head"><span>PROMOÇÕES</span><h1>Benefícios</h1><p>Área pronta para campanhas, VIP, cashback e bônus.</p></div>
      <p class="empty-state">As campanhas publicadas aparecem nesta página automaticamente.</p>
    </section>

    <section class="page-section" id="section-wallet">
      <div class="inner-head"><span>MINHA CONTA</span><h1>Carteira</h1><p>Saldo real alimentado pelo ledger do backend.</p></div>
      <section class="wallet-balance gold-frame"><small>Saldo disponível</small><strong id="wallet-total">R$ 0,00</strong><div class="wallet-actions"><button class="gold-btn" id="deposit-placeholder">DEPOSITAR</button><button class="dark-btn" id="withdraw-placeholder">SACAR</button></div></section>
      <section class="wallet-card"><h3>Contas</h3><div id="wallet-accounts" class="account-list"><div class="empty-state">Entre para consultar a carteira.</div></div></section>
      <section class="wallet-card"><div class="wallet-card-head"><h3>Movimentações</h3><button id="refresh-wallet">Atualizar</button></div><div id="transaction-list" class="transaction-list"><div class="empty-state">Nenhuma movimentação.</div></div></section>
    </section>
  </main>

  <nav class="bottom-nav" aria-label="Navegação principal">
    <button class="bottom-item active" data-section="home"><i></i><span>Início</span></button>
    <button class="bottom-item" data-section="promotions"><i></i><span>Promoção</span></button>
    <button class="bottom-item center" data-section="casino"><b>M</b><span>MZ90</span></button>
    <button class="bottom-item" data-section="wallet"><i></i><span>Depósito</span></button>
    <button class="bottom-item" id="profile-nav"><i></i><span>Perfil</span></button>
  </nav>
</div>

<div class="modal-backdrop hidden" id="auth-modal" role="dialog" aria-modal="true">
  <div class="auth-modal">
    <button class="modal-close" id="close-auth">×</button>
    <div class="auth-brand"><span class="brand-round">M</span><div><strong><?= h($appName) ?></strong><small>CASINO</small></div></div>
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

<footer id="platform-footer" class="platform-footer"></footer>
<div class="toast-stack" id="toast-stack"></div>
<script>window.IGAMING = <?= json_encode(['basePath'=>$basePath,'appName'=>$appName], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="<?= h($basePath) ?>/assets/app.js?v=20260916-slider3"></script>
</body>
</html>
