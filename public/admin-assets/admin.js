const $=(s,r=document)=>r.querySelector(s); const $$=(s,r=document)=>[...r.querySelectorAll(s)];
const tokenKey='igaming_admin_token'; let token=localStorage.getItem(tokenKey)||''; let usersPage=1; let usersPages=1; let currentUserId=''; let financePage=1; let financePages=1;
async function api(path,options={}){const headers={'Content-Type':'application/json',...(options.headers||{})};if(token)headers.Authorization=`Bearer ${token}`;const r=await fetch(path,{...options,headers});const data=await r.json().catch(()=>({}));if(!r.ok)throw new Error(data.message||data.error||`HTTP ${r.status}`);return data}

function setAdminBrowserFavicon(path){const link=document.getElementById('browser-favicon')||document.createElement('link');link.id='browser-favicon';link.rel='icon';if(!link.parentNode)document.head.append(link);const value=String(path||'').trim();if(!/^\/uploads\/identity\/[a-z0-9_-]+\.(?:png|jpg|webp)$/i.test(value)){link.href='data:,';return}const ext=value.split('.').pop().toLowerCase();link.type=ext==='png'?'image/png':ext==='webp'?'image/webp':'image/jpeg';link.href=value}
async function syncPublicFavicon(){try{const response=await fetch('/api/platform/public',{headers:{Accept:'application/json'}});if(!response.ok)return;const data=await response.json();setAdminBrowserFavicon(data.settings?.favicon_path)}catch{}}
syncPublicFavicon();
function alertBox(id,msg,ok=false){const e=$(id);e.textContent=msg;e.classList.remove('hidden','ok');if(ok)e.classList.add('ok');if(!ok){const overlay=document.querySelector('.admin-editor-overlay:not(.hidden)');if(overlay){const err=overlay.querySelector('.admin-editor-error');err.textContent=msg;err.classList.remove('hidden')}}}
function money(minor){return new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format((Number(minor)||0)/100)}
function shortId(id){return String(id||'').split('-')[0]||'—'}
function formatCpf(v){const d=String(v||'').replace(/\D/g,'');return d.length===11?`${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}`:(v||'—')}
function formatPhone(v){const d=String(v||'').replace(/\D/g,'');if(d.length===11)return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;if(d.length===10)return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;return v||'—'}

