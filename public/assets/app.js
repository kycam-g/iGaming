(() => {
  const cfg = window.IGAMING || {basePath:''};
  const base=(cfg.basePath||'').replace(/\/$/,'');
  const state={token:localStorage.getItem('igaming_token')||'',user:null,accounts:[],transactions:[],paymentGateways:[],activePayment:null,depositInFlight:false,depositIdempotencyKey:'',paymentPoll:null};
  const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
  const money=(minor=0,currency='BRL')=>new Intl.NumberFormat('pt-BR',{style:'currency',currency}).format(Number(minor)/100);
  const api=async(path,options={})=>{const headers={'Accept':'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(options.headers||{})};if(state.token)headers.Authorization=`Bearer ${state.token}`;const res=await fetch(`${base}${path}`,{...options,headers});let data={};try{data=await res.json()}catch{}if(!res.ok)throw new Error(data.message||data.error||`Erro ${res.status}`);return data};
  const toast=(msg,type='')=>{const el=document.createElement('div');el.className=`toast ${type}`;el.textContent=msg;$('#toast-stack').appendChild(el);setTimeout(()=>el.remove(),3000)};
  // Catálogo publicado pelo Admin; nunca substituímos por jogos fictícios.
  const catalog={games:[],category:'ALL'};
  const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const categories=[['ALL','Todos'],['SLOTS','Slots'],['LIVE','Ao vivo'],['TABLE','Mesa'],['OTHER','Outros']];
  function gameCard(game){
    const image=game.image_url?`<img src="${escapeHtml(game.image_url)}" alt="" loading="lazy" referrerpolicy="no-referrer">`:'<span class="game-icon">🎮</span>';
    return `<article class="game-card" data-category="${escapeHtml(game.category)}"><div class="game-thumb casino-thumb">${image}</div><div class="game-info"><strong>${escapeHtml(game.name)}</strong><small>${escapeHtml(game.provider_name)}</small></div><button type="button" class="game-play" data-game-id="${Number(game.id)}">Detalhes</button></article>`;
  }
  function renderCatalog(){
    const games=catalog.games;
    const featured=games.filter(g=>Number(g.featured)===1).slice(0,8);
    $('#featured-games').innerHTML=featured.length?featured.map(gameCard).join(''):'<div class="empty-state catalog-empty">Nenhum jogo em destaque publicado ainda.</div>';
    const available=new Set(games.map(g=>g.category));
    const chips=categories.filter(([key])=>key==='ALL'||available.has(key)).map(([key,label])=>`<button type="button" class="category-chip ${catalog.category===key?'active':''}" data-category-filter="${key}">${label}</button>`).join('');
    $('#category-row').innerHTML=chips;
    $('#casino-category-row').innerHTML=chips;
    const filtered=games.filter(g=>catalog.category==='ALL'||g.category===catalog.category);
    $('#casino-games').innerHTML=filtered.length?filtered.map(gameCard).join(''):'<div class="empty-state catalog-empty">Nenhum jogo disponível nesta categoria.</div>';
    const live=games.filter(g=>g.category==='LIVE');
    $('#live-games').innerHTML=live.length?live.map(gameCard).join(''):'<div class="empty-state catalog-empty">Nenhum jogo ao vivo publicado ainda.</div>';
  }
  async function loadCatalog(){
    try{const response=await api('/api/casino/games');catalog.games=Array.isArray(response.games)?response.games:[];renderCatalog()}
    catch(error){catalog.games=[];renderCatalog();toast('Não foi possível carregar o catálogo de jogos.');}
  }
  renderCatalog();
  document.addEventListener('click',event=>{
    const category=event.target.closest('[data-category-filter]');
    if(category){catalog.category=category.dataset.categoryFilter;renderCatalog();return;}
    const game=event.target.closest('[data-game-id]');
    if(game){const selected=catalog.games.find(item=>Number(item.id)===Number(game.dataset.gameId));toast(selected?`${selected.name}: catálogo demonstrativo. Para jogar, é necessária a integração da API do provedor.`:'Jogo indisponível.');}
  });
  function section(name){$$('.page-section').forEach(x=>x.classList.toggle('active',x.id===`section-${name}`));$$('.bottom-item[data-section]').forEach(x=>x.classList.toggle('active',x.dataset.section===name));window.scrollTo({top:0,behavior:'smooth'});if(name==='wallet')loadWallet()}
  $$('[data-section]').forEach(b=>b.addEventListener('click',()=>section(b.dataset.section)));
  function openAuth(tab='login'){$('#auth-modal').classList.remove('hidden');authTab(tab)}
  function authTab(tab){$$('[data-auth-tab]').forEach(b=>b.classList.toggle('active',b.dataset.authTab===tab));$('#login-form').classList.toggle('hidden',tab!=='login');$('#register-form').classList.toggle('hidden',tab!=='register');$('#auth-alert').classList.add('hidden')}
  $$('[data-open-auth]').forEach(b=>b.addEventListener('click',()=>openAuth(b.dataset.openAuth)));$$('[data-auth-tab]').forEach(b=>b.addEventListener('click',()=>authTab(b.dataset.authTab)));$('#close-auth').addEventListener('click',()=>$('#auth-modal').classList.add('hidden'));$('#auth-modal').addEventListener('click',e=>{if(e.target.id==='auth-modal')e.currentTarget.classList.add('hidden')});
  function authError(err){const a=$('#auth-alert');a.textContent=err.message;a.classList.remove('hidden')}
  async function finishAuth(data){state.token=data.token;state.user=data.user;localStorage.setItem('igaming_token',state.token);$('#auth-modal').classList.add('hidden');renderAuth();await loadWallet();toast('Bem-vindo ao MZ90!','success')}
  $('#login-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);try{await finishAuth(await api('/api/auth/login',{method:'POST',body:JSON.stringify(Object.fromEntries(f))}))}catch(err){authError(err)}});
  $('#register-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);try{await finishAuth(await api('/api/auth/register',{method:'POST',body:JSON.stringify(Object.fromEntries(f))}))}catch(err){authError(err)}});
  const maskCpf=v=>{const d=v.replace(/\D/g,'').slice(0,11);return d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2')};
  const maskPhone=v=>{const d=v.replace(/\D/g,'').slice(0,11);return d.length>10?d.replace(/(\d{2})(\d{5})(\d{0,4})/,'($1) $2-$3'):d.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3')};
  const cpfInput=$('#register-form [name=cpf]'),phoneInput=$('#register-form [name=phone]');if(cpfInput)cpfInput.addEventListener('input',e=>e.target.value=maskCpf(e.target.value));if(phoneInput)phoneInput.addEventListener('input',e=>e.target.value=maskPhone(e.target.value));
  function renderAuth(){const logged=!!state.user;$('#guest-actions').classList.toggle('hidden',logged);$('#user-actions').classList.toggle('hidden',!logged);if(logged)$('#user-avatar').textContent=(state.user.username||'U').slice(0,1).toUpperCase()}
  function renderWallet(){const total=state.accounts.reduce((s,a)=>s+Number(a.balance_minor||0),0);$('#header-balance').textContent=money(total);$('#wallet-total').textContent=money(total);$('#wallet-accounts').innerHTML=state.accounts.length?state.accounts.map(a=>`<div class="account-row"><div><strong>${a.type}</strong><span>${a.currency}</span></div><b>${money(a.balance_minor,a.currency)}</b></div>`).join(''):'<div class="empty-state">Nenhuma conta encontrada.</div>';$('#transaction-list').innerHTML=state.transactions.length?state.transactions.map(t=>`<div class="transaction-row"><div><strong>${t.type}</strong><small>${t.account_type} • ${new Date(t.created_at).toLocaleString('pt-BR')}</small></div><b class="${t.direction==='CREDIT'?'money-credit':'money-debit'}">${t.direction==='CREDIT'?'+':'-'} ${money(t.amount_minor,t.currency)}</b></div>`).join(''):'<div class="empty-state">Nenhuma movimentação.</div>'}
  async function loadWallet(){if(!state.token){state.accounts=[];state.transactions=[];renderWallet();return}try{const [w,t]=await Promise.all([api('/api/wallet'),api('/api/wallet/transactions?limit=20')]);state.accounts=w.accounts||[];state.transactions=t.transactions||[];renderWallet()}catch(e){if(/unauthorized/i.test(e.message))logout(false)}}
  async function restore(){if(!state.token){renderAuth();renderWallet();return}try{const r=await api('/api/me');state.user=r.user;renderAuth();await loadWallet()}catch{logout(false)}}
  async function logout(callApi=true){try{if(callApi&&state.token)await api('/api/auth/logout',{method:'POST'})}catch{}state.token='';state.user=null;state.accounts=[];state.transactions=[];localStorage.removeItem('igaming_token');renderAuth();renderWallet();toast('Sessão encerrada.')}
  $('#user-avatar').addEventListener('click',()=>{if(confirm(`Olá ${state.user?.username||''}. Deseja sair?`))logout(true)});$('#profile-nav').addEventListener('click',()=>state.user?section('wallet'):openAuth('login'));$('#refresh-wallet').addEventListener('click',loadWallet);
  async function loadPaymentGateways(){const r=await api('/api/payments/gateways');state.paymentGateways=r.gateways||[];$('#gateway-select').innerHTML=state.paymentGateways.map(g=>`<option value="${g.code}">${g.name}${g.sandbox?' • Sandbox':''}</option>`).join('')}
  const clearPaymentPoll=()=>{if(state.paymentPoll){clearInterval(state.paymentPoll);state.paymentPoll=null}};
  const closePayment=()=>{clearPaymentPoll();$('#payment-modal').classList.add('hidden')};
  const newIdempotencyKey=()=>crypto.randomUUID?crypto.randomUUID():`${Date.now()}-${Math.random().toString(16).slice(2)}`;
  function setGenerateLoading(loading){state.depositInFlight=loading;const btn=$('#generate-pix');if(!btn)return;btn.disabled=loading;btn.classList.toggle('is-loading',loading);$('.btn-label',btn).textContent=loading?'GERANDO PIX...':'GERAR PIX'}
  function renderPixQr(code){const host=$('#pix-qr');host.innerHTML='';if(!code){host.innerHTML='<small>QR Code indisponível</small>';return}if(typeof window.QRCode!=='function'){host.innerHTML='<small>Não foi possível carregar o gerador de QR Code. Use o PIX copia e cola.</small>';return}new QRCode(host,{text:code,width:160,height:160,colorDark:'#000000',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M})}
  function renderPayment(payment){state.activePayment=payment;$('#deposit-form').classList.add('hidden');$('#pix-result').classList.remove('hidden');const code=payment.payment_code||payment.payment_qr_code||'';$('#pix-code').value=code;renderPixQr(code);const paid=payment.status==='PAID';$('#pix-status').textContent=paid?'Pagamento confirmado':payment.status==='FAILED'?'Falha no pagamento':payment.status==='EXPIRED'?'PIX expirado':'Aguardando pagamento';const gw=state.paymentGateways.find(g=>g.code===payment.gateway_code);$('#sandbox-confirm').classList.toggle('hidden',!gw?.sandbox||paid);$('#sandbox-note').textContent=gw?.sandbox?'Ambiente local: o botão abaixo simula a confirmação que futuramente virá pelo webhook do gateway.':'';if(paid){clearPaymentPoll();loadWallet();toast('Pagamento confirmado e saldo atualizado.','success')}}
  function startPaymentPoll(){clearPaymentPoll();if(!state.activePayment||state.activePayment.status==='PAID')return;state.paymentPoll=setInterval(async()=>{if(!state.activePayment||$('#payment-modal').classList.contains('hidden')){clearPaymentPoll();return}try{const r=await api(`/api/payments/status?id=${encodeURIComponent(state.activePayment.id)}`);if(r.payment)renderPayment(r.payment)}catch{}},3000)}
  async function openDeposit(){if(!state.user){openAuth('login');return}try{await loadPaymentGateways();if(!state.paymentGateways.length){toast('Nenhum gateway de pagamento ativo.');return}clearPaymentPoll();state.activePayment=null;state.depositIdempotencyKey='';setGenerateLoading(false);$('#payment-alert').classList.add('hidden');$('#pix-result').classList.add('hidden');$('#deposit-form').classList.remove('hidden');$('#payment-modal').classList.remove('hidden')}catch(e){toast(e.message)}}
  $('#deposit-placeholder').addEventListener('click',openDeposit);$$('.bottom-item[data-section="wallet"]').forEach(b=>b.addEventListener('dblclick',openDeposit));$('#close-payment').addEventListener('click',closePayment);$('#payment-modal').addEventListener('click',e=>{if(e.target.id==='payment-modal')closePayment()});
  const paymentError=e=>{const a=$('#payment-alert');a.textContent=e.message;a.classList.remove('hidden')};
  $('#deposit-form').addEventListener('submit',async e=>{e.preventDefault();if(state.depositInFlight)return;const f=new FormData(e.currentTarget);const amount=Math.round(Number(f.get('amount'))*100);if(!Number.isFinite(amount)||amount<100){paymentError(new Error('Informe um valor válido.'));return}state.depositIdempotencyKey=state.depositIdempotencyKey||newIdempotencyKey();$('#payment-alert').classList.add('hidden');setGenerateLoading(true);try{const r=await api('/api/payments/deposits',{method:'POST',body:JSON.stringify({amount_minor:amount,gateway_code:f.get('gateway_code'),idempotency_key:state.depositIdempotencyKey})});renderPayment(r.payment);startPaymentPoll()}catch(err){paymentError(err)}finally{setGenerateLoading(false)}});
  $('#copy-pix').addEventListener('click',async()=>{const value=$('#pix-code').value;try{await navigator.clipboard.writeText(value);toast('PIX copiado.','success')}catch{toast('Não foi possível copiar automaticamente.')}});
  $('#sandbox-confirm').addEventListener('click',async()=>{if(!state.activePayment)return;try{const r=await api('/api/payments/sandbox/confirm',{method:'POST',body:JSON.stringify({payment_id:state.activePayment.id})});renderPayment(r.payment);$('#sandbox-confirm').classList.add('hidden');await loadWallet()}catch(e){paymentError(e)}});
  $('#withdraw-placeholder').addEventListener('click',()=>toast('Saques serão habilitados quando definirmos o primeiro gateway de payout.'));
  async function loadPlatformContent(){
    try{
      const data=await api('/api/platform/public');const settings=data.settings||{};
      if(/^#[0-9a-fA-F]{6}$/.test(settings.accent_color||''))document.documentElement.style.setProperty('--platform-accent',settings.accent_color);
      if(settings.site_name){document.title=settings.site_name;document.querySelectorAll('.brand-text b,.auth-brand strong').forEach(el=>el.textContent=settings.site_name);}
      $('#platform-footer').textContent=settings.footer_text||'';
      const banners=(data.banners||[]);
      const safeTarget=value=>/^\/(?:$|[a-z0-9/_-]+$)/i.test(value||'')?value:'/cassino';
      const makeBanner=(item,cls)=>{const a=document.createElement('a');a.className=cls;a.href=safeTarget(item.target_path);a.setAttribute('aria-label',item.title||'Abrir destaque');if(item.image_path){const img=document.createElement('img');img.src=item.image_path;img.alt=item.title||'';img.loading=cls==='slider-slide'?'eager':'lazy';a.append(img)}else{const caption=document.createElement('span');caption.textContent=item.title;a.append(caption)}return a};
      const slider=$('#managed-banners');slider.replaceChildren();
      const slides=banners.filter(b=>b.position==='home'&&Number(b.enabled)===1).sort((a,b)=>Number(a.sort_order)-Number(b.sort_order)||Number(a.id)-Number(b.id));
      if(window.mz90SliderCleanup)window.mz90SliderCleanup();
      if(slides.length){const track=document.createElement('div');track.className='slider-track';slides.forEach(b=>track.append(makeBanner(b,'slider-slide')));slider.append(track);let index=0;const dots=document.createElement('div');dots.className='slider-dots';const controls=[];const show=n=>{index=(n+slides.length)%slides.length;track.style.transform=`translateX(-${index*100}%)`;controls.forEach((b,i)=>{b.classList.toggle('active',i===index);b.setAttribute('aria-current',i===index?'true':'false')})};slides.forEach((b,i)=>{const dot=document.createElement('button');dot.type='button';dot.setAttribute('aria-label',`Ir para banner ${i+1}`);dot.onclick=()=>{show(i);restart()};dots.append(dot);controls.push(dot)});slider.append(dots);let timer=null;const stop=()=>{if(timer){clearInterval(timer);timer=null}};const restart=()=>{stop();if(slides.length>1&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches)timer=setInterval(()=>{if(!document.hidden)show(index+1)},5500)};if(slides.length>1){for(const [cls,step,label] of [['slider-prev',-1,'Banner anterior'],['slider-next',1,'Próximo banner']]){const btn=document.createElement('button');btn.type='button';btn.className='slider-arrow '+cls;btn.setAttribute('aria-label',label);btn.textContent=step<0?'‹':'›';btn.onclick=()=>{show(index+step);restart()};slider.append(btn)}let touchX=null;slider.addEventListener('touchstart',e=>{touchX=e.changedTouches[0]?.clientX??null;stop()},{passive:true});slider.addEventListener('touchend',e=>{if(touchX!==null){const delta=(e.changedTouches[0]?.clientX??touchX)-touchX;if(Math.abs(delta)>45)show(index+(delta<0?1:-1))}touchX=null;restart()},{passive:true});slider.addEventListener('mouseenter',stop);slider.addEventListener('mouseleave',restart);slider.addEventListener('focusin',stop);slider.addEventListener('focusout',restart)}else dots.hidden=true;show(0);restart();window.mz90SliderCleanup=stop;
      }else{const empty=document.createElement('div');empty.className='slider-empty';empty.textContent='Nenhum banner principal publicado.';slider.append(empty)}
      const lobby=$('#home-lobby');lobby.replaceChildren();const lobbyItems=banners.filter(b=>b.position==='casino'&&Number(b.enabled)===1).sort((a,b)=>Number(a.sort_order)-Number(b.sort_order)||Number(a.id)-Number(b.id)).slice(0,3);lobbyItems.forEach((b,i)=>{const el=makeBanner(b,'lobby-banner lobby-slot-'+(i+1));lobby.append(el)});if(!lobbyItems.length)lobby.hidden=true;else lobby.hidden=false;
      const casino=$('#casino-managed-banners');casino.replaceChildren();
      const promotions=$('#managed-promotions');promotions.replaceChildren();(data.promotions||[]).forEach(pr=>{
        const article=document.createElement('article');article.className='managed-promotion';
        if(pr.image_path){const image=document.createElement('img');image.src=pr.image_path;image.alt='';image.loading='lazy';article.append(image)}
        const content=document.createElement('div');const title=document.createElement('h2');title.textContent=pr.title;const description=document.createElement('p');description.textContent=pr.description;const start=pr.starts_at?new Date(String(pr.starts_at).replace(' ','T')):null;const upcoming=start&&!Number.isNaN(start.getTime())&&start.getTime()>Date.now();const status=document.createElement('span');status.className='promotion-availability '+(upcoming?'upcoming':'available');status.textContent=upcoming?'Em breve · disponível em '+new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(start):'Disponível';content.prepend(status);content.append(title,description);article.append(content);promotions.append(article);
      });
    }catch(error){console.warn('Conteúdo dinâmico indisponível:',error.message)}
  }
  loadPlatformContent();
  loadCatalog();
  restore();
})();
