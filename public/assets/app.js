(() => {
  const cfg = window.IGAMING || {basePath:''};
  const base=(cfg.basePath||'').replace(/\/$/,'');
  const state={token:localStorage.getItem('igaming_token')||'',user:null,accounts:[],transactions:[],paymentGateways:[],activePayment:null,depositInFlight:false,depositIdempotencyKey:'',paymentPoll:null};
  const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
  const money=(minor=0,currency='BRL')=>new Intl.NumberFormat('pt-BR',{style:'currency',currency}).format(Number(minor)/100);
  const api=async(path,options={})=>{const headers={'Accept':'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(options.headers||{})};if(state.token)headers.Authorization=`Bearer ${state.token}`;const res=await fetch(`${base}${path}`,{...options,headers});let data={};try{data=await res.json()}catch{}if(!res.ok)throw new Error(data.message||data.error||`Erro ${res.status}`);return data};
  const toast=(msg,type='')=>{const el=document.createElement('div');el.className=`toast ${type}`;el.textContent=msg;$('#toast-stack').appendChild(el);setTimeout(()=>el.remove(),3000)};
  const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const setBrowserFavicon=path=>{
    const link=$('#browser-favicon')||document.createElement('link');
    link.id='browser-favicon';link.rel='icon';
    if(!link.parentNode)document.head.append(link);
    const value=String(path||'').trim();
    if(!/^\/uploads\/identity\/[a-z0-9_-]+\.(?:png|jpg|webp)$/i.test(value)){link.href='data:,';return;}
    const ext=value.split('.').pop().toLowerCase();link.type=ext==='png'?'image/png':ext==='webp'?'image/webp':'image/jpeg';link.href=base+value;
  };

  const favoritesKey='igaming_favorites';
  const loadFavoriteIds=()=>{try{return new Set(JSON.parse(localStorage.getItem(favoritesKey)||'[]').map(Number).filter(Number.isFinite))}catch{return new Set()}};
  let favoriteIds=loadFavoriteIds();
  const saveFavoriteIds=()=>localStorage.setItem(favoritesKey,JSON.stringify([...favoriteIds]));
  const isFavorite=id=>favoriteIds.has(Number(id));
  const toggleFavorite=id=>{id=Number(id);if(isFavorite(id))favoriteIds.delete(id);else favoriteIds.add(id);saveFavoriteIds();};

  const catalog={games:[],providers:[],categories:[],category:'ALL',provider:'',query:''};
  const casinoPrimaryFilters=[
    {key:'ALL',label:'Todos',icon:'all'},
    {key:'POPULAR',label:'Popular',icon:'popular'},
    {key:'RECENT',label:'Recente',icon:'recent'},
    {key:'FAVORITES',label:'Favoritos',icon:'heart'}
  ];
  const iconMarkup=(name,solid=false)=>({
    all:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16"/><path d="M12 4v16"/><circle cx="12" cy="12" r="8.5"/></svg>`,
    slots:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="6" width="16" height="12" rx="3"/><path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/><path d="M8 14h8"/></svg>`,
    fish:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12s3-5 8-5c4.5 0 7 2.2 10 5-3 2.8-5.5 5-10 5-5 0-8-5-8-5Z"/><circle cx="14.5" cy="11" r=".8" fill="currentColor" stroke="none"/><path d="M6 12H3"/></svg>`,
    sport:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 5h10l2 5-7 9-7-9 2-5Z"/><path d="M9 10h6"/></svg>`,
    roulette:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="2.5"/><path d="M12 3.5v6"/><path d="M20.5 12h-6"/><path d="m17.8 6.2-4.2 4.2"/><path d="m6.2 6.2 4.2 4.2"/></svg>`,
    live:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="m10 9 5 3-5 3Z"/></svg>`,
    table:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14l2 10H3L5 7Z"/><path d="M8 11h8"/></svg>`,
    other:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="19" cy="12" r="1" fill="currentColor"/></svg>`,
    popular:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c1.4 2.8 4 4.5 4 8a4 4 0 0 1-8 0c0-1.9.9-3.2 2.3-4.9"/><path d="M8 14c0 2.2 1.8 4 4 4s4-1.8 4-4c0-1.8-.9-3-2-4.1"/></svg>`,
    recent:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/></svg>`,
    heart:`<svg viewBox="0 0 24 24" ${solid?'fill="currentColor" stroke="currentColor"':'fill="none" stroke="currentColor"'} stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.5 4.8 13.7a4.7 4.7 0 0 1 6.6-6.7L12 7.7l.6-.7a4.7 4.7 0 0 1 6.6 6.7Z"/></svg>`,
    spark:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8Z"/></svg>`,
    home:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>`
  }[name]||'');
  const providerIcon=(provider,variant='default')=>{const cls=variant==='top'?'provider-top-logo':(variant==='group'?'provider-group-logo':'');return provider?.logo_path?`<img class="${cls}" src="${escapeHtml(provider.logo_path)}" alt="" width="150" height="60" loading="lazy">`:`<span class="provider-logo-fallback ${cls}" aria-hidden="true">${escapeHtml(String(provider?.name||'?').slice(0,2).toUpperCase())}</span>`};
  const sampleAccessCount=game=>{const explicit=Number(game.access_count);if(Number.isFinite(explicit)&&explicit>0)return explicit;return 180+((Number(game.id)||1)*137+(Number(game.sort_order)||0))%9800};
  const accessText=game=>sampleAccessCount(game).toLocaleString('pt-BR');
  const sortRecent=(a,b)=>Number(b.id)-Number(a.id)||Number(a.sort_order)-Number(b.sort_order);
  const sortDefault=(a,b)=>Number(b.featured)-Number(a.featured)||Number(a.sort_order)-Number(b.sort_order)||Number(b.id)-Number(a.id);

  function filterGame(game){
    if(catalog.provider && String(game.provider_id)!==catalog.provider)return false;
    if(catalog.query && !String(game.name).toLocaleLowerCase('pt-BR').includes(catalog.query))return false;
    if(catalog.category==='ALL')return true;
    if(catalog.category==='POPULAR')return Number(game.featured)===1;
    if(catalog.category==='RECENT')return true;
    if(catalog.category==='FAVORITES')return isFavorite(game.id);
    return game.category===catalog.category;
  }
  function filteredGames(){
    const items=catalog.games.filter(filterGame);
    return [...items].sort(catalog.category==='RECENT'?sortRecent:sortDefault);
  }
  function chipButton(item,active=false){
    return `<button type="button" class="category-chip ${active?'active':''}" data-category-filter="${escapeHtml(item.key)}"><span class="category-chip-icon" aria-hidden="true">${iconMarkup(item.icon)}</span><span class="category-chip-label">${escapeHtml(item.label)}</span></button>`;
  }
  function gameCard(game,{compact=false}={}){
    const image=game.image_url?`<img src="${escapeHtml(game.image_url)}" alt="" loading="lazy" referrerpolicy="no-referrer">`:'<span class="game-icon">🎮</span>';
    const fav=isFavorite(game.id);
    return `<article class="game-card${compact?' compact-home':''}" data-category="${escapeHtml(game.category)}"><div class="game-thumb-wrap"><button class="game-favorite${fav?' active':''}" type="button" data-favorite-toggle="${Number(game.id)}" aria-label="${fav?'Remover dos favoritos':'Salvar nos favoritos'}" aria-pressed="${fav?'true':'false'}">${iconMarkup('heart',fav)}</button><button class="game-thumb casino-thumb game-cover-link" type="button" data-game-id="${Number(game.id)}" aria-label="Abrir página do jogo ${escapeHtml(game.name)}">${image}</button></div><div class="game-info"><strong>${escapeHtml(game.name)}</strong><small>${escapeHtml(game.provider_name)}</small><span class="game-online has-count"><i aria-hidden="true"></i> Acessos: <b>${accessText(game)}</b></span></div></article>`;
  }
  function renderProviderCarousel(){
    const host=$('#provider-scroll'); if(!host)return; host.replaceChildren();
    const all=document.createElement('button');all.type='button';all.className='provider-chip';all.dataset.providerView='';all.innerHTML=`<span class="provider-all-icon" aria-hidden="true">${iconMarkup('spark')}</span><span>Todos</span>`;host.append(all);
    for(const provider of catalog.providers){const b=document.createElement('button');b.type='button';b.className='provider-chip provider-logo-only';b.dataset.providerView=String(provider.id);b.setAttribute('aria-label',`Mostrar jogos de ${provider.name}`);b.title=provider.name;b.innerHTML=providerIcon(provider,'top');host.append(b)}
  }
  function renderCategoryRows(){
    const available=new Set(catalog.games.map(g=>String(g.category)));
    const published=(catalog.categories||[]).filter(item=>Number(item.enabled)!==0&&available.has(String(item.code))).sort((a,b)=>Number(a.sort_order)-Number(b.sort_order)||String(a.name).localeCompare(String(b.name),'pt-BR'));
    const homeItems=[{key:'ALL',label:'Todos',icon:'all'},...published.slice(0,4).map(item=>({key:item.code,label:item.name,icon:item.icon_key||'slots'}))];
    const casinoItems=[...casinoPrimaryFilters,...published.map(item=>({key:item.code,label:item.name,icon:item.icon_key||'slots'}))];
    const home=$('#category-row'),casino=$('#casino-category-row');
    if(home)home.innerHTML=homeItems.map(item=>chipButton(item,catalog.category===item.key)).join('');
    if(casino)casino.innerHTML=casinoItems.map(item=>chipButton(item,catalog.category===item.key)).join('');
  }
  function renderCatalog(){
    const games=[...catalog.games].sort(sortDefault);
    const featured=games.filter(g=>Number(g.featured)===1).slice(0,10);
    const featuredSection=$('#featured-section');if(featuredSection)featuredSection.hidden=games.length===0;
    const featuredHeading=$('#featured-section > .section-title');if(featuredHeading)featuredHeading.classList.toggle('hidden',featured.length===0);
    const featuredGrid=$('#featured-games');if(featuredGrid){featuredGrid.classList.toggle('hidden',featured.length===0);featuredGrid.innerHTML=featured.map(game=>gameCard(game,{compact:true})).join('')}
    const groups=$('#home-provider-groups');
    if(groups){
      groups.replaceChildren();
      for(const provider of catalog.providers){
        const own=games.filter(g=>Number(g.provider_id)===Number(provider.id));
        if(!own.length)continue;
        const section=document.createElement('section');section.className='provider-game-group';
        section.innerHTML=`<div class="section-title provider-group-title"><button type="button" class="provider-group-name provider-logo-trigger" data-provider-view="${Number(provider.id)}" title="${escapeHtml(provider.name)}" aria-label="Mostrar jogos de ${escapeHtml(provider.name)}">${providerIcon(provider,'group')}</button><button type="button" data-provider-view="${Number(provider.id)}">Ver todos</button></div><div class="game-grid home-game-grid">${own.slice(0,12).map(game=>gameCard(game,{compact:true})).join('')}</div>`;
        groups.append(section)
      }
      if(!groups.children.length)groups.innerHTML='<p class="empty-state">Nenhum provedor com jogos ativos publicado.</p>';
    }
    renderProviderCarousel();
    renderCategoryRows();
    const filtered=filteredGames();
    $('#casino-games').innerHTML=filtered.length?filtered.map(game=>gameCard(game)).join(''):'<div class="empty-state catalog-empty">Nenhum jogo encontrado com os filtros selecionados.</div>';
    const activeProvider=catalog.providers.find(p=>String(p.id)===catalog.provider);const summary=$('#casino-filter-summary');if(summary)summary.textContent=`${filtered.length} jogo${filtered.length===1?'':'s'}${activeProvider?' · '+activeProvider.name:''}${catalog.category==='FAVORITES'?' · Favoritos':''}${catalog.category==='POPULAR'?' · Populares':''}${catalog.category==='RECENT'?' · Recentes':''}`;
    const live=games.filter(g=>g.category==='LIVE');$('#live-games').innerHTML=live.length?live.map(game=>gameCard(game)).join(''):'<div class="empty-state catalog-empty">Nenhum jogo ao vivo publicado ainda.</div>';
  }
  async function loadCatalog(){
    try{
      const [gameResult,providerResult,categoryResult]=await Promise.allSettled([api('/api/casino/games'),api('/api/casino/providers'),api('/api/casino/categories')]);
      if(gameResult.status!=='fulfilled')throw gameResult.reason;
      catalog.games=Array.isArray(gameResult.value.games)?gameResult.value.games:[];
      if(providerResult.status==='fulfilled'&&Array.isArray(providerResult.value.providers))catalog.providers=providerResult.value.providers;
      else catalog.providers=[...new Map(catalog.games.map(game=>[Number(game.provider_id),{id:Number(game.provider_id),name:game.provider_name||game.provider_code||'Provedor',logo_path:game.provider_logo||''}])).values()];
      catalog.categories=categoryResult.status==='fulfilled'&&Array.isArray(categoryResult.value.categories)?categoryResult.value.categories:[
        {code:'SLOTS',name:'Slots',icon_key:'slots',enabled:1,sort_order:10},{code:'OTHER',name:'Pescaria',icon_key:'fish',enabled:1,sort_order:20},{code:'LIVE',name:'SportBet',icon_key:'sport',enabled:1,sort_order:30},{code:'TABLE',name:'Roleta',icon_key:'roulette',enabled:1,sort_order:40}
      ];
      renderCatalog();
    }catch(error){catalog.games=[];catalog.providers=[];catalog.categories=[];renderCatalog();toast('Não foi possível carregar o catálogo de jogos.');}
  }
  renderCatalog();
  document.addEventListener('click',event=>{
    const favorite=event.target.closest('[data-favorite-toggle]');
    if(favorite){event.preventDefault();event.stopPropagation();toggleFavorite(favorite.dataset.favoriteToggle);renderCatalog();return;}
    const category=event.target.closest('[data-category-filter]');if(category){catalog.category=category.dataset.categoryFilter;renderCatalog();return;}
    const provider=event.target.closest('[data-provider-view]');if(provider){catalog.provider=provider.dataset.providerView||'';catalog.category='ALL';catalog.query='';const search=$('#public-game-search');if(search)search.value='';section('casino');renderCatalog();return;}
    const game=event.target.closest('[data-game-id]');if(game){const selected=catalog.games.find(item=>Number(item.id)===Number(game.dataset.gameId));if(selected)openGamePage(selected);else toast('Jogo indisponível.');}
  });
  let previousGameSection='home';
  function openGamePage(game){
    previousGameSection=$('.page-section.active')?.id?.replace('section-','')||'home';
    const art=$('#game-detail-art');art.replaceChildren();
    if(game.image_url){const img=document.createElement('img');img.src=game.image_url;img.alt=game.name;img.loading='eager';art.append(img)}
    else{const fallback=document.createElement('span');fallback.className='game-icon';fallback.textContent='🎮';art.append(fallback)}
    $('#game-detail-name').textContent=game.name;$('#game-detail-provider').textContent=game.provider_name||'Provedor';
    const count=sampleAccessCount(game);
    $('#detail-online-count').textContent=count.toLocaleString('pt-BR');
    $('#detail-online-dot').classList.add('available');
    section('game');
  }
  $('#game-back').addEventListener('click',()=>section(previousGameSection==='game'?'casino':previousGameSection));
  const search=$('#public-game-search');if(search)search.addEventListener('input',e=>{catalog.query=e.target.value.trim().toLocaleLowerCase('pt-BR');renderCatalog()});
  const clear=$('#clear-casino-filters');if(clear)clear.addEventListener('click',()=>{catalog.query='';catalog.category='ALL';catalog.provider='';if(search)search.value='';renderCatalog()});
  const homeSearch=$('#home-search');if(homeSearch)homeSearch.addEventListener('click',()=>{section('casino');if(search)search.focus()});
  const providerStrip=$('#provider-scroll');const prev=$('#providers-prev'),next=$('#providers-next');if(prev&&providerStrip)prev.addEventListener('click',()=>providerStrip.scrollBy({left:-240,behavior:'smooth'}));if(next&&providerStrip)next.addEventListener('click',()=>providerStrip.scrollBy({left:240,behavior:'smooth'}));
  const navLabel=$('#home-nav-label');const updateNavLabel=()=>{if(navLabel)navLabel.textContent=window.scrollY>140?'Topo':'Início'};window.addEventListener('scroll',updateNavLabel,{passive:true});updateNavLabel();
  function section(name){$$('.page-section').forEach(x=>x.classList.toggle('active',x.id===`section-${name}`));$$('.bottom-item[data-section]').forEach(x=>x.classList.toggle('active',x.dataset.section===name));window.scrollTo({top:0,behavior:'smooth'});if(name==='profile')loadWallet()}
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
  function renderAuth(){const logged=!!state.user;$('#guest-actions').classList.toggle('hidden',logged);$('#user-actions').classList.toggle('hidden',!logged);if(logged){const user=state.user;$('#profile-display-name').textContent=user.username||('Jogador '+(user.public_id||''));$('#profile-public-id').textContent=user.public_id||'—';$('#profile-email').textContent=user.email||'Não cadastrado';const phone=String(user.phone||'');$('#profile-phone').textContent=phone.length>=4?'•••• '+phone.slice(-4):'—';const cpf=String(user.cpf||'');$('#profile-cpf').textContent=cpf.length===11?'•••.•••.•••-'+cpf.slice(-2):'—';$('#profile-status').textContent=user.status==='ACTIVE'?'Conta ativa':'Consultar situação da conta'}}
  function renderWallet(){const total=state.accounts.reduce((sum,a)=>sum+Number(a.balance_minor||0),0);const walletTotal=$('#wallet-total');if(walletTotal)walletTotal.textContent=money(total);$('#header-balance').textContent=money(total);const accountLabel=type=>({CASH:'SALDO',BONUS:'BÔNUS',AFFILIATE:'AFILIADO'})[String(type).toUpperCase()]||String(type||'SALDO');const txLabel=type=>{const value=String(type||'').toUpperCase();if(value.includes('WITHDRAWAL'))return'SAQUE';if(value.includes('DEPOSIT'))return'DEPÓSITO';if(value.includes('BONUS'))return'BÔNUS';return value.replaceAll('_',' ')||'MOVIMENTAÇÃO'};const accounts=$('#wallet-accounts');if(accounts)accounts.innerHTML=state.accounts.length?state.accounts.map(a=>`<div class="account-row"><strong>${accountLabel(a.type)}</strong><b>${money(a.balance_minor,a.currency)}</b></div>`).join(''):'<div class="empty-state">Nenhuma conta encontrada.</div>';const transactions=$('#transaction-list');if(transactions)transactions.innerHTML=state.transactions.length?state.transactions.map(t=>`<div class="transaction-row profile-transaction"><div><strong class="${t.direction==='CREDIT'?'money-credit':'money-debit'}">${txLabel(t.type)} - ${money(t.amount_minor,t.currency)}</strong><small>${new Date(t.created_at).toLocaleString('pt-BR')}</small></div></div>`).join(''):'<div class="empty-state">Nenhuma movimentação.</div>'}
  async function loadWallet(){if(!state.token){state.accounts=[];state.transactions=[];renderWallet();return}try{const [w,t]=await Promise.all([api('/api/wallet'),api('/api/wallet/transactions?limit=20')]);state.accounts=w.accounts||[];state.transactions=t.transactions||[];renderWallet()}catch(e){if(/unauthorized/i.test(e.message))logout(false)}}
  async function restore(){if(!state.token){renderAuth();renderWallet();return}try{const r=await api('/api/me');state.user=r.user;renderAuth();await loadWallet()}catch{logout(false)}}
  async function logout(callApi=true){try{if(callApi&&state.token)await api('/api/auth/logout',{method:'POST'})}catch{}state.token='';state.user=null;state.accounts=[];state.transactions=[];localStorage.removeItem('igaming_token');renderAuth();renderWallet();toast('Sessão encerrada.')}
  $('#user-avatar').addEventListener('click',()=>state.user?section('profile'):openAuth('login'));$('#profile-nav').addEventListener('click',()=>state.user?section('profile'):openAuth('login'));$('#profile-logout').addEventListener('click',async()=>{await logout(true);section('home')});$('#refresh-wallet')?.addEventListener('click',loadWallet);
  async function loadPaymentGateways(){const r=await api('/api/payments/gateways');state.paymentGateways=r.gateways||[];$('#gateway-select').innerHTML=state.paymentGateways.map(g=>`<option value="${g.code}">${g.name}${g.sandbox?' • Sandbox':''}</option>`).join('')}
  const clearPaymentPoll=()=>{if(state.paymentPoll){clearInterval(state.paymentPoll);state.paymentPoll=null}};
  const closePayment=()=>{clearPaymentPoll();$('#payment-modal').classList.add('hidden')};
  const newIdempotencyKey=()=>crypto.randomUUID?crypto.randomUUID():`${Date.now()}-${Math.random().toString(16).slice(2)}`;
  function setGenerateLoading(loading){state.depositInFlight=loading;const btn=$('#generate-pix');if(!btn)return;btn.disabled=loading;btn.classList.toggle('is-loading',loading);$('.btn-label',btn).textContent=loading?'GERANDO PIX...':'GERAR PIX'}
  function renderPixQr(code){const host=$('#pix-qr');host.innerHTML='';if(!code){host.innerHTML='<small>QR Code indisponível</small>';return}if(typeof window.QRCode!=='function'){host.innerHTML='<small>Não foi possível carregar o gerador de QR Code. Use o PIX copia e cola.</small>';return}new QRCode(host,{text:code,width:160,height:160,colorDark:'#000000',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M})}
  function renderPayment(payment){state.activePayment=payment;$('#deposit-form').classList.add('hidden');$('#pix-result').classList.remove('hidden');const code=payment.payment_code||payment.payment_qr_code||'';$('#pix-code').value=code;renderPixQr(code);const paid=payment.status==='PAID';$('#pix-status').textContent=paid?'Pagamento confirmado':payment.status==='FAILED'?'Falha no pagamento':payment.status==='EXPIRED'?'PIX expirado':'Aguardando pagamento';const gw=state.paymentGateways.find(g=>g.code===payment.gateway_code);$('#sandbox-confirm').classList.toggle('hidden',!gw?.sandbox||paid);$('#sandbox-note').textContent=gw?.sandbox?'Ambiente local: o botão abaixo simula a confirmação que futuramente virá pelo webhook do gateway.':'';if(paid){clearPaymentPoll();loadWallet();toast('Pagamento confirmado e saldo atualizado.','success')}}
  function startPaymentPoll(){clearPaymentPoll();if(!state.activePayment||state.activePayment.status==='PAID')return;state.paymentPoll=setInterval(async()=>{if(!state.activePayment||$('#payment-modal').classList.contains('hidden')){clearPaymentPoll();return}try{const r=await api(`/api/payments/status?id=${encodeURIComponent(state.activePayment.id)}`);if(r.payment)renderPayment(r.payment)}catch{}},3000)}
  async function openDeposit(){if(!state.user){openAuth('login');return}try{await loadPaymentGateways();if(!state.paymentGateways.length){toast('Nenhum gateway de pagamento ativo.');return}clearPaymentPoll();state.activePayment=null;state.depositIdempotencyKey='';setGenerateLoading(false);$('#payment-alert').classList.add('hidden');$('#pix-result').classList.add('hidden');$('#deposit-form').classList.remove('hidden');$('#payment-modal').classList.remove('hidden')}catch(e){toast(e.message)}}
  $('#deposit-placeholder')?.addEventListener('click',openDeposit);$('#deposit-nav')?.addEventListener('click',openDeposit);$('#close-payment').addEventListener('click',closePayment);$('#payment-modal').addEventListener('click',e=>{if(e.target.id==='payment-modal')closePayment()});
  const paymentError=e=>{const a=$('#payment-alert');a.textContent=e.message;a.classList.remove('hidden')};
  $('#deposit-form').addEventListener('submit',async e=>{e.preventDefault();if(state.depositInFlight)return;const f=new FormData(e.currentTarget);const amount=Math.round(Number(f.get('amount'))*100);if(!Number.isFinite(amount)||amount<100){paymentError(new Error('Informe um valor válido.'));return}state.depositIdempotencyKey=state.depositIdempotencyKey||newIdempotencyKey();$('#payment-alert').classList.add('hidden');setGenerateLoading(true);try{const r=await api('/api/payments/deposits',{method:'POST',body:JSON.stringify({amount_minor:amount,gateway_code:f.get('gateway_code'),idempotency_key:state.depositIdempotencyKey})});renderPayment(r.payment);startPaymentPoll()}catch(err){paymentError(err)}finally{setGenerateLoading(false)}});
  $('#copy-pix').addEventListener('click',async()=>{const value=$('#pix-code').value;try{await navigator.clipboard.writeText(value);toast('PIX copiado.','success')}catch{toast('Não foi possível copiar automaticamente.')}});
  $('#sandbox-confirm').addEventListener('click',async()=>{if(!state.activePayment)return;try{const r=await api('/api/payments/sandbox/confirm',{method:'POST',body:JSON.stringify({payment_id:state.activePayment.id})});renderPayment(r.payment);$('#sandbox-confirm').classList.add('hidden');await loadWallet()}catch(e){paymentError(e)}});
  $('#withdraw-placeholder').addEventListener('click',()=>toast('Saques serão habilitados quando definirmos o primeiro gateway de payout.'));

  async function loadPlatformContent(){
    try{
      const data=await api('/api/platform/public');const settings=data.settings||{};
      setBrowserFavicon(settings.favicon_path);
      if(/^#[0-9a-fA-F]{6}$/.test(settings.accent_color||''))document.documentElement.style.setProperty('--platform-accent',settings.accent_color);
      if(settings.site_name){document.title=settings.site_name;document.querySelectorAll('.brand-text b,.auth-brand strong').forEach(el=>el.textContent=settings.site_name);}
      const site=settings.site_name||'MZ90';
      const logo=$('#footer-logo'),brand=$('#footer-brand-name');if(brand)brand.textContent=site;
      const logoPath=String(settings.logo_path||'');const validLogo=/^\/uploads\/identity\/[a-z0-9_-]+\.(?:png|jpg|webp)$/i.test(logoPath);
      if(logo){logo.classList.toggle('hidden',!validLogo);if(validLogo)logo.src=base+logoPath;else logo.removeAttribute('src')}
      if(brand)brand.classList.toggle('hidden',validLogo);
      const headerLogo=$('#header-site-logo');const lockup=$('.brand-lockup');
      if(headerLogo){headerLogo.classList.toggle('hidden',!validLogo);if(validLogo){headerLogo.src=base+logoPath;headerLogo.alt=site}else headerLogo.removeAttribute('src')}
      if(lockup)lockup.classList.toggle('has-site-logo',validLogo);
      const footerAbout=$('#footer-about');if(footerAbout)footerAbout.textContent=settings.footer_about||`Conheça a ${site}: entretenimento online com responsabilidade. Plataforma destinada a maiores de 18 anos.`;
      const footerCopy=$('#footer-copy');if(footerCopy){const copyright=document.createElement('span');copyright.textContent=`© ${new Date().getFullYear()} ${site}.`;const rights=document.createElement('span');rights.textContent='Todos os direitos reservados.';footerCopy.replaceChildren(copyright,rights)}
      const iconPaths={
        email:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        phone:'<path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 3.09 5.18 2 2 0 0 1 5.08 3h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.79a2 2 0 0 1-.45 2.11L9.1 10.9a16 16 0 0 0 4 4l1.28-1.25a2 2 0 0 1 2.11-.45c.89.35 1.83.59 2.79.72a2 2 0 0 1 1.72 2Z"/>',
        whatsapp:'<path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.6 8.6 0 0 1-4-.9L3 21l1.9-5.4a8.6 8.6 0 0 1-.9-4.1A8.5 8.5 0 0 1 12.5 3 8.5 8.5 0 0 1 21 11.5Z"/><path d="M9 9.3c.7 2.4 2.3 4 4.7 4.7l1.2-1.2 1.7.8-.3 1.8c-3.5.9-8.2-3.8-7.3-7.3l1.8-.3.8 1.7Z"/>',
        telegram:'<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13l-2 6"/>',
        instagram:'<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        facebook:'<path d="M15 21v-8h3l.5-4H15V7c0-1 .5-2 2.5-2H19V1.5A19 19 0 0 0 16 1c-4 0-6 2.5-6 6v2H7v4h3v8Z"/>'
      };
      const icon=key=>`<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${iconPaths[key]}</svg>`;
      const addLink=(host,label,href,key,showLabel=false)=>{const link=document.createElement('a');link.href=href;link.setAttribute('aria-label',label);link.title=label;link.innerHTML=icon(key)+(showLabel?`<span>${label}</span>`:'');if(/^https:\/\//.test(href)){link.target='_blank';link.rel='noopener noreferrer'}host.append(link)};
      const contact=$('#footer-contact');if(contact){contact.replaceChildren();if(settings.support_email)addLink(contact,'Atendimento por e-mail','mailto:'+settings.support_email,'email',true);if(settings.contact_phone)addLink(contact,'Atendimento por telefone','tel:'+settings.contact_phone.replace(/[^+0-9]/g,''),'phone',true);if(!contact.children.length){const text=document.createElement('small');text.textContent='Canais de atendimento em atualização.';contact.append(text)}}
      const social=$('#footer-socials');if(social){social.replaceChildren();for(const [key,label,iconKey] of [['social_whatsapp','WhatsApp','whatsapp'],['social_telegram','Telegram','telegram'],['social_instagram','Instagram','instagram'],['social_facebook','Facebook','facebook']]){const value=settings[key]||'';if(!/^https:\/\//i.test(value))continue;addLink(social,label,value,iconKey)}}
      const messages=(data.announcements||[]).map(item=>String(item.message||'').trim()).filter(Boolean);
      const track=$('#news-track'),windowEl=$('#news-window');
      if(track&&windowEl){
        track.replaceChildren();track.classList.remove('is-moving','news-single');track.onanimationend=null;
        if(!messages.length){const text=document.createElement('span');text.textContent='Novidades e lançamentos '+site;track.append(text)}
        else if(window.matchMedia('(prefers-reduced-motion: reduce)').matches){const text=document.createElement('span');text.textContent=messages[0];track.append(text)}
        else{
          let current=0;
          const showNext=()=>{track.classList.remove('news-single');track.replaceChildren();const text=document.createElement('span');text.textContent=messages[current];track.append(text);const start=windowEl.clientWidth;const end=-text.getBoundingClientRect().width;track.style.setProperty('--news-start',start+'px');track.style.setProperty('--news-end',end+'px');track.style.setProperty('--news-duration',Math.max(5,(start-end)/75).toFixed(2)+'s');void track.offsetWidth;track.classList.add('news-single');};
          track.onanimationend=event=>{if(event.target!==track)return;current=(current+1)%messages.length;showNext()};
          showNext();
        }
      }
      const banners=(data.banners||[]);
      const safeTarget=value=>/^\/(?:$|[a-z0-9/_-]+$)/i.test(value||'')?value:'/cassino';
      const makeBanner=(item,cls)=>{const a=document.createElement('a');a.className=cls;a.href=safeTarget(item.target_path);a.setAttribute('aria-label',item.title||'Abrir destaque');if(item.image_path){const img=document.createElement('img');img.src=item.image_path;img.alt=item.title||'';img.loading=cls==='slider-slide'?'eager':'lazy';a.append(img)}else{const caption=document.createElement('span');caption.textContent=item.title;a.append(caption)}return a};
      const slider=$('#managed-banners');slider.replaceChildren();
      const slides=banners.filter(b=>b.position==='home'&&Number(b.enabled)===1).sort((a,b)=>Number(a.sort_order)-Number(b.sort_order)||Number(a.id)-Number(b.id));
      if(window.mz90SliderCleanup)window.mz90SliderCleanup();
      if(slides.length){
        const track=document.createElement('div');track.className='slider-track';slides.forEach(b=>track.append(makeBanner(b,'slider-slide')));slider.append(track);let index=0;const dots=document.createElement('div');dots.className='slider-dots';const controls=[];const show=n=>{index=(n+slides.length)%slides.length;track.style.transform=`translateX(-${index*100}%)`;controls.forEach((b,i)=>{b.classList.toggle('active',i===index);b.setAttribute('aria-current',i===index?'true':'false')})};slides.forEach((b,i)=>{const dot=document.createElement('button');dot.type='button';dot.setAttribute('aria-label',`Ir para banner ${i+1}`);dot.onclick=()=>{show(i);restart()};dots.append(dot);controls.push(dot)});slider.append(dots);let timer=null;const stop=()=>{if(timer){clearInterval(timer);timer=null}};const restart=()=>{stop();if(slides.length>1&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches)timer=setInterval(()=>{if(!document.hidden)show(index+1)},5500)};if(slides.length>1){let touchX=null;slider.addEventListener('touchstart',e=>{touchX=e.changedTouches[0]?.clientX??null;stop()},{passive:true});slider.addEventListener('touchend',e=>{if(touchX!==null){const delta=(e.changedTouches[0]?.clientX??touchX)-touchX;if(Math.abs(delta)>45)show(index+(delta<0?1:-1))}touchX=null;restart()},{passive:true});slider.addEventListener('mouseenter',stop);slider.addEventListener('mouseleave',restart);slider.addEventListener('focusin',stop);slider.addEventListener('focusout',restart)}else dots.hidden=true;show(0);restart();window.mz90SliderCleanup=stop;
      }else{const empty=document.createElement('div');empty.className='slider-empty';empty.textContent='Nenhum banner principal publicado.';slider.append(empty)}
      const lobby=$('#home-lobby');lobby.replaceChildren();const lobbyItems=banners.filter(b=>b.position==='casino'&&Number(b.enabled)===1).sort((a,b)=>Number(a.sort_order)-Number(b.sort_order)||Number(a.id)-Number(b.id)).slice(0,3);lobbyItems.forEach((b,i)=>{const el=makeBanner(b,'lobby-banner lobby-slot-'+(i+1));lobby.append(el)});if(!lobbyItems.length)lobby.hidden=true;else lobby.hidden=false;
      $('#casino-managed-banners').replaceChildren();
      const promotions=$('#managed-promotions');promotions.replaceChildren();(data.promotions||[]).forEach(pr=>{const article=document.createElement('article');article.className='managed-promotion';if(pr.image_path){const image=document.createElement('img');image.src=pr.image_path;image.alt='';image.loading='lazy';article.append(image)}const content=document.createElement('div');const title=document.createElement('h2');title.textContent=pr.title;const description=document.createElement('p');description.textContent=pr.description;const start=pr.starts_at?new Date(String(pr.starts_at).replace(' ','T')):null;const upcoming=start&&!Number.isNaN(start.getTime())&&start.getTime()>Date.now();const status=document.createElement('span');status.className='promotion-availability '+(upcoming?'upcoming':'available');status.textContent=upcoming?'Em breve · disponível em '+new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(start):'Disponível';content.prepend(status);content.append(title,description);article.append(content);promotions.append(article);});
    }catch(error){console.warn('Conteúdo dinâmico indisponível:',error.message)}
  }
  loadPlatformContent();
  loadCatalog();
  restore();
})();