function dateTime(v){if(!v)return '—';const d=new Date(String(v).replace(' ','T'));return Number.isNaN(d.getTime())?v:new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'medium'}).format(d)}
// Edição em modais: os dados continuam sendo enviados às APIs existentes.
let editorReturnFocus=null;
function openEditor(formId,editing=false){
 const overlay=document.getElementById('editor-'+formId);if(!overlay)return;
 editorReturnFocus=document.activeElement;
 const label={'casino-provider-form':'provedor','casino-category-form':'categoria','casino-game-form':'jogo','banners-form':'banner','promotions-form':'promoção','affiliates-form':'link de indicação','announcements-form':'novidade'}[formId]||'registro';
 overlay.querySelector('h2').textContent=(editing?'Editar ':'Novo ')+label;
 overlay.querySelector('.admin-editor-error').classList.add('hidden');
 if(overlay._closeTimer){clearTimeout(overlay._closeTimer);overlay._closeTimer=null;}
 overlay.classList.remove('hidden','is-closing');document.body.classList.add('admin-editor-open');
 overlay.querySelector('input:not([type=hidden]),select,textarea')?.focus();
}
function closeEditor(formId){
 const overlay=document.getElementById('editor-'+formId);if(!overlay)return;
 if(overlay.classList.contains('hidden')||overlay.classList.contains('is-closing'))return;
 overlay.classList.add('is-closing');
 const focusTarget=editorReturnFocus;
 overlay._closeTimer=setTimeout(()=>{
  overlay.classList.add('hidden');overlay.classList.remove('is-closing');overlay._closeTimer=null;
  if(!document.querySelector('.admin-editor-overlay:not(.hidden)'))document.body.classList.remove('admin-editor-open');
  focusTarget?.focus?.();
 },window.matchMedia('(prefers-reduced-motion: reduce)').matches?0:220);
}
for(const overlay of document.querySelectorAll('.admin-editor-overlay[id^="editor-"]')){
 const id=overlay.id.slice('editor-'.length);
 overlay.querySelector('.admin-editor-close').addEventListener('click',()=>closeEditor(id));
 overlay.querySelector('.modal-cancel').addEventListener('click',()=>closeEditor(id));
 overlay.addEventListener('click',e=>{if(e.target===overlay)closeEditor(id)});
}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){const overlay=document.querySelector('.admin-editor-overlay[id^="editor-"]:not(.hidden)');if(overlay)closeEditor(overlay.id.slice('editor-'.length));}});
function newEditor(formId){const f=document.getElementById(formId);f.reset();f.elements.id.value='';if(formId==='casino-provider-form'){f.elements.code.readOnly=false;f.elements.logo_path.value='';f.elements.logo_file.value='';$('#provider-logo-preview').classList.add('hidden')}if(formId==='casino-category-form'){f.elements.code.readOnly=false;f.elements.sort_order.value='100';f.elements.enabled.checked=true}if(formId==='casino-game-form' && f.elements.access_count)f.elements.access_count.value='250';if(formId==='banners-form'){f.elements.image_path.value='';f.elements.position.value=document.querySelector('[data-banner-filter].active')?.dataset.bannerFilter||'home'}openEditor(formId,false)}
document.getElementById('new-provider').addEventListener('click',()=>newEditor('casino-provider-form'));
document.getElementById('new-category').addEventListener('click',()=>newEditor('casino-category-form'));
document.getElementById('new-game').addEventListener('click',()=>newEditor('casino-game-form'));
document.getElementById('new-promotion').addEventListener('click',()=>newEditor('promotions-form'));
document.getElementById('new-affiliate').addEventListener('click',()=>newEditor('affiliates-form'));
document.getElementById('new-announcement').addEventListener('click',()=>newEditor('announcements-form'));
async function boot(){
 if(!token)return showLogin();
 try{
  const d=await api('/admin/api/me');
  $('#admin-name').textContent=d.admin.name;
  $('#admin-login').classList.add('hidden');
  $('#admin-shell').classList.remove('login-mode');
  $('#admin-sidebar').classList.remove('hidden');
  $('#admin-app').classList.remove('hidden');
  $('#admin-logout').classList.remove('hidden');
  await openPage('dashboard');
 }catch(e){token='';localStorage.removeItem(tokenKey);showLogin()}
}
function showLogin(){
 $('#admin-app').classList.add('hidden');
 $('#admin-sidebar').classList.add('hidden');
 $('#admin-sidebar').classList.remove('open');
 $('#admin-logout').classList.add('hidden');
 $('#admin-shell').classList.add('login-mode');
 $('#admin-login').classList.remove('hidden');
}
$('#admin-login-form').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);try{const d=await api('/admin/api/login',{method:'POST',body:JSON.stringify(Object.fromEntries(f))});token=d.token;localStorage.setItem(tokenKey,token);await boot()}catch(err){alertBox('#login-alert',err.message)}})
$('#admin-logout').addEventListener('click',async()=>{try{await api('/admin/api/logout',{method:'POST',body:'{}'})}catch{}token='';localStorage.removeItem(tokenKey);location.reload()});
$('#menu-toggle').addEventListener('click',()=>$('#admin-sidebar').classList.toggle('open'));
$$('.nav-item[data-page]:not(.promotion-admin-parent)').forEach(b=>b.addEventListener('click',()=>openPage(b.dataset.page)));
async function openPage(page){$$('.admin-page').forEach(p=>p.classList.add('hidden'));$$('.nav-item').forEach(n=>n.classList.remove('nav-active'));$(`#page-${page}`).classList.remove('hidden');$(`.nav-item[data-page="${page}"]`)?.classList.add('nav-active');$('#admin-sidebar').classList.remove('open');if(page==='dashboard'){setHeading('VISÃO GERAL','Dashboard','Resumo operacional da plataforma.');await loadDashboard()}else if(page==='users'){setHeading('GESTÃO','Usuários','Cadastros, saldos, atividade e situação das contas.');await loadUsers()}else if(page==='finance'){setHeading('GESTÃO','Financeiro','Depósitos, conciliação e eventos dos gateways.');await loadFinance()}else if(page==='casino'){setHeading('PLATAFORMA','Gerenciamento de Jogos API','Visualize e gerencie os jogos cadastrados por provedor e API.');await loadCasino()}else if(page==='gateways'){setHeading('PAGAMENTOS','Gateways','Ative provedores, defina prioridades e separe depósito de saque.');await loadGateways()}else if(['settings','appearance','promotions','affiliates'].includes(page)){setHeading('PLATAFORMA',({settings:'Configurações',appearance:'Aparência',promotions:'Promoções',affiliates:'Afiliados'})[page],'Gerencie conteúdo e configurações.');await loadPlatform()}else if(page==='audit'){setHeading('SISTEMA','Auditoria','Histórico de ações.');await loadAudit()}}
function setHeading(k,t,d){$('#page-kicker').textContent=k;$('#page-title').textContent=t;$('#page-description').textContent=d}
$('#reconcile-refresh').addEventListener('click',loadReconciliation);$('#finance-filter').addEventListener('click',()=>{financePage=1;loadFinance()});$('#finance-refresh').addEventListener('click',loadFinance);$('#finance-search').addEventListener('keydown',e=>{if(e.key==='Enter'){financePage=1;loadFinance()}});$('#finance-status').addEventListener('change',()=>{financePage=1;loadFinance()});$('#finance-gateway').addEventListener('change',()=>{financePage=1;loadFinance()});$('#finance-prev').addEventListener('click',()=>{if(financePage>1){financePage--;loadFinance()}});$('#finance-next').addEventListener('click',()=>{if(financePage<financePages){financePage++;loadFinance()}});$('#close-deposit-modal').addEventListener('click',closeDepositModal);$('#deposit-modal').addEventListener('click',e=>{if(e.target.id==='deposit-modal')closeDepositModal()});$('#copy-dep-pix').addEventListener('click',async()=>{const v=$('#dep-pix').value;if(!v)return;try{await navigator.clipboard.writeText(v);$('#copy-dep-pix').textContent='COPIADO';setTimeout(()=>$('#copy-dep-pix').textContent='COPIAR PIX',1200)}catch{}});
$('#refresh-dashboard').addEventListener('click',loadDashboard);$('#refresh-gateways').addEventListener('click',loadGateways);$('#refresh-users').addEventListener('click',()=>loadUsers());$('#search-users').addEventListener('click',()=>{usersPage=1;loadUsers()});$('#users-search').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();usersPage=1;loadUsers()}});$('#users-status').addEventListener('change',()=>{usersPage=1;loadUsers()});$('#users-prev').addEventListener('click',()=>{if(usersPage>1){usersPage--;loadUsers()}});$('#users-next').addEventListener('click',()=>{if(usersPage<usersPages){usersPage++;loadUsers()}});$('#close-user-modal').addEventListener('click',closeUserModal);$('#user-modal').addEventListener('click',e=>{if(e.target.id==='user-modal')closeUserModal()});$$('.user-tab').forEach(b=>b.addEventListener('click',()=>openUserTab(b.dataset.userTab)));$('#toggle-user-block').addEventListener('click',toggleUserBlock);$('#open-balance-adjust').addEventListener('click',()=>$('#balance-adjust-modal').classList.remove('hidden'));$('#close-balance-adjust').addEventListener('click',()=>$('#balance-adjust-modal').classList.add('hidden'));$('#save-balance-adjust').addEventListener('click',saveBalanceAdjustment);$('#save-user-profile').addEventListener('click',saveUserProfile);$('#reset-user-password').addEventListener('click',resetUserPassword);
async function loadDashboard(){try{const d=await api('/admin/api/dashboard');renderMetrics(d.metrics);renderChart('#withdrawals-chart',d.daily.withdrawals,'withdrawal');renderChart('#deposits-chart',d.daily.deposits,'deposit');renderTable('#latest-withdrawals',d.latest.withdrawals);renderTable('#latest-deposits',d.latest.deposits);$('#dashboard-updated').textContent='Atualizado agora'}catch(e){alertBox('#dashboard-alert',e.message)}}
function renderMetrics(m){$('#m-users-online').textContent=m.users_online;$('#m-users-total').textContent=m.registrations_total;$('#m-users-today').textContent=m.registrations_today;$('#m-users-90d').textContent=m.registrations_90d;$('#m-balances').textContent=money(m.user_balances_minor);$('#m-profit').textContent=money(m.profit_minor);$('#m-profit-note').classList.toggle('hidden',!!m.profit_available);$('#m-deposit-no-link').textContent=money(m.deposits_no_link_minor);$('#m-deposit-bloggers').textContent=money(m.deposits_bloggers_minor);$('#m-withdraw-bloggers').textContent=money(m.withdrawals_bloggers_minor);$('#m-withdraw-no-link').textContent=money(m.withdrawals_no_link_minor);$('#m-accesses').textContent=m.accesses_total;$('#m-location').textContent=m.top_location||'Sem dados de localização'}
function renderChart(selector,rows,type){const host=$(selector);host.innerHTML='';if(!rows?.length){host.innerHTML='<div class="empty-chart">Nenhum movimento aprovado no período.</div>';return}const max=Math.max(...rows.map(r=>Number(r.total)||0),1);rows.forEach(r=>{const c=document.createElement('div');c.className='bar-column';const h=Math.max(3,Math.round(((Number(r.total)||0)/max)*220));c.innerHTML=`<span class="bar-value">${r.total}</span><div class="bar ${type==='withdrawal'?'withdrawal':''}" style="height:${h}px" title="${r.total} transação(ões) • ${money(r.amount_minor)}"></div><span class="bar-label">${r.day.slice(5).split('-').reverse().join('/')}</span>`;host.append(c)})}
function renderTable(selector,rows){const body=$(selector);body.innerHTML='';if(!rows?.length){body.innerHTML='<tr class="empty-row"><td colspan="4">Nenhum registro encontrado.</td></tr>';return}rows.forEach(r=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${shortId(r.id)}</td><td>${escapeHtml(r.user)}</td><td>${dateTime(r.created_at)}</td><td>${money(r.amount_minor)}</td>`;body.append(tr)})}
function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML}
async function loadUsers(){
    try{
        const search=encodeURIComponent($('#users-search').value.trim());
        const status=encodeURIComponent($('#users-status').value);
        const d=await api(`/admin/api/users?page=${usersPage}&limit=25&search=${search}&status=${status}`);
        usersPage=d.pagination.page;usersPages=d.pagination.pages;
        $('#users-count').textContent=`${d.pagination.total} usuário(s)`;
        $('#users-page').textContent=`Página ${usersPage} de ${usersPages}`;
        $('#users-prev').disabled=usersPage<=1;$('#users-next').disabled=usersPage>=usersPages;
        renderUsers(d.users);
    }catch(e){alertBox('#users-alert',e.message)}
}
function renderUsers(rows){
    const body=$('#users-table-body');body.innerHTML='';
    if(!rows?.length){body.innerHTML='<tr class="empty-row"><td colspan="10">Nenhum usuário encontrado.</td></tr>';return}
    rows.forEach(u=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`<td><strong class="public-id">#${u.public_id}</strong></td><td><div class="user-cell"><strong>${escapeHtml(u.username)}</strong><small>${escapeHtml(u.email)}</small></div></td><td><div class="user-cell"><strong>${escapeHtml(formatCpf(u.cpf))}</strong><small>${escapeHtml(formatPhone(u.phone))}</small></div></td><td>${statusBadge(u.status)}</td><td>${money(u.cash_minor)}</td><td>${money(u.bonus_minor)}</td><td><strong>${money(u.deposited_minor)}</strong><small class="subvalue">${u.deposit_count} depósito(s)</small></td><td>${u.last_seen_at?dateTime(u.last_seen_at):'<span class="muted">Nunca</span>'}</td><td>${dateTime(u.created_at)}</td><td><button class="row-action" data-user-id="${u.public_id}">Ver</button></td>`;
        $('.row-action',tr).addEventListener('click',()=>openUserDetail(String(u.public_id)));body.append(tr);
    })
}
function statusBadge(status){const labels={ACTIVE:'Ativo',SUSPENDED:'Suspenso',BLOCKED:'Bloqueado',PAID:'Pago',PENDING:'Pendente',PROCESSING:'Processando',FAILED:'Falhou',CANCELLED:'Cancelado',EXPIRED:'Expirado',REVIEW:'Revisão',COMPLETED:'Concluído'};return `<span class="badge badge-${String(status).toLowerCase()}">${labels[status]||escapeHtml(status)}</span>`}
async function openUserDetail(id){
    currentUserId=id;$('#user-modal').classList.remove('hidden');document.body.classList.add('modal-open');openUserTab('info');
    $('#user-detail-title').textContent='Carregando...';$('#user-detail-email').textContent='';
    try{const d=await api(`/admin/api/users/detail?id=${encodeURIComponent(id)}`);renderUserDetail(d)}catch(e){alertBox('#user-detail-alert',e.message)}
}
function closeUserModal(){currentUserId='';$('#user-modal').classList.add('hidden');$('#balance-adjust-modal').classList.add('hidden');document.body.classList.remove('modal-open')}
function openUserTab(name){$$('.user-tab').forEach(b=>b.classList.toggle('active',b.dataset.userTab===name));$$('.user-tab-panel').forEach(p=>p.classList.toggle('hidden',p.dataset.userPanel!==name))}
function paymentTotal(summary,kind){return summary.filter(x=>x.kind===kind&&x.status==='PAID').reduce((a,x)=>a+Number(x.amount_minor||0),0)}
function renderUserDetail(d){
    const u=d.user;currentUserId=String(u.public_id);$('#user-detail-title').textContent=u.username;$('#user-detail-email').textContent=u.email;$('#user-detail-contact').textContent=`CPF ${formatCpf(u.cpf)} • ${formatPhone(u.phone)}`;$('#ud-public-id').textContent=`#${u.public_id}`;$('#ud-status').value=u.status;$('#ud-edit-username').value=u.username;$('#ud-edit-email').value=u.email;$('#ud-edit-cpf').value=u.cpf?formatCpf(u.cpf):'';$('#ud-edit-phone').value=u.phone?formatPhone(u.phone):'';$('#ud-avatar').textContent=(u.username||'U').slice(0,1).toUpperCase();
    const balance=t=>d.balances.find(b=>b.type===t)?.balance_minor||0;const cash=balance('CASH'),bonus=balance('BONUS');const deposits=paymentTotal(d.payment_summary,'DEPOSIT'),withdrawals=paymentTotal(d.payment_summary,'WITHDRAWAL');
    $('#ud-cash').textContent=money(cash);$('#ud-bonus').textContent=money(bonus);$('#ud-deposits').textContent=money(deposits);$('#ud-withdrawals').textContent=money(withdrawals);$('#ud-cash-card').textContent=money(cash);$('#ud-deposits-card').textContent=money(deposits);$('#ud-withdrawals-card').textContent=money(withdrawals);$('#ud-sessions').textContent=d.sessions.filter(s=>s.active).length;
    $('#toggle-user-block').textContent=u.status==='BLOCKED'?'DESBLOQUEAR':'BLOQUEAR';$('#toggle-user-block').dataset.status=u.status;
    renderUserLedger(d.ledger);renderUserSessions(d.sessions);renderPayoutAccounts(d.payout_accounts);renderPaymentList('#ud-deposit-list',d.payments.filter(x=>x.kind==='DEPOSIT'),'Nenhum depósito disponível.');renderPaymentList('#ud-withdrawal-list',d.payments.filter(x=>x.kind==='WITHDRAWAL'),'Nenhum saque disponível.');
}
function renderPaymentList(selector,rows,empty){const body=$(selector);body.innerHTML='';if(!rows?.length){body.innerHTML=`<tr class="empty-row"><td colspan="5">${empty}</td></tr>`;return}rows.forEach(r=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${shortId(r.id)}</td><td>${escapeHtml(r.gateway_code)}</td><td>${money(r.amount_minor)}</td><td>${dateTime(r.created_at)}</td><td>${statusBadge(r.status)}</td>`;body.append(tr)})}
function renderPayoutAccounts(rows){const body=$('#ud-payout-accounts');body.innerHTML='';if(!rows?.length){body.innerHTML='<tr class="empty-row"><td colspan="5">Nenhuma conta de recebimento cadastrada.</td></tr>';return}rows.forEach(r=>{const tr=document.createElement('tr');tr.innerHTML=`<td>#${r.id}</td><td>${escapeHtml(r.name)}</td><td>${escapeHtml(r.type)} / ${escapeHtml(r.key_type)}</td><td>${escapeHtml(r.key_value)}</td><td>${dateTime(r.created_at)}</td>`;body.append(tr)})}
function renderUserLedger(rows){const body=$('#ud-ledger');body.innerHTML='';if(!rows?.length){body.innerHTML='<tr class="empty-row"><td colspan="5">Sem movimentações.</td></tr>';return}rows.forEach(r=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${escapeHtml(r.account_type)}</td><td><span class="movement ${r.direction==='CREDIT'?'credit':'debit'}">${r.direction==='CREDIT'?'+ Crédito':'− Débito'}</span></td><td>${money(r.amount_minor)}</td><td>${money(r.balance_after_minor)}</td><td>${dateTime(r.created_at)}</td>`;body.append(tr)})}
function renderUserSessions(rows){const body=$('#ud-session-list');body.innerHTML='';if(!rows?.length){body.innerHTML='<tr class="empty-row"><td colspan="5">Sem sessões.</td></tr>';return}rows.forEach(r=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${shortId(r.id)}</td><td>${r.active?'<span class="badge badge-active">Ativa</span>':'<span class="badge badge-blocked">Encerrada</span>'}</td><td>${dateTime(r.created_at)}</td><td>${r.last_used_at?dateTime(r.last_used_at):'—'}</td><td>${dateTime(r.expires_at)}</td>`;body.append(tr)})}
async function refreshCurrentUser(){const d=await api(`/admin/api/users/detail?id=${encodeURIComponent(currentUserId)}`);renderUserDetail(d);return d}
async function toggleUserBlock(){if(!currentUserId)return;const btn=$('#toggle-user-block');const current=btn.dataset.status;const status=current==='BLOCKED'?'ACTIVE':'BLOCKED';if(status==='BLOCKED'&&!confirm('Bloquear este usuário e encerrar todas as sessões ativas?'))return;btn.disabled=true;try{await api('/admin/api/users/status',{method:'POST',body:JSON.stringify({id:currentUserId,status})});alertBox('#user-detail-alert',status==='BLOCKED'?'Usuário bloqueado.':'Usuário desbloqueado.',true);await refreshCurrentUser();await loadUsers()}catch(e){alertBox('#user-detail-alert',e.message)}finally{btn.disabled=false}}
async function saveUserProfile(){if(!currentUserId)return;const btn=$('#save-user-profile');btn.disabled=true;btn.textContent='SALVANDO...';try{await api('/admin/api/users/profile',{method:'POST',body:JSON.stringify({id:currentUserId,username:$('#ud-edit-username').value,email:$('#ud-edit-email').value,cpf:$('#ud-edit-cpf').value,phone:$('#ud-edit-phone').value,status:$('#ud-status').value})});alertBox('#user-detail-alert','Usuário atualizado com sucesso.',true);await refreshCurrentUser();await loadUsers()}catch(e){alertBox('#user-detail-alert',e.message)}finally{btn.disabled=false;btn.textContent='ATUALIZAR USUÁRIO'}}
async function resetUserPassword(){if(!currentUserId)return;const password=$('#ud-new-password').value;if(password.length<8){alertBox('#user-detail-alert','A nova senha precisa ter pelo menos 8 caracteres.');return}if(!confirm('Redefinir a senha e encerrar as sessões atuais deste usuário?'))return;const btn=$('#reset-user-password');btn.disabled=true;try{await api('/admin/api/users/password',{method:'POST',body:JSON.stringify({id:currentUserId,password})});$('#ud-new-password').value='';alertBox('#user-detail-alert','Senha redefinida e sessões encerradas.',true);await refreshCurrentUser()}catch(e){alertBox('#user-detail-alert',e.message)}finally{btn.disabled=false}}
async function saveBalanceAdjustment(){if(!currentUserId)return;const amount=Number($('#adjust-amount').value);const payload={id:currentUserId,account_type:$('#adjust-account').value,direction:$('#adjust-direction').value,amount_minor:Math.round(amount*100),reason:$('#adjust-reason').value.trim()};if(!amount||amount<=0){alertBox('#user-detail-alert','Informe um valor válido.');return}if(payload.reason.length<3){alertBox('#user-detail-alert','Informe o motivo do ajuste.');return}if(!confirm(`${payload.direction==='CREDIT'?'Creditar':'Debitar'} ${money(payload.amount_minor)} na conta ${payload.account_type}?`))return;const btn=$('#save-balance-adjust');btn.disabled=true;try{await api('/admin/api/users/balance-adjustment',{method:'POST',body:JSON.stringify(payload)});$('#balance-adjust-modal').classList.add('hidden');$('#adjust-amount').value='';$('#adjust-reason').value='';alertBox('#user-detail-alert','Ajuste registrado no ledger com sucesso.',true);await refreshCurrentUser();await loadUsers()}catch(e){alertBox('#user-detail-alert',e.message)}finally{btn.disabled=false}}

async function loadFinance(){
    const qs=new URLSearchParams({page:String(financePage),limit:'25'});const fields={search:'#finance-search',status:'#finance-status',gateway:'#finance-gateway',from:'#finance-from',to:'#finance-to'};Object.entries(fields).forEach(([k,sel])=>{const v=$(sel).value.trim();if(v)qs.set(k,v)});
    try{const d=await api('/admin/api/finance/deposits?'+qs.toString());financePage=d.pagination.page;financePages=d.pagination.pages;$('#finance-count').textContent=`${d.pagination.total} depósito(s)`;$('#finance-page').textContent=`Página ${financePage} de ${financePages}`;$('#finance-prev').disabled=financePage<=1;$('#finance-next').disabled=financePage>=financePages;$('#fin-paid').textContent=money(d.metrics.paid_minor);$('#fin-paid-count').textContent=`${d.metrics.paid_count} depósito(s)`;$('#fin-pending').textContent=money(d.metrics.pending_minor);$('#fin-pending-count').textContent=`${d.metrics.pending_count} depósito(s)`;$('#fin-failed').textContent=d.metrics.failed_count;$('#fin-expired').textContent=d.metrics.expired_count;renderFinanceGateways(d.gateways);renderFinanceRows(d.items)}catch(e){alertBox('#finance-alert',e.message)}
}
async function loadReconciliation(){const b=$('#reconcile-refresh');b.disabled=true;b.textContent='VERIFICANDO...';try{const d=await api('/admin/api/finance/reconciliation');$('#reconcile-note').textContent=`${d.note} Webhooks sem processamento (>5 min): ${d.unprocessed_webhooks}. ${d.items.length} ocorrências (máximo 100).`;const body=$('#reconcile-body');body.innerHTML='';if(!d.items.length)body.innerHTML='<tr><td colspan="6">Nenhuma divergência detectada pelas regras locais.</td></tr>';d.items.forEach(r=>{const tr=document.createElement('tr');tr.innerHTML=`<td><code>${escapeHtml(shortId(r.id))}</code></td><td>#${r.public_id}</td><td>${statusBadge(r.status)}</td><td>${money(r.amount_minor)}</td><td>${escapeHtml(r.reason)}</td><td><button class="row-action">Ver</button></td>`;$('button',tr).addEventListener('click',()=>openDeposit(r.id));body.append(tr)})}catch(e){alertBox('#finance-alert',e.message)}finally{b.disabled=false;b.textContent='VERIFICAR DIVERGÊNCIAS'}}
function renderFinanceGateways(rows){const sel=$('#finance-gateway'),current=sel.value;if(sel.options.length<=1){rows.forEach(g=>{const o=document.createElement('option');o.value=g.code;o.textContent=g.name;sel.append(o)})}sel.value=current}
function financeUser(u){const main=u.username||u.email||formatPhone(u.phone)||formatCpf(u.cpf)||`Usuário #${u.public_id}`;return `<div class="user-cell"><strong>#${u.public_id} · ${escapeHtml(main)}</strong><small>${escapeHtml(formatCpf(u.cpf))} • ${escapeHtml(formatPhone(u.phone))}</small></div>`}
function renderFinanceRows(rows){const body=$('#finance-body');body.innerHTML='';if(!rows?.length){body.innerHTML='<tr class="empty-row"><td colspan="8">Nenhum depósito encontrado.</td></tr>';return}rows.forEach(r=>{const tr=document.createElement('tr');tr.innerHTML=`<td><code>${shortId(r.id)}</code></td><td>${financeUser(r.user)}</td><td>${escapeHtml(r.gateway_code)}</td><td><strong>${money(r.amount_minor)}</strong></td><td>${statusBadge(r.status)}</td><td><span class="external-id">${escapeHtml(r.external_id||'—')}</span></td><td>${dateTime(r.created_at)}</td><td><button class="row-action">Ver</button></td>`;$('button',tr).addEventListener('click',()=>openDeposit(r.id));body.append(tr)})}
async function openDeposit(id){try{const d=await api('/admin/api/finance/deposits/detail?id='+encodeURIComponent(id));renderDeposit(d);$('#deposit-modal').classList.remove('hidden');document.body.classList.add('modal-open')}catch(e){alertBox('#finance-alert',e.message)}}
function closeDepositModal(){$('#deposit-modal').classList.add('hidden');document.body.classList.remove('modal-open')}
function renderDeposit(d){const p=d.payment,u=p.user;$('#dep-title').textContent=`Depósito ${shortId(p.id)}`;$('#dep-subtitle').textContent=`Criado em ${dateTime(p.created_at)}`;$('#dep-amount').textContent=money(p.amount_minor);$('#dep-status').innerHTML=statusBadge(p.status);$('#dep-user').textContent=`#${u.public_id}`;$('#dep-gateway').textContent=p.gateway_code;$('#dep-pix').value=p.payment_code||p.payment_qr_code||'';$('#copy-dep-pix').disabled=!$('#dep-pix').value;const fields=[['ID interno',p.id],['External ID',p.external_id||'—'],['Usuário',`#${u.public_id}`],['CPF',formatCpf(u.cpf)],['Telefone',formatPhone(u.phone)],['E-mail',u.email||'—'],['Idempotência',p.idempotency_key],['Criado',dateTime(p.created_at)],['Atualizado',dateTime(p.updated_at)],['Expira',dateTime(p.expires_at)]];$('#dep-fields').innerHTML=fields.map(([k,v])=>`<div><span>${k}</span><strong>${escapeHtml(String(v))}</strong></div>`).join('');const body=$('#dep-webhooks');body.innerHTML='';if(!d.webhooks?.length){body.innerHTML='<tr class="empty-row"><td colspan="5">Nenhum webhook relacionado encontrado.</td></tr>';return}d.webhooks.forEach(e=>{const tr=document.createElement('tr');tr.innerHTML=`<td>#${e.id}</td><td>${escapeHtml(e.event_type||'—')}</td><td>${escapeHtml(e.external_id||'—')}</td><td>${dateTime(e.created_at)}</td><td>${e.processed_at?dateTime(e.processed_at):'<span class="badge badge-pending">Pendente</span>'}</td>`;body.append(tr)})}

async function loadGateways(){const d=await api('/admin/api/gateways');const host=$('#gateway-list');host.innerHTML='';d.gateways.forEach(g=>host.append(renderGateway(g)))}
function minorToMoney(v){return v==null?'':(Number(v)/100).toFixed(2)}function moneyToMinor(v){if(v===''||v==null)return null;return Math.round(Number(v)*100)}
function renderGateway(g){const node=$('#gateway-template').content.firstElementChild.cloneNode(true);node.dataset.code=g.code;node.dataset.enabled=g.enabled?'1':'0';$('h2',node).textContent=g.name;$('.gateway-code',node).textContent=g.code;$('[name=name]',node).value=g.name;$('[name=enabled]',node).checked=!!Number(g.enabled);$('[name=deposit_enabled]',node).checked=!!Number(g.deposit_enabled);$('[name=withdrawal_enabled]',node).checked=!!Number(g.withdrawal_enabled);$('[name=mode]',node).value=g.mode;$('[name=priority_deposit]',node).value=g.priority_deposit;$('[name=priority_withdrawal]',node).value=g.priority_withdrawal;$('[name=min_deposit]',node).value=minorToMoney(g.min_deposit_minor);$('[name=max_deposit]',node).value=minorToMoney(g.max_deposit_minor);$('[name=min_withdrawal]',node).value=minorToMoney(g.min_withdrawal_minor);$('[name=max_withdrawal]',node).value=minorToMoney(g.max_withdrawal_minor);$('.credential-state',node).textContent=g.credentials_configured?'Credenciais configuradas':'Sem credenciais';if(g.code==='pixup'){const b=$('.pixup-fields',node);b.classList.remove('hidden');$('[name=base_url]',node).value=g.settings?.base_url||'https://api.pixupbr.com';$('[name=webhook_url]',node).value=g.settings?.webhook_url||'';$('[name=verify_webhook_signature]',node).checked=!!g.settings?.verify_webhook_signature}$('.save-gateway',node).addEventListener('click',()=>saveGateway(node,g.code));return node}
async function saveGateway(node,code){const q=n=>$(`[name=${n}]`,node);const payload={code,name:q('name').value,enabled:q('enabled').checked,deposit_enabled:q('deposit_enabled').checked,withdrawal_enabled:q('withdrawal_enabled').checked,mode:q('mode').value,priority_deposit:Number(q('priority_deposit').value||100),priority_withdrawal:Number(q('priority_withdrawal').value||100),min_deposit_minor:moneyToMinor(q('min_deposit').value)||0,max_deposit_minor:moneyToMinor(q('max_deposit').value),min_withdrawal_minor:moneyToMinor(q('min_withdrawal').value)||0,max_withdrawal_minor:moneyToMinor(q('max_withdrawal').value),credentials:{},settings:{}};if(code==='pixup'){payload.credentials={client_id:q('client_id').value,client_secret:q('client_secret').value,webhook_secret:q('webhook_secret').value};payload.settings={base_url:q('base_url').value,webhook_url:q('webhook_url').value,verify_webhook_signature:q('verify_webhook_signature').checked}}try{await api('/admin/api/gateways/save',{method:'POST',body:JSON.stringify(payload)});alertBox('#save-alert',`Gateway ${code} salvo.`,true);await loadGateways()}catch(e){alertBox('#save-alert',e.message)}}
boot();

let casinoCatalog={providers:[],categories:[],games:[]};
let casinoGamePage=1;
const casinoApiLabel=source=>source==='PLAYFIVER'?'PlayFiver':'Catálogo local';
function casinoGameForm(v){
 const f=$('#casino-game-form');
 for(const key of ['id','provider_id','external_id','name','category','image_url','sort_order','access_count'])f.elements[key].value=v[key]??'';
 f.elements.access_count.value=v.access_count??250;
 f.elements.enabled.checked=!!Number(v.enabled);
 f.elements.featured.checked=!!Number(v.featured);
 openEditor('casino-game-form',true);
}
function casinoGameOptions(){
 const api=$('#casino-game-api-filter'),provider=$('#casino-game-provider-filter');
 const previousApi=api.value,previousProvider=provider.value;
 api.replaceChildren(new Option('Todas as APIs',''));
 provider.replaceChildren(new Option('Todos os Provedores',''));
 const sources=[...new Set(casinoCatalog.providers.map(v=>v.api_source||'MANUAL'))];
 sources.forEach(src=>api.add(new Option(casinoApiLabel(src),src)));
 casinoCatalog.providers.forEach(v=>provider.add(new Option(v.name,v.id)));
 api.value=sources.includes(previousApi)?previousApi:'';
 provider.value=casinoCatalog.providers.some(v=>String(v.id)===previousProvider)?previousProvider:'';
}
async function changeCasinoGame(game,changes){
 try{
   await api('/admin/api/casino/games/save',{method:'POST',body:JSON.stringify({...game,...changes})});
   await loadCasino();
   alertBox('#casino-alert','Jogo atualizado com sucesso.',true);
 }catch(error){alertBox('#casino-alert',error.message);await loadCasino()}
}
function gameToggle(game,field,label){
 const input=document.createElement('input');input.type='checkbox';input.checked=!!Number(game[field]);input.className='game-api-switch';
 input.setAttribute('aria-label',`${label}: ${game.name}`);
 input.addEventListener('change',async()=>{input.disabled=true;await changeCasinoGame(game,{[field]:input.checked})});
 return input;
}
function renderCasinoGames(){
 const q=$('#casino-game-search').value.trim().toLocaleLowerCase('pt-BR');
 const selectedApi=$('#casino-game-api-filter').value;
 const selectedProvider=$('#casino-game-provider-filter').value;
 const items=casinoCatalog.games.filter(v=>(!selectedApi||(v.api_source||'MANUAL')===selectedApi)&&(!selectedProvider||String(v.provider_id)===selectedProvider)&&(!q||String(v.name).toLocaleLowerCase('pt-BR').includes(q)||String(v.external_id).toLocaleLowerCase('pt-BR').includes(q)));
 const pageSize=Number($('#casino-games-limit').value)||8;
 const pages=Math.max(1,Math.ceil(items.length/pageSize));casinoGamePage=Math.min(Math.max(casinoGamePage,1),pages);
 const start=(casinoGamePage-1)*pageSize;
 const body=$('#casino-games-body');body.replaceChildren();
 for(const game of items.slice(start,start+pageSize)){
   const tr=document.createElement('tr');
   const cell=(value,css='')=>{const td=document.createElement('td');td.textContent=value;if(css)td.className=css;tr.append(td);return td};
   const imageCell=document.createElement('td');imageCell.className='game-api-cover-cell';
   if(game.image_url){const image=document.createElement('img');image.src=game.image_url;image.alt='Capa: '+game.name;image.loading='lazy';image.referrerPolicy='no-referrer';imageCell.append(image)}
   else{const placeholder=document.createElement('span');placeholder.className='game-api-cover-placeholder';placeholder.textContent='MZ';imageCell.append(placeholder)}
   tr.append(imageCell);
   const title=cell(game.name,'game-api-name');title.title=game.name;
   cell(game.external_id,'game-api-code');cell(game.provider_name,'game-api-provider');
   const apiCell=cell(casinoApiLabel(game.api_source),'game-api-source');apiCell.classList.add(game.api_source==='PLAYFIVER'?'is-playfiver':'is-manual');
   cell(Number(game.access_count||0).toLocaleString('pt-BR'),'game-api-access');
   const status=document.createElement('td');status.append(gameToggle(game,'enabled','Ativar jogo'));tr.append(status);
   const popular=document.createElement('td');popular.append(gameToggle(game,'featured','Marcar como popular'));tr.append(popular);
   const actions=document.createElement('td');actions.className='game-api-actions';
   const edit=document.createElement('button');edit.type='button';edit.className='game-api-edit';edit.textContent='✎ Editar';edit.addEventListener('click',()=>casinoGameForm(game));
   const del=document.createElement('button');del.type='button';del.className='game-api-delete';del.textContent='♜ Excluir';
   del.addEventListener('click',async()=>{
     if(!confirm(`Excluir o jogo “${game.name}” do catálogo? Esta ação não pode ser desfeita.`))return;
     del.disabled=true;
     try{await api('/admin/api/casino/games/delete',{method:'POST',body:JSON.stringify({id:game.id})});await loadCasino();alertBox('#casino-alert','Jogo excluído.',true)}
     catch(error){alertBox('#casino-alert',error.message);del.disabled=false}
   });
   actions.append(edit,del);tr.append(actions);body.append(tr);
 }
 if(!items.length){const row=document.createElement('tr');row.innerHTML='<td class="game-api-empty" colspan="9">Nenhum jogo encontrado com os filtros selecionados.</td>';body.append(row)}
 $('#casino-games-count').textContent=items.length?`Mostrando ${start+1} a ${Math.min(start+pageSize,items.length)} de ${items.length} jogos`:'Nenhum jogo encontrado';
 $('#casino-games-page').textContent=`Página ${casinoGamePage} de ${pages}`;
 $('#casino-games-prev').disabled=casinoGamePage<=1;
 $('#casino-games-next').disabled=casinoGamePage>=pages;
}
async function loadCasino(){
 try{
   casinoCatalog=await api('/admin/api/casino/catalog');
   const p=$('#casino-providers-body'),select=$('#casino-provider-select'),categorySelect=$('#casino-game-form [name=category]'),categoryBody=$('#casino-categories-body');p.replaceChildren();select.replaceChildren();categorySelect.replaceChildren();categoryBody.replaceChildren();
   const iconLabels={slots:'Slots',fish:'Pescaria',sport:'SportBet',roulette:'Roleta',live:'Ao vivo',table:'Mesa',other:'Outro'};
   (casinoCatalog.categories||[]).sort((a,b)=>Number(a.sort_order)-Number(b.sort_order)||Number(a.id)-Number(b.id)).forEach(v=>{
     categorySelect.add(new Option(v.name,v.code));
     const tr=document.createElement('tr');
     tr.innerHTML=`<td><code>${escapeHtml(v.code)}</code></td><td>${escapeHtml(v.name)}</td><td>${escapeHtml(iconLabels[v.icon_key]||v.icon_key)}</td><td>${Number(v.sort_order)}</td><td>${Number(v.enabled)?'Ativa':'Inativa'}</td><td class="provider-row-actions"><button class="secondary-action category-edit" type="button">Editar</button><button class="danger-button category-delete" type="button">Excluir</button></td>`;
     tr.querySelector('.category-edit').onclick=()=>{const f=$('#casino-category-form');f.elements.id.value=v.id;f.elements.code.value=v.code;f.elements.code.readOnly=true;f.elements.name.value=v.name;f.elements.icon_key.value=v.icon_key||'slots';f.elements.sort_order.value=v.sort_order;f.elements.enabled.checked=!!Number(v.enabled);openEditor('casino-category-form',true)};
     tr.querySelector('.category-delete').onclick=async()=>{if(!confirm(`Excluir a categoria “${v.name}”? Só é permitido quando não existem jogos vinculados.`))return;const button=tr.querySelector('.category-delete');button.disabled=true;try{await api('/admin/api/casino/categories/delete',{method:'POST',body:JSON.stringify({id:v.id})});await loadCasino();alertBox('#casino-alert','Categoria excluída.',true)}catch(error){alertBox('#casino-alert',error.message);button.disabled=false}};
     categoryBody.append(tr);
   });
   if(!(casinoCatalog.categories||[]).length){categoryBody.innerHTML='<tr><td colspan="6">Nenhuma categoria cadastrada.</td></tr>';categorySelect.add(new Option('Slots','SLOTS'))}
   casinoCatalog.providers.forEach(v=>{
     select.add(new Option(v.name,v.id));
     const tr=document.createElement('tr');
     tr.innerHTML=`<td class="provider-admin-logo">${v.logo_path?`<img src="${escapeHtml(v.logo_path)}" alt="" width="150" height="60">`:'<span>—</span>'}</td><td>${escapeHtml(v.code)}</td><td>${escapeHtml(v.name)}</td><td>${escapeHtml(v.mode)}</td><td>${escapeHtml(casinoApiLabel(v.api_source))}</td><td>${v.enabled?'Ativo':'Inativo'}</td><td class="provider-row-actions"><button class="secondary-action casino-edit" type="button">Editar</button><button class="danger-button provider-delete" type="button" aria-label="Excluir provedor ${escapeHtml(v.name)}">Excluir</button></td>`;
     tr.querySelector('.casino-edit').onclick=()=>{
       const f=$('#casino-provider-form');f.elements.id.value=v.id;f.elements.code.value=v.code;f.elements.code.readOnly=true;
       f.elements.name.value=v.name;f.elements.logo_path.value=v.logo_path||'';f.elements.logo_file.value='';const preview=$('#provider-logo-preview');preview.classList.toggle('hidden',!v.logo_path);if(v.logo_path)preview.src=v.logo_path;f.elements.mode.value=v.mode;f.elements.api_source.value=v.api_source||'MANUAL';
       f.elements.enabled.checked=!!Number(v.enabled);openEditor('casino-provider-form',true);
     };
     tr.querySelector('.provider-delete').onclick=async()=>{
       if(!confirm(`Excluir o provedor “${v.name}”? Esta ação é definitiva e só será permitida se não houver jogos vinculados.`))return;
       const button=tr.querySelector('.provider-delete');button.disabled=true;
       try{await api('/admin/api/casino/providers/delete',{method:'POST',body:JSON.stringify({id:v.id})});await loadCasino();alertBox('#casino-alert','Provedor excluído.',true)}
       catch(error){alertBox('#casino-alert',error.message);button.disabled=false}
     };
     p.append(tr);
   });
   if(!casinoCatalog.providers.length)p.innerHTML='<tr><td colspan="7">Nenhum provedor cadastrado.</td></tr>';
   casinoGameOptions();renderCasinoGames();
 }catch(error){alertBox('#casino-alert',error.message)}
}
$('#casino-refresh').addEventListener('click',loadCasino);
$('#casino-game-filters').addEventListener('submit',event=>{event.preventDefault();casinoGamePage=1;renderCasinoGames()});
$('#casino-game-search').addEventListener('input',()=>{casinoGamePage=1;renderCasinoGames()});
for(const id of ['casino-game-api-filter','casino-game-provider-filter','casino-games-limit'])$('#'+id).addEventListener('change',()=>{casinoGamePage=1;renderCasinoGames()});
$('#casino-games-prev').addEventListener('click',()=>{casinoGamePage--;renderCasinoGames()});
$('#casino-games-next').addEventListener('click',()=>{casinoGamePage++;renderCasinoGames()});
$('#casino-category-form').addEventListener('reset',e=>setTimeout(()=>{e.currentTarget.elements.code.readOnly=false;e.currentTarget.elements.sort_order.value='100';e.currentTarget.elements.enabled.checked=true},0));
$('#casino-category-form').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,d=Object.fromEntries(new FormData(f));d.code=String(d.code||'').trim().toUpperCase();d.enabled=f.elements.enabled.checked;const submit=f.querySelector('[type=submit]');submit.disabled=true;try{await api('/admin/api/casino/categories/save',{method:'POST',body:JSON.stringify(d)});f.reset();closeEditor('casino-category-form');await loadCasino();alertBox('#casino-alert','Categoria salva.',true)}catch(err){alertBox('#casino-alert',err.message)}finally{submit.disabled=false}});
$('#casino-provider-form').addEventListener('reset',e=>setTimeout(()=>e.currentTarget.elements.code.readOnly=false,0));
$('#casino-provider-form').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,d=Object.fromEntries(new FormData(f));delete d.logo_file;d.enabled=f.elements.enabled.checked;const submit=f.querySelector('[type=submit]');submit.disabled=true;try{const file=f.elements.logo_file.files[0];if(file){if(file.size>2097152)throw new Error('Logo deve ter até 2 MB.');const fd=new FormData();fd.append('kind','providers');fd.append('image',file);const response=await fetch('/admin/api/platform/upload',{method:'POST',headers:{Authorization:`Bearer ${token}`},body:fd});const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(data.message||data.error||'Falha no upload da logo');d.logo_path=data.path;}await api('/admin/api/casino/providers/save',{method:'POST',body:JSON.stringify(d)});f.reset();f.elements.code.readOnly=false;closeEditor('casino-provider-form');await loadCasino();alertBox('#casino-alert','Provedor salvo.',true)}catch(err){alertBox('#casino-alert',err.message)}finally{submit.disabled=false}});
$('#casino-game-form').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,d=Object.fromEntries(new FormData(f));d.enabled=f.elements.enabled.checked;d.featured=f.elements.featured.checked;d.access_count=Number(f.elements.access_count.value||0);try{await api('/admin/api/casino/games/save',{method:'POST',body:JSON.stringify(d)});f.reset();f.elements.access_count.value='250';closeEditor('casino-game-form');await loadCasino();alertBox('#casino-alert','Jogo salvo.',true)}catch(err){alertBox('#casino-alert',err.message)}});

// Navegação do cassino: seções reais ativas; recursos que dependem de APIs permanecem indisponíveis.
(() => {
 const tabs=[...document.querySelectorAll('[data-casino-tab]:not(:disabled)')];
 const parent=document.querySelector('.casino-nav-parent');
 const group=document.querySelector('.casino-nav-group');
 const choose=(tab,expand=true)=>{
   tabs.forEach(b=>b.classList.toggle('active',b.dataset.casinoTab===tab));
   const title=tab==='providers'?'Provedores':tab==='categories'?'Categorias de jogos':tab==='credentials'?'Credenciais das APIs':'Gerenciamento de Jogos API';
   const description=tab==='providers'?'Gerencie os provedores cadastrados.':tab==='categories'?'Organize Slots, Pescaria, SportBet, Roleta e outras categorias.':tab==='credentials'?'Credenciais PlayFiver criptografadas, game launch e callback financeiro.':'Busca, filtros e visibilidade do catálogo.';
   if(!document.querySelector('#page-casino').classList.contains('hidden')) setHeading('PLATAFORMA',title,description);
   const panelId=tab==='providers'?'casino-providers-panel':tab==='categories'?'casino-categories-panel':tab==='credentials'?'casino-credentials-panel':'casino-games-panel';
   document.querySelectorAll('.casino-panel').forEach(p=>p.classList.toggle('hidden',p.id!==panelId));
   if(expand && group)group.classList.remove('collapsed');
   if(parent && expand)parent.setAttribute('aria-expanded','true');
 };
 tabs.forEach(b=>b.addEventListener('click',async()=>{document.querySelector('.nav-item[data-page="casino"]')?.click();choose(b.dataset.casinoTab)}));
 parent?.addEventListener('click',e=>{
   e.preventDefault();
   const collapsed=group?.classList.toggle('collapsed');
   parent.setAttribute('aria-expanded',collapsed?'false':'true');
   if(!collapsed){ openPage('casino'); }
 });
 choose('games',false);
})();

async function loadPlayfiverConfig(){try{const d=await api('/admin/api/casino/playfiver');const c=d.config,f=$('#playfiver-form');f.elements.base_url.value=c.base_url||'https://api.playfivers.com';f.elements.enabled.checked=!!c.enabled;let status='Credenciais PlayFiver não configuradas';if(c.configured)status=c.enabled?'PlayFiver ativa':'PlayFiver configurada, porém desativada';$('#playfiver-status').textContent=status;const callback=$('#playfiver-callback-url');if(callback)callback.textContent=location.origin+(window.IGAMING?.basePath||'')+'/api/webhooks/casino/playfiver';}catch(e){alertBox('#playfiver-alert',e.message)}}
$('#playfiver-form').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget;const btn=f.querySelector('[type="submit"]');btn.disabled=true;try{const data=Object.fromEntries(new FormData(f));data.enabled=f.elements.enabled.checked;await api('/admin/api/casino/playfiver',{method:'POST',body:JSON.stringify(data)});for(const name of ['agent_code','agent_token','agent_secret'])f.elements[name].value='';await loadPlayfiverConfig();alertBox('#playfiver-alert','Integração PlayFiver salva com credenciais criptografadas.',true)}catch(err){alertBox('#playfiver-alert',err.message)}finally{btn.disabled=false}});
document.querySelector('[data-casino-tab="credentials"]').addEventListener('click',loadPlayfiverConfig);

// Plataforma: controles administrativos autenticados, sem mutação de saldo.
let platformData={};let auditPage=1;
async function loadPlatform(){try{platformData=await api('/admin/api/platform');setAdminBrowserFavicon(platformData.settings?.favicon_path);const f=$('#settings-form');for(const [k,v] of Object.entries(platformData.settings)){if(!f.elements[k])continue;if(k==='maintenance')f.elements[k].checked=v==='1';else f.elements[k].value=v;}for(const type of ['banners','promotions','affiliates'])renderPlatform(type);renderAnnouncements();populateIdentity()}catch(e){alertBox('#settings-alert',e.message)}}
function renderAnnouncements(){
 const host=$('#announcements-list');host.replaceChildren();
 const rows=platformData.announcements||[];
 if(!rows.length){host.innerHTML='<p class="appearance-empty">Nenhuma novidade cadastrada. Use “Nova novidade” para publicar um texto na home.</p>';return;}
 for(const item of rows){
   const row=document.createElement('div');row.className='platform-row announcement-admin-row';
   const info=document.createElement('div');info.className='promotion-info';
   const title=document.createElement('strong');title.textContent=item.message;
   const details=document.createElement('small');details.textContent=(Number(item.enabled)?'Publicada':'Desativada')+' · Ordem '+item.sort_order;
   info.append(title,details);row.append(info);
   const edit=document.createElement('button');edit.className='secondary';edit.type='button';edit.textContent='Editar';edit.onclick=()=>{const f=$('#announcements-form');f.elements.id.value=item.id;f.elements.message.value=item.message;f.elements.sort_order.value=item.sort_order;f.elements.enabled.checked=!!Number(item.enabled);openEditor('announcements-form',true)};
   const toggle=document.createElement('button');toggle.className='secondary';toggle.type='button';toggle.textContent=Number(item.enabled)?'Desativar':'Ativar';toggle.onclick=async()=>{toggle.disabled=true;try{await api('/admin/api/platform/announcements/save',{method:'POST',body:JSON.stringify({...item,enabled:!Number(item.enabled)})});await loadPlatform();alertBox('#announcements-alert','Novidade atualizada.',true)}catch(e){alertBox('#announcements-alert',e.message);toggle.disabled=false}};
   const del=document.createElement('button');del.className='danger-button';del.type='button';del.textContent='Excluir';del.onclick=async()=>{if(!confirm('Excluir esta novidade?'))return;try{await api('/admin/api/platform/announcements/delete',{method:'POST',body:JSON.stringify({id:item.id})});await loadPlatform();alertBox('#announcements-alert','Novidade excluída.',true)}catch(e){alertBox('#announcements-alert',e.message)}};
   row.append(edit,toggle,del);host.append(row);
 }
}
$('#announcements-form').addEventListener('submit',async e=>{
 e.preventDefault();const f=e.currentTarget;const d=Object.fromEntries(new FormData(f));d.enabled=f.elements.enabled.checked;const button=f.querySelector('[type=submit]');button.disabled=true;
 try{await api('/admin/api/platform/announcements/save',{method:'POST',body:JSON.stringify(d)});closeEditor('announcements-form');f.reset();await loadPlatform();alertBox('#announcements-alert','Novidade salva.',true)}catch(error){alertBox('#announcements-alert',error.message)}finally{button.disabled=false}
});
async function deletePlatformBanner(item){
 if(!confirm(`Excluir o banner “${item.title}”? Esta ação não pode ser desfeita.`))return;
 try{await api('/admin/api/platform/banners/delete',{method:'POST',body:JSON.stringify({id:item.id})});await loadPlatform();alertBox('#banners-alert','Banner excluído.',true)}
 catch(error){alertBox('#banners-alert',error.message)}
}
function renderPlatform(type){
 const host=$('#'+type+'-list');if(!host)return;host.replaceChildren();
 let rows=platformData[type]||[];
 if(type==='banners'){
   const filter=document.querySelector('[data-banner-filter].active')?.dataset.bannerFilter||'home';
   rows=rows.filter(item=>item.position===filter).sort((a,b)=>Number(a.sort_order)-Number(b.sort_order)||Number(a.id)-Number(b.id));
   if(!rows.length){host.innerHTML='<div class="appearance-empty">Nenhum banner nesta seção. Use “Novo banner” para começar.</div>';return}
   const table=document.createElement('table');table.className='appearance-table';
   table.innerHTML='<thead><tr><th>ID</th><th>Título</th><th>Imagem</th><th>Status</th><th>Destino</th><th>Ordem</th><th>Ações</th></tr></thead>';
   const body=document.createElement('tbody');
   for(const item of rows){
     const tr=document.createElement('tr');
     const cell=value=>{const td=document.createElement('td');td.textContent=value;tr.append(td)};
     cell('#'+item.id);cell(item.title);
     const image=document.createElement('td');if(item.image_path){const img=document.createElement('img');img.src=item.image_path;img.alt=item.title;img.loading='lazy';image.append(img)}else image.textContent='Sem imagem';tr.append(image);
     const status=document.createElement('td');const badge=document.createElement('span');badge.className='appearance-status '+(Number(item.enabled)?'on':'off');badge.textContent=Number(item.enabled)?'Ativo':'Inativo';status.append(badge);tr.append(status);
     cell(item.target_path||'—');cell(String(item.sort_order??'—')+(item.position==='casino'&&Number(item.sort_order)>=1&&Number(item.sort_order)<=3?' · '+['','Esquerda','Direita superior','Direita inferior'][Number(item.sort_order)] : ''));
     const actions=document.createElement('td');const edit=document.createElement('button');edit.className='secondary';edit.type='button';edit.textContent='Editar';edit.onclick=()=>editPlatformItem(type,item);const del=document.createElement('button');del.className='danger-button banner-delete';del.type='button';del.textContent='Excluir';del.onclick=()=>deletePlatformBanner(item);actions.append(edit,del);tr.append(actions);body.append(tr);
   }
   table.append(body);host.append(table);return;
 }
 if(!rows.length){host.innerHTML='<div class="appearance-empty">Nenhum registro cadastrado nesta seção.</div>';return}
 for(const item of rows){
   const row=document.createElement('div');row.className='platform-row'+(type==='promotions'?' promotions-row':'');
   if(type==='promotions'){
     const image=document.createElement('div');image.className='promotion-preview';
     if(item.image_path){const img=document.createElement('img');img.src=item.image_path;img.alt=item.title;img.loading='lazy';image.append(img)}else image.textContent='Sem imagem';row.append(image);
   }
   const info=document.createElement('div');info.className='promotion-info';const title=document.createElement('strong');title.textContent=item.title||item.label;const details=document.createElement('small');
   if(type==='promotions'){
      const now=Date.now(),start=item.starts_at?new Date(String(item.starts_at).replace(' ','T')).getTime():null,end=item.ends_at?new Date(String(item.ends_at).replace(' ','T')).getTime():null;
      details.textContent=(!Number(item.enabled)?'Inativa':start&&start>now?'Em breve':end&&end<now?'Encerrada':'Disponível')+' · '+(item.starts_at?'Início: '+dateTime(item.starts_at):'Sem início')+' · '+(item.ends_at?'Fim: '+dateTime(item.ends_at):'Sem fim');
      const description=document.createElement('p');description.textContent=item.description||'';info.append(title,details,description);
   }else{details.textContent=`Código ${item.code} · ${item.visits} visitas`;info.append(title,details)}
   row.append(info);
   const edit=document.createElement('button');edit.className='secondary';edit.type='button';edit.textContent='Editar';edit.onclick=()=>editPlatformItem(type,item);row.append(edit);
   if(type==='promotions'){
      const toggle=document.createElement('button');toggle.className='secondary';toggle.type='button';toggle.textContent=Number(item.enabled)?'Desativar':'Ativar';toggle.onclick=async()=>{toggle.disabled=true;try{await api('/admin/api/platform/promotions/save',{method:'POST',body:JSON.stringify({...item,enabled:!Number(item.enabled)})});await loadPlatform();alertBox('#promotions-alert','Status atualizado.',true)}catch(error){alertBox('#promotions-alert',error.message);toggle.disabled=false}};
      const del=document.createElement('button');del.className='danger-button';del.type='button';del.textContent='Excluir';del.onclick=async()=>{if(!confirm(`Excluir a promoção “${item.title}”? Esta ação não pode ser desfeita.`))return;try{await api('/admin/api/platform/promotions/delete',{method:'POST',body:JSON.stringify({id:item.id})});await loadPlatform();alertBox('#promotions-alert','Promoção excluída.',true)}catch(error){alertBox('#promotions-alert',error.message)}};
      row.append(toggle,del);
   }
   host.append(row);
 }
}
function editPlatformItem(type,item){const form=$('#'+type+'-form');for(const [key,value] of Object.entries(item)){const el=form.elements[key];if(!el||el.type==='file')continue;if(el.type==='checkbox')el.checked=!!Number(value);else el.value=value??'';}if(type==='banners'){$('#banner-form-title').textContent='Banners da plataforma';$('#banner-modal-size-guide').textContent=item.position==='casino'?'Lobby: esquerda 900 × 900 px; direita 900 × 430 px; até 2 MB.':'Carrossel: recomendado 1920 × 600 px; até 2 MB.';}openEditor(type+'-form',true)}
function populateIdentity(){const f=$('#identity-form');if(!f||!platformData.settings)return;for(const k of ['site_name','support_email','accent_color','logo_path','favicon_path','footer_about','contact_phone','social_whatsapp','social_telegram','social_instagram','social_facebook'])if(f.elements[k])f.elements[k].value=platformData.settings[k]||(k==='footer_about'?`Conheça a ${platformData.settings.site_name||'MZ90'}: entretenimento online com responsabilidade. Plataforma destinada a maiores de 18 anos.`:'');f.elements.maintenance.checked=platformData.settings.maintenance==='1';for(const kind of ['logo','favicon']){const img=$('#identity-'+kind+'-preview');const path=f.elements[kind+'_path'].value;img.classList.toggle('hidden',!path);if(path)img.src=path;}}
$('#identity-form').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget;const submit=f.querySelector('[type="submit"]');submit.disabled=true;try{const payload={};for(const k of ['site_name','support_email','accent_color','logo_path','favicon_path','footer_about','contact_phone','social_whatsapp','social_telegram','social_instagram','social_facebook'])payload[k]=f.elements[k].value;payload.maintenance=f.elements.maintenance.checked;for(const kind of ['logo','favicon']){const file=f.elements[kind+'_file'].files[0];if(!file)continue;const fd=new FormData();fd.append('kind','identity');fd.append('image',file);const response=await fetch('/admin/api/platform/upload',{method:'POST',headers:{Authorization:`Bearer ${token}`},body:fd});const data=await response.json();if(!response.ok)throw new Error(data.message||data.error||'Falha no upload');payload[kind+'_path']=data.path;}await api('/admin/api/platform/settings',{method:'POST',body:JSON.stringify(payload)});await loadPlatform();f.elements.logo_file.value='';f.elements.favicon_file.value='';alertBox('#identity-alert','Identidade visual salva.',true)}catch(err){alertBox('#identity-alert',err.message)}finally{submit.disabled=false}});
$('#settings-form').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget;const d=Object.fromEntries(new FormData(f));d.maintenance=f.elements.maintenance.checked;try{await api('/admin/api/platform/settings',{method:'POST',body:JSON.stringify(d)});alertBox('#settings-alert','Configurações salvas.',true)}catch(err){alertBox('#settings-alert',err.message)}});
for(const type of ['banners','promotions','affiliates']){$('#'+type+'-form').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget;const d=Object.fromEntries(new FormData(f));d.enabled=f.elements.enabled.checked;try{const file=f.elements.image?.files?.[0];if(file){const fd=new FormData();fd.append('kind',type);fd.append('image',file);const r=await fetch('/admin/api/platform/upload',{method:'POST',headers:{Authorization:`Bearer ${token}`},body:fd});const out=await r.json();if(!r.ok)throw new Error(out.message||out.error||'Upload falhou');d.image_path=out.path;}await api('/admin/api/platform/'+type+'/save',{method:'POST',body:JSON.stringify(d)});f.reset();closeEditor(type+'-form');await loadPlatform();alertBox('#'+type+'-alert','Registro salvo.',true)}catch(err){alertBox('#'+type+'-alert',err.message);const overlay=$('#editor-'+type+'-form');const error=overlay?.querySelector('.admin-editor-error');if(error){error.textContent=err.message;error.classList.remove('hidden')}}});}
async function loadAudit(){try{const q=encodeURIComponent($('#audit-search').value);const data=await api(`/admin/api/platform/audit?page=${auditPage}&action=${q}`);const body=$('#audit-body');body.replaceChildren();for(const item of data.items){const tr=document.createElement('tr');for(const value of [dateTime(item.created_at),item.actor_type+' · '+shortId(item.actor_id),item.action,(item.entity_type||'—')+' · '+(item.entity_id||'—')]){const td=document.createElement('td');td.textContent=value;tr.append(td)}body.append(tr)}$('#audit-page').textContent='Página '+auditPage;$('#audit-next').disabled=data.items.length<30}catch(e){console.error('Falha ao carregar auditoria')}}
$('#audit-refresh').onclick=()=>{auditPage=1;loadAudit()};$('#audit-prev').onclick=()=>{if(auditPage>1){auditPage--;loadAudit()}};$('#audit-next').onclick=()=>{auditPage++;loadAudit()};

// Aparência: somente banners; carrossel e lobby têm configurações independentes.
(()=>{
 const setBannerSection=(filter)=>{
  document.querySelectorAll('[data-banner-filter]').forEach(x=>x.classList.toggle('active',x.dataset.bannerFilter===filter));
  const form=$('#banners-form'); form.elements.position.value=filter; closeEditor('banners-form');
  const isLobby=filter==='casino';
  $('#banner-form-title').textContent=isLobby?'Novo banner do lobby':'Novo banner do carrossel';
  $('#banner-section-note').textContent=isLobby?'Posições do lobby: ordem 1 = esquerda, 2 = direita superior, 3 = direita inferior.':'O carrossel exibe somente banners ativos desta seção, respeitando a ordem definida.';
  $('#banner-size-guide').textContent=isLobby?'Lobby: recomendado 900 × 900 px (esquerda) e 900 × 430 px (direita superior/inferior). PNG, JPG ou WebP, até 2 MB. Mantenha textos e elementos importantes centralizados.':'Carrossel principal: recomendado 1920 × 600 px (proporção 16:5). PNG, JPG ou WebP, até 2 MB. Ajuste a área importante ao centro para telas menores.';
  $('#banner-modal-size-guide').textContent=isLobby?'Lobby: esquerda 900 × 900 px; direita 900 × 430 px; até 2 MB.':'Carrossel: recomendado 1920 × 600 px; até 2 MB.';
  renderPlatform('banners');
 };
 document.querySelectorAll('[data-banner-filter]').forEach(b=>b.addEventListener('click',()=>setBannerSection(b.dataset.bannerFilter)));
 document.querySelectorAll('[data-appearance-tab]').forEach(b=>b.addEventListener('click',()=>{
  const tab=b.dataset.appearanceTab;
  document.querySelectorAll('[data-appearance-tab]').forEach(x=>x.classList.toggle('selected',x===b));
  document.querySelectorAll('[data-appearance-panel]').forEach(x=>x.classList.toggle('hidden',x.dataset.appearancePanel!==tab));
}));

$('#banner-form-toggle').addEventListener('click',()=>{const filter=document.querySelector('[data-banner-filter].active')?.dataset.bannerFilter||'home';const form=$('#banners-form');form.reset();form.elements.id.value='';form.elements.image_path.value='';form.elements.position.value=filter;form.elements.sort_order.value=filter==='casino'?'1':'1';$('#banner-form-title').textContent=filter==='casino'?'Novo banner do lobby':'Novo banner do carrossel';openEditor('banners-form',false)});
 setBannerSection('home');
})();

// Promoções: submenu independente da navegação genérica e sem serviços fictícios.
(() => {
 const group=document.querySelector('.promotion-admin-group');
 const parent=document.querySelector('.promotion-admin-parent');
 const detail=document.querySelector('#admin-promotion-detail');
 const campaigns=document.querySelector('#admin-promotion-campaigns');
 const titles={vip:'Níveis VIP',coupons:'Cupons',checkin:'Check-in diário',roulette:'Roleta de boas-vindas',envelope:'Envelope vermelho',chests:'Baús e indicações',agency:'Agência',rebate:'Rebate',rescue:'Fundos de Resgate',weekly:'Compensação Semanal',cashwheel:'Roleta de Saque',lottery:'Sorteio','bonus-history':'Histórico de bônus','level-history':'Histórico de níveis'};
 const select=(key)=>{
  document.querySelectorAll('[data-admin-promotion-tab]').forEach(b=>b.classList.toggle('active',b.dataset.adminPromotionTab===key));
  detail.classList.toggle('hidden',!key);campaigns.classList.toggle('hidden',!!key);
  if(key){document.querySelector('#admin-promotion-detail-title').textContent=titles[key];document.querySelector('#admin-promotion-detail-description').textContent='Cadastre e gerencie as regras desta seção.';window.promotionConfigSelect?.(key);setHeading('PROMOÇÕES',titles[key],'Configuração do módulo de promoções.')}
 };
 parent?.addEventListener('click',e=>{e.stopImmediatePropagation();const collapsed=group.classList.toggle('collapsed');parent.setAttribute('aria-expanded',String(!collapsed));if(!collapsed){openPage('promotions');select(null)}});
 document.querySelectorAll('[data-admin-promotion-tab]').forEach(button=>button.addEventListener('click',e=>{e.stopPropagation();group.classList.remove('collapsed');parent.setAttribute('aria-expanded','true');openPage('promotions');select(button.dataset.adminPromotionTab)}));
})();

// Gerenciador CRUD persistido por API, isolado do menu e da home pública.
(() => {
 const definitions={
  vip:[['level','Nível VIP','integer'],['goal_cents','Meta de apostas acumuladas (R$)','money'],['bonus_cents','Bônus de upgrade (R$)','money'],['daily_bonus_cents','Bônus diário (R$)','money'],['weekly_bonus_cents','Bônus semanal (R$)','money'],['monthly_bonus_cents','Bônus mensal (R$)','money'],['maintenance_cents','Meta de manutenção mensal (R$)','money'],['rollover_x','Rollover (x)','decimal']],
  coupons:[['code','Código de resgate (4-64 letras/números)','code'],['quantity','Quantidade disponível','integer'],['bonus_min_cents','Bônus mínimo (R$)','money'],['bonus_max_cents','Bônus máximo (R$)','money'],['rollover_x','Rollover (x)','decimal']],
  checkin:[['day','Dia do check-in','integer'],['reward_min_cents','Recompensa mínima (R$)','money'],['reward_max_cents','Recompensa máxima (R$)','money'],['random_reward','Recompensa aleatória','bool'],['deposit_min_cents','Recarga necessária (R$)','money'],['bet_min_cents','Aposta necessária (R$)','money'],['extra_cents','Recompensa extra (R$)','money'],['rollover_x','Rollover (x)','decimal']],
  roulette:[['reward_min_cents','Recompensa mínima (R$)','money'],['reward_max_cents','Recompensa máxima (R$)','money'],['deposit_min_cents','Depósito mínimo para ganhar rodada (R$)','money'],['spins_per_deposit','Rodadas por depósito qualificado','integer'],['spins_per_referral','Rodadas por indicado cadastrado','integer'],['referral_requires_signup','Exigir cadastro pelo link de indicação','bool'],['rollover_x','Rollover do prêmio (x)','decimal']],
  envelope:[['auto_enabled','Autorizar oferta automática (configuração apenas)','bool'],['reward_min_cents','Valor mínimo (R$)','money'],['reward_max_cents','Valor máximo (R$)','money'],['multiplier_min','Multiplicador mínimo (x)','decimal'],['multiplier_max','Multiplicador máximo (x)','decimal'],['rollover_x','Rollover (x)','decimal']],
  chests:[['referral_count','Quantidade de indicados elegíveis','integer'],['referred_deposit_min_cents','Depósito mínimo por indicado (R$)','money'],['bonus_cents','Bônus ao indicador (R$)','money'],['rollover_x','Rollover necessário (x)','decimal'],['max_claims','Máximo de resgates por indicador (0 = sem limite)','integer']],
  agency:[['level','Nível','integer'],['team_bet_min_cents','Apostas válidas da equipe (R$)','money'],['commission_percent','Comissão (%)','decimal']],
  rebate:[['level','Nível','integer'],['bet_volume_cents','Volume de apostas (R$)','money'],['rebate_percent','Rebate (%)','decimal']],
  rescue:[['level','Nível','integer'],['loss_min_cents','Perda mínima (R$)','money'],['refund_percent','Compensação (%)','decimal'],['rollover_x','Rollover (x)','decimal']],
  weekly:[['level','Faixa','integer'],['loss_min_cents','Perda semanal (R$)','money'],['refund_percent','Compensação (%)','decimal'],['rollover_x','Rollover (x)','decimal']],
  cashwheel:[['target_cents','Meta (R$)','money'],['duration_days','Duração (dias)','integer'],['free_spins_per_day','Rodadas gratuitas/dia','integer'],['referral_bonus_cents','Ajuda por indicado (R$)','money']],
  lottery:[['spins_per_day','Rodadas por dia','integer'],['collection_bonus_cents','Prêmio por coleção completa (R$)','money'],['rollover_x','Rollover (x)','decimal']]
 };
 const $id=id=>document.getElementById(id);
 let active='',items=[],editing=null;
 const form=$id('promotion-config-form'),overlay=$id('promotion-config-overlay');
 const brl=n=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format((Number(n)||0)/100);
 const notify=(message,success=false)=>{const el=$id('promotion-config-alert');el.textContent=message;el.classList.remove('hidden','ok');if(success)el.classList.add('ok');};
 const val=(value,kind)=>kind==='money'?brl(value):kind==='bool'?(Number(value)?'Sim':'Não'):String(value??'—');
 const close=()=>{overlay.classList.add('hidden');document.body.classList.remove('admin-editor-open');};
 const open=item=>{
  editing=item||null;form.reset();form.elements.id.value=item?.id||'';form.elements.title.value=item?.title||'';form.elements.enabled.checked=!!Number(item?.enabled);
  $id('promotion-config-modal-title').textContent=(item?'Editar':'Adicionar')+' • '+document.querySelector('#admin-promotion-detail-title').textContent;
  $id('promotion-config-modal-error').classList.add('hidden');
  const root=$id('promotion-config-fields');root.replaceChildren();
  for(const [key,label,kind] of definitions[active]){
   const field=document.createElement('label');field.textContent=label;
   const control=document.createElement(kind==='bool'?'select':'input');control.name=key;
   if(kind==='bool'){for(const [v,t] of [['0','Não'],['1','Sim']]){const option=document.createElement('option');option.value=v;option.textContent=t;control.append(option)}control.value=String(item?.config?.[key]??0)}
   else if(kind==='code'){control.type='text';control.maxLength=64;control.pattern='[A-Za-z0-9_-]{4,64}';control.required=true;control.autocomplete='off';control.value=String(item?.config?.[key]||'');}
   else{control.type='number';control.min='0';control.step=kind==='integer'?'1':kind==='money'?'0.01':'0.01';control.required=true;control.value=item?.config?.[key]===undefined?'0':kind==='money'?(Number(item.config[key])/100).toFixed(2):String(item.config[key]);}
   field.append(control);root.append(field);
  }
  overlay.classList.remove('hidden');document.body.classList.add('admin-editor-open');form.elements.title.focus();
 };
 async function loadVipSettings(){
  let panel=document.getElementById('mz-vip-admin-settings');
  if(!panel){panel=document.createElement('section');panel.id='mz-vip-admin-settings';panel.className='panel mz-vip-admin-settings';document.querySelector('#admin-promotion-detail').prepend(panel);}
  panel.replaceChildren();const heading=document.createElement('h3');heading.textContent='Programa VIP • Regras de manutenção';panel.append(heading);
  try{const [response,hist]=await Promise.all([api('/admin/api/promotions/vip/settings'),api('/admin/api/promotions/vip/reviews')]);
    const cfg=response.settings;const form=document.createElement('form');form.className='platform-form';
    const enable=document.createElement('label');enable.textContent='Ativar benefícios recorrentes';const check=document.createElement('input');check.type='checkbox';check.checked=!!Number(cfg.enabled);enable.append(check);
    const modeLabel=document.createElement('label');modeLabel.textContent='Política de manutenção';const mode=document.createElement('select');for(const [value,label] of [['lifelong','VIP vitalício (suspender bônus se faltar manutenção)'],['downgrade','Reduzir nível quando faltar manutenção']]){const option=document.createElement('option');option.value=value;option.textContent=label;mode.append(option);}mode.value=cfg.maintenance_mode;modeLabel.append(mode);
    const stepsLabel=document.createElement('label');stepsLabel.textContent='Níveis reduzidos por mês (1–20)';const steps=document.createElement('input');steps.type='number';steps.min=1;steps.max=20;steps.value=cfg.downgrade_steps;stepsLabel.append(steps);
    const save=document.createElement('button');save.className='gold-button';save.type='submit';save.textContent='Salvar regras VIP';const message=document.createElement('p');message.setAttribute('role','status');
    form.append(enable,modeLabel,stepsLabel,save,message);form.addEventListener('submit',async e=>{e.preventDefault();save.disabled=true;try{await api('/admin/api/promotions/vip/settings',{method:'POST',body:JSON.stringify({enabled:check.checked,maintenance_mode:mode.value,downgrade_steps:Number(steps.value)})});message.textContent='Regras VIP salvas.';}catch(error){message.textContent=error.message;}finally{save.disabled=false;}});panel.append(form);
    const summary=document.createElement('details');const title=document.createElement('summary');title.textContent='Histórico de manutenção ('+(hist.items||[]).length+' registros recentes)';summary.append(title);
    for(const item of hist.items||[]){const line=document.createElement('p');line.textContent=`${item.username} • ${item.period_key} • VIP ${item.before_level} → ${item.after_level} • ${item.rule_applied} • ${brl(item.volume_minor)} / ${brl(item.required_minor)}`;summary.append(line);}panel.append(summary);
  }catch(error){panel.append(document.createTextNode('Falha ao carregar regras VIP: '+error.message));}
 }
 const load=async()=>{
  if(!definitions[active])return;
  if(active==='vip')loadVipSettings();
  try{const data=await api('/admin/api/promotion-configs?type='+encodeURIComponent(active));if(!definitions[active])return;items=data.items||[];render();}
  catch(e){notify('Não foi possível carregar configurações: '+e.message+'. Execute a migration 018 no servidor.');}
 };
 const render=()=>{
  const head=$id('promotion-config-head'),body=$id('promotion-config-body');head.replaceChildren();body.replaceChildren();
  const tr=document.createElement('tr');for(const label of ['Nome',...definitions[active].map(x=>x[1]),'Status','Ações']){const th=document.createElement('th');th.textContent=label;tr.append(th)}head.append(tr);
  if(!items.length){const row=document.createElement('tr'),cell=document.createElement('td');cell.colSpan=definitions[active].length+3;cell.textContent='Nenhuma configuração cadastrada.';cell.className='promotion-empty';row.append(cell);body.append(row);return;}
  for(const item of items){const row=document.createElement('tr');for(const text of [item.title,...definitions[active].map(([k,,type])=>val(item.config?.[k],type)),Number(item.enabled)?'Habilitada':'Desabilitada']){const cell=document.createElement('td');cell.textContent=text;row.append(cell)}
   const actions=document.createElement('td');actions.className='promotion-row-actions';
   const edit=document.createElement('button');edit.type='button';edit.textContent='Editar';edit.className='row-action';edit.addEventListener('click',()=>open(item));
   const del=document.createElement('button');del.type='button';del.textContent='Excluir';del.className='danger-button';del.addEventListener('click',async()=>{if(!confirm('Excluir “'+item.title+'”? Esta alteração não pode ser desfeita.'))return;del.disabled=true;try{await api('/admin/api/promotion-configs/delete',{method:'POST',body:JSON.stringify({type:active,id:item.id})});await load();notify('Registro excluído.',true)}catch(e){notify(e.message);del.disabled=false}});
   actions.append(edit,del);row.append(actions);body.append(row);
  }
 };
 async function loadRedemptionHistory(){
  const head=$id('promotion-config-head'),body=$id('promotion-config-body');head.replaceChildren();body.replaceChildren();
  const tr=document.createElement('tr');for(const label of ['Jogador','Promoção','Campanha','Dia','Valor','Rollover','Status','Data']){const th=document.createElement('th');th.textContent=label;tr.append(th)}head.append(tr);
  try{const data=await api('/admin/api/promotions/redemptions');for(const item of data.items||[]){const row=document.createElement('tr');for(const value of [item.username,item.promotion_type,item.title,item.day_key||'—',brl(item.amount_minor),brl(item.wager_progress_minor)+' / '+brl(item.wager_required_minor),item.status,item.created_at]){const td=document.createElement('td');td.textContent=String(value??'');row.append(td)}body.append(row)}if(!data.items?.length){const row=document.createElement('tr'),td=document.createElement('td');td.colSpan=8;td.textContent='Nenhum resgate registrado.';row.append(td);body.append(row)}}catch(error){notify('Falha ao consultar resgates: '+error.message)}
 }
 window.promotionConfigSelect=key=>{active=key;const vipPanel=document.getElementById('mz-vip-admin-settings');if(vipPanel)vipPanel.classList.toggle('hidden',key!=='vip');const supported=!!definitions[key];$id('promotion-config-new').classList.toggle('hidden',!supported);$id('promotion-config-head').replaceChildren();$id('promotion-config-body').replaceChildren();$id('promotion-config-alert').classList.add('hidden');
  if(!supported){if(key==='bonus-history')loadRedemptionHistory();else if(key==='level-history'){(async()=>{try{const response=await api('/admin/api/promotions/vip/reviews');const body=$id('promotion-config-body');for(const item of response.items||[]){const tr=document.createElement('tr'),td=document.createElement('td');td.textContent=`${item.username} · ${item.period_key} · VIP ${item.before_level} → ${item.after_level} · ${item.rule_applied} · ${brl(item.volume_minor)} / ${brl(item.required_minor)}`;tr.append(td);body.append(tr)}if(!response.items?.length){const tr=document.createElement('tr'),td=document.createElement('td');td.textContent='Nenhuma revisão mensal registrada.';tr.append(td);body.append(tr)}}catch(err){notify(err.message)}})();}return;}load();};
 $id('promotion-config-new').addEventListener('click',()=>{if(definitions[active])open(null)});
 $id('promotion-config-close').addEventListener('click',close);$id('promotion-config-cancel').addEventListener('click',close);
 overlay.addEventListener('click',e=>{if(e.target===overlay)close()});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!overlay.classList.contains('hidden'))close()});
 form.addEventListener('submit',async e=>{e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;
  try{const config={};for(const [key,,kind] of definitions[active]){const raw=form.elements[key].value;config[key]=kind==='code'?String(raw).trim().toUpperCase():kind==='bool'?Number(raw):kind==='money'?Math.round(Number(raw)*100):Number(raw)}
   await api('/admin/api/promotion-configs/save',{method:'POST',body:JSON.stringify({type:active,id:form.elements.id.value||0,title:form.elements.title.value,enabled:form.elements.enabled.checked,config})});close();await load();notify('Configuração salva com sucesso.',true);
  }catch(err){const el=$id('promotion-config-modal-error');el.textContent=err.message;el.classList.remove('hidden')}finally{button.disabled=false}
 });
})();
