/* MZ90 V12: cupons e check-in com resgate real e status autenticado. */
(() => {
  'use strict';
  const base=(window.IGAMING?.basePath||'').replace(/\/$/,'');
  const directory=document.getElementById('mz-promo-directory');
  const detail=document.getElementById('mz-promo-detail');
  const section=detail?.closest('.page-section');
  if(!directory||!detail)return;
  const definitions=[
   {id:'agency',title:'Agência e Indicações',icon:'🤝',desc:'Faixas de comissão e informações do programa.',type:'agency',fields:[['level','Nível','integer'],['team_bet_min_cents','Meta de equipe','money'],['commission_percent','Comissão','percent']]},
   {id:'chests',title:'Baú do Tesouro',icon:'🎁',desc:'Requisitos e recompensas de indicação.',type:'chests',fields:[['referral_count','Indicados elegíveis','integer'],['referred_deposit_min_cents','Depósito mínimo','money'],['bonus_cents','Bônus','money'],['rollover_x','Rollover','multiple']]},
   {id:'rebate',title:'Rebate',icon:'↺',desc:'Porcentagem fixa para apostas válidas.',type:'rebate',fields:[]},
   {id:'coupons',title:'Troca de Recompensas',icon:'🎟️',desc:'Consulte as regras de cupons ativos.',type:'coupons',fields:[['quantity','Quantidade','integer'],['bonus_min_cents','Bônus mínimo','money'],['bonus_max_cents','Bônus máximo','money']]},
   {id:'checkin',title:'Nível e Check-in',icon:'📅',desc:'Dias, requisitos e recompensas publicados.',type:'checkin',fields:[['day','Dia','integer'],['reward_min_cents','Valor mínimo','money'],['reward_max_cents','Valor máximo','money'],['bet_min_cents','Apostas necessárias','money']]},
   {id:'rescue',title:'Fundos de Resgate',icon:'🛟',desc:'Faixas de compensação diária.',type:'rescue',fields:[['loss_min_cents','Perda mínima','money'],['refund_percent','Percentual','percent'],['rollover_x','Rollover','multiple']]},
   {id:'vip',title:'Clube VIP',icon:'👑',desc:'Níveis e benefícios configurados.',type:'vip',fields:[['level','VIP','integer'],['goal_cents','Meta','money'],['bonus_cents','Bônus','money'],['rollover_x','Rollover','multiple']]},
   {id:'cashwheel',title:'Roleta de Saque',icon:'🎡',desc:'Condições da campanha configurada.',type:'cashwheel',fields:[['target_cents','Meta da campanha','money'],['duration_days','Validade (dias)','integer'],['free_spins_per_day','Rodadas grátis/dia','integer']]},
   {id:'lottery',title:'Sorteio de Cartas',icon:'🃏',desc:'Prêmio de coleção e regras publicadas.',type:'lottery',fields:[['spins_per_day','Rodadas por dia','integer'],['collection_bonus_cents','Prêmio coleção','money'],['rollover_x','Rollover','multiple']]},
   {id:'roulette',title:'Giro da Sorte',icon:'🎯',desc:'Rodadas por depósito e indicação.',type:'roulette',fields:[['deposit_min_cents','Depósito mínimo','money'],['spins_per_deposit','Rodadas por depósito','integer'],['spins_per_referral','Rodadas por indicado','integer'],['win_chance_percent','Chance de ganho','percent']]},
  ];
  let configs=[];
  const brl=value=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(value||0)/100);
  function token(){return localStorage.getItem('igaming_token')||'';}
  async function userApi(path,body=null){
    if(!token())throw Error('Faça login para resgatar recompensas.');
    const response=await fetch(base+path,{method:body===null?'GET':'POST',credentials:'same-origin',headers:{'Accept':'application/json','Authorization':'Bearer '+token(),...(body===null?{}:{'Content-Type':'application/json'})},...(body===null?{}:{body:JSON.stringify(body)})});
    const result=await response.json().catch(()=>({}));
    if(!response.ok)throw Error(result.message||result.error||'Não foi possível concluir o resgate.');
    return result;
  }
  function info(parent,message,success=false){let output=parent.querySelector('.mz-promo-feedback');if(!output){output=node('p','mz-promo-feedback');output.setAttribute('role','status');parent.prepend(output)}output.textContent=message;output.classList.toggle('is-success',success);}
  function history(container,records,type){const list=node('div','mz-promo-history');list.append(node('h3','','Histórico de resgates'));const own=records.filter(row=>row.promotion_type===type);if(!own.length)list.append(node('p','mz-promo-note','Nenhum resgate realizado até agora.'));for(const record of own.slice(0,15)){const entry=node('div','mz-promo-line');entry.append(node('span','',`${record.title} • ${String(record.created_at).slice(0,16)}`),node('b','',`${brl(record.amount_minor)} • ${record.status==='COMPLETED'?'Liberado':'Rollover: '+brl(record.wager_progress_minor)+'/'+brl(record.wager_required_minor)}`));list.append(entry)}container.append(list);}
  async function couponSection(container){
    const box=node('section','mz-promo-box');box.append(node('h3','','Resgatar cupom'));
    box.append(node('p','mz-promo-note','Digite o código recebido pelos canais oficiais. Cada código pode ser utilizado uma única vez por conta, respeitando o estoque.'));
    const form=node('form','mz-promo-redeem-form');const input=node('input','mz-promo-field');input.name='coupon';input.maxLength=64;input.required=true;input.pattern='[A-Za-z0-9_-]{4,64}';input.autocomplete='off';input.placeholder='Seu código promocional';input.setAttribute('aria-label','Código do cupom');
    const button=node('button','mz-promo-action','Trocar código');button.type='submit';form.append(input,button);box.append(form);container.append(box);
    form.addEventListener('submit',async event=>{event.preventDefault();button.disabled=true;try{const data=await userApi('/api/promotions/coupons/redeem',{code:input.value.trim().toUpperCase()});info(box,`Cupom resgatado: ${brl(data.award.amount_minor)} creditados na conta ${data.award.account_type}. ${data.award.status==='LOCKED'?'Liberação após rollover de '+brl(data.award.wager_required_minor)+'.':''}`,true);input.value='';document.dispatchEvent(new Event('mz:wallet-updated'));await refresh();}catch(error){info(box,error.message)}finally{button.disabled=false}});
    if(token())try{const data=await userApi('/api/promotions/status');history(container,data.history||[],'coupons')}catch(error){info(box,error.message)}
  }
  async function checkinSection(container){
    const box=node('section','mz-promo-box mz-checkin');
    const header=node('div','mz-checkin-hero');
    const tag=node('span','mz-checkin-kicker','✦ RECOMPENSA DIÁRIA');
    const present=node('span','mz-checkin-hero-gift','🎁');present.setAttribute('aria-hidden','true');
    header.append(tag,present,node('h3','','Check-in Diário'),node('p','','Entre todos os dias e acompanhe suas recompensas.'));
    const streak=node('div','mz-checkin-streak','🔥 Consulte sua sequência');header.append(streak);box.append(header);
    const content=node('div','mz-checkin-content');box.append(content);container.append(box);
    const render=(days,status)=>{
      content.replaceChildren();
      const ordered=[...days].sort((a,b)=>Number(a.config.day)-Number(b.config.day));
      const claimed=Boolean(status?.claimed_today);
      const next=Number(status?.next_day||0);
      const historyRows=(status?.history||[]).filter(r=>r.promotion_type==='checkin');
      const claimedDays=new Set();
      // Reconstrói apenas os dias consecutivos do ciclo corrente; registros antigos não viram resgates atuais.
      const today=status?.today;
      const anchor=today?new Date(today+'T12:00:00Z'):null;
      const latest=historyRows.find(r=>r.day_key===today);
      let lastSequence=claimed?Number(latest?.sequence_day||0):next-1;
      if(anchor&&lastSequence>0){
        for(let index=0;index<lastSequence;index++){
          const date=new Date(anchor);date.setUTCDate(date.getUTCDate()-(claimed?index:index+1));
          const key=date.toISOString().slice(0,10);
          const row=historyRows.find(r=>r.day_key===key&&Number(r.sequence_day)===lastSequence-index);
          if(!row)break;
          claimedDays.add(Number(row.sequence_day));
        }
      }
      const completed=claimedDays.size;
      streak.textContent=`🔥 Sequência: ${completed} ${completed===1?'dia':'dias'}`;
      const heading=node('div','mz-checkin-heading');heading.append(node('h4','','Suas recompensas'),node('span','mz-checkin-counter',`${completed}/${ordered.length} dias`));content.append(heading);
      const progress=node('div','mz-checkin-progress');progress.setAttribute('role','progressbar');progress.setAttribute('aria-label','Progresso da sequência');progress.setAttribute('aria-valuemin','0');progress.setAttribute('aria-valuemax',String(ordered.length));progress.setAttribute('aria-valuenow',String(completed));
      const bar=node('span','mz-checkin-progress-fill');bar.style.width=(ordered.length?completed/ordered.length*100:0)+'%';progress.append(bar);content.append(progress);
      const grid=node('div','mz-promo-checkin-days');
      for(const [index,item] of ordered.entries()){
        const day=Number(item.config.day);const done=claimedDays.has(day);const available=Boolean(status&&!claimed&&day===next);
        const finale=ordered.length>=7 && index===ordered.length-1;
        const card=node('div','mz-promo-checkin-day'+(done?' is-complete':available?' is-available':' is-locked')+(finale?' is-finale':''));
        const reward=Number(item.config.reward_min_cents||0),max=Number(item.config.reward_max_cents||0),extra=Number(item.config.extra_cents||0);
        const rewardLabel=reward===max?brl(reward+extra):`${brl(reward+extra)} – ${brl(max+extra)}`;
        card.append(node('span','mz-checkin-day-label',`DIA ${day}`),node('span','mz-checkin-day-icon',done?'✓':available?'🎁':'🔒'),node('strong','mz-checkin-day-reward',rewardLabel),node('span','mz-checkin-day-state',done?'Recebido':available?'Disponível':'Bloqueado'));
        // Regras são individuais por dia e vêm exclusivamente da configuração do Admin.
        const rules=node('div','mz-checkin-day-rules');
        const deposit=Number(item.config.deposit_min_cents||0);
        const bet=Number(item.config.bet_min_cents||0);
        const rollover=Number(item.config.rollover_x||0);
        rules.append(node('span','','Depósito mínimo: '+brl(deposit)));
        rules.append(node('span','','Apostas necessárias: '+brl(bet)));
        rules.append(node('span','','Rollover do bônus: '+rollover.toLocaleString('pt-BR')+'x'));
        card.append(rules);
        if(finale)card.setAttribute('aria-label',`Recompensa especial do dia ${day}: ${rewardLabel}. ${done?'Recebido':available?'Disponível':'Bloqueado'}`);
        grid.append(card);
      }
      content.append(grid);
      if(!ordered.length)content.append(node('p','mz-promo-note','Nenhum dia de check-in está habilitado no Admin.'));
      const button=node('button','mz-promo-action mz-checkin-claim',!status?'Entre para resgatar':claimed?'✓ Recompensa já recebida':ordered.length?'🎁 Resgatar recompensa':'Check-in indisponível');
      button.type='button';button.disabled=!status||claimed||!ordered.length;content.append(button);
      if(status&&!claimed&&ordered.length){button.addEventListener('click',async()=>{
        button.disabled=true;button.textContent='Processando resgate…';
        try{
          const data=await userApi('/api/promotions/checkin/redeem',{});
          info(box,`Check-in resgatado: ${brl(data.award.amount_minor)} na conta ${data.award.account_type}. ${data.award.status==='LOCKED'?'Apostas necessárias para liberação: '+brl(data.award.wager_required_minor):''}`,true);
          document.dispatchEvent(new Event('mz:wallet-updated'));
          const updated=await userApi('/api/promotions/status');render(updated.days||[],updated);
        }catch(error){info(box,error.message);button.disabled=false;button.textContent='🎁 Resgatar recompensa';}
      });}
      if(!status)content.append(node('p','mz-checkin-login','Faça login para consultar sua sequência e resgatar recompensas.'));
    };
    if(!token()){
      const publicDays=configs.filter(item=>item.type==='checkin').map(item=>({title:item.title,config:item.config}));
      render(publicDays,null);return;
    }
    try{const status=await userApi('/api/promotions/status');render(status.days||[],status);}
    catch(error){info(box,error.message);render([],null);}
  }

  async function chestSection(container){
    const shell=node('section','mz-chests');container.append(shell);
    if(!token()){shell.append(node('div','mz-chest-hero','🎁 Entre na sua conta para obter o link de indicação e acompanhar seus baús.'));return;}
    const render=async()=>{
      const data=await userApi('/api/promotions/chests/status');shell.replaceChildren();
      const hero=node('div','mz-chest-hero');hero.append(node('span','mz-chest-kicker','✦ PROGRAMA DE INDICAÇÕES'),node('h2','','Baú do Tesouro'),node('p','',`Indicados cadastrados: ${Number(data.total_referrals||0)} · Cada baú exige depósitos confirmados de amigos cadastrados pelo seu link.`));
      const link=window.location.origin+base+'/?ref='+encodeURIComponent(data.code);
      const share=node('div','mz-chest-share');const field=node('input','mz-chest-link');field.readOnly=true;field.value=link;field.setAttribute('aria-label','Seu link exclusivo de indicação');
      const copy=node('button','mz-chest-copy','Copiar link');copy.type='button';copy.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(link);copy.textContent='✓ Copiado';}catch(e){field.select();info(shell,'Selecione e copie o link exibido.');}});
      share.append(field,copy);hero.append(share);shell.append(hero);
      const grid=node('div','mz-chests-grid');
      for(const chest of data.campaigns||[]){
        const done=!!chest.claimed,available=!!chest.available;
        const card=node('article','mz-chest-card '+(done?'is-claimed':available?'is-available':'is-locked'));
        const state=node('span','mz-chest-state',done?'✓ Resgatado':available?'✦ Disponível':'🔒 Bloqueado');
        card.append(state,node('div','mz-chest-icon',done?'✅':available?'🎁':'🗝️'),node('h3','',chest.title),node('strong','mz-chest-bonus',brl(chest.bonus_cents)),node('p','',`${chest.qualified} / ${chest.required} indicados qualificados`));
        const progress=node('div','mz-chest-progress');progress.setAttribute('role','progressbar');progress.setAttribute('aria-label','Progresso do baú');progress.setAttribute('aria-valuenow',String(Math.min(chest.qualified,chest.required)));progress.setAttribute('aria-valuemax',String(chest.required));const fill=node('span');fill.style.width=Math.min(100,100*chest.qualified/chest.required)+'%';progress.append(fill);card.append(progress);
        card.append(node('small','',`Depósito confirmado por indicado: ${brl(chest.deposit_min_cents)}`),node('small','',`Rollover do bônus: ${Number(chest.rollover_x).toLocaleString('pt-BR')}x`));
        const button=node('button','mz-chest-claim',done?'Já resgatado':available?'Resgatar baú':'Meta pendente');button.type='button';button.disabled=done||!available;
        if(available)button.addEventListener('click',async()=>{button.disabled=true;button.textContent='Processando…';try{const result=await userApi('/api/promotions/chests/redeem',{campaign_id:chest.id});document.dispatchEvent(new Event('mz:wallet-updated'));await render();info(shell,`Baú resgatado: ${brl(result.award.amount_minor)} creditados ${result.award.status==='LOCKED'?'na conta bônus, sujeitos ao rollover.':'na carteira.'}`,true);}catch(e){info(shell,e.message);button.disabled=false;button.textContent='Resgatar baú';}});
        card.append(button);grid.append(card);
      }
      if(!data.campaigns?.length)shell.append(node('p','mz-promo-note','Nenhum baú ativo no momento. O administrador deve publicar as metas e os depósitos mínimos.'));
      shell.append(grid);
    };
    try{await render();}catch(e){info(shell,e.message);}
  }

  async function agencySection(container){
    const shell=node('section','mz-agency');container.append(shell);
    if(!token()){shell.append(node('div','mz-agency-hero','🤝 Entre para consultar seus indicados e comissões.'));return;}
    try{
      const data=await userApi('/api/promotions/agency/status');
      const hero=node('div','mz-agency-hero');hero.append(node('span','','✦ PROGRAMA DE AGÊNCIA'),node('h2','','Minha agência'),node('p','',data.settings.enabled?'Comissões de indicações diretas habilitadas.':'Comissões temporariamente desativadas. Seus dados continuam disponíveis.'));
      const link=window.location.origin+base+'/?ref='+encodeURIComponent(data.code||'');
      const share=node('div','mz-chest-share');const field=node('input','mz-chest-link');field.readOnly=true;field.value=link;field.setAttribute('aria-label','Link de indicação');
      const copy=node('button','mz-chest-copy','Copiar link');copy.type='button';copy.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(link);copy.textContent='✓ Copiado';}catch(error){field.select();info(shell,'Selecione e copie o link de indicação.');}});
      share.append(field,copy);hero.append(share);shell.append(hero);
      const grid=node('div','mz-agency-stats');for(const [label,value] of [['Indicados cadastrados',String(data.total_referrals)],['Volume da equipe',brl(data.team_bet_minor)],['Comissões pagas',brl(data.total_commission_minor)],['Saldo de afiliado',brl(data.affiliate_balance_minor)]]){const card=node('article','mz-agency-stat');card.append(node('span','',label),node('strong','',value));grid.append(card);}shell.append(grid);
      const level=node('div','mz-agency-level');level.append(node('h3','',data.level?'Faixa atual: '+data.level.title:'Faixa de comissão pendente'),node('p','',data.level?`Taxa: ${Number(data.level.rate).toLocaleString('pt-BR')}% · Meta: ${brl(data.level.threshold)}`:'O volume da equipe ainda não atingiu a primeira meta publicada.'));shell.append(level);
      const tabs=node('div','mz-agency-tabs');const body=node('div','mz-agency-body');shell.append(tabs,body);
      const sections=[['Equipe',()=>{body.append(node('h3','','Minha equipe'));if(!data.referrals.length)body.append(node('p','','Nenhum cadastro pelo seu link até agora.'));for(const entry of data.referrals){const row=node('div','mz-agency-line');const name=String(entry.username||'Jogador');row.append(node('span','',name.slice(0,2)+'*** · '+String(entry.created_at).slice(0,10)),node('strong','',`Depósitos confirmados: ${brl(entry.deposits_minor)}`));body.append(row);}}],['Comissões',()=>{body.append(node('h3','','Histórico de comissões'));if(!data.history.length)body.append(node('p','','Nenhuma comissão processada até agora.'));for(const entry of data.history){const row=node('div','mz-agency-line');row.append(node('span','',`${String(entry.created_at).slice(0,16)} · Aposta ${brl(entry.bet_minor)} · ${Number(entry.commission_percent).toLocaleString('pt-BR')}%`),node('strong','',brl(entry.amount_minor)));body.append(row);}}],['Faixas',()=>{body.append(node('h3','','Faixas publicadas'));for(const tier of data.tiers){const row=node('div','mz-agency-line');row.append(node('span','',`${tier.title} · Meta ${brl(tier.threshold)}`),node('strong','',Number(tier.rate).toLocaleString('pt-BR')+'%'));body.append(row);}if(!data.tiers.length)body.append(node('p','','Nenhuma faixa ativa no momento.'));}]];
      sections.forEach(([label,render],index)=>{const btn=node('button','mz-agency-tab'+(!index?' active':''),label);btn.type='button';btn.addEventListener('click',()=>{tabs.querySelectorAll('button').forEach(item=>item.classList.remove('active'));btn.classList.add('active');body.replaceChildren();render();});tabs.append(btn);});sections[0][1]();
    }catch(error){info(shell,error.message);}
  }

  async function rebateSection(container){
    const shell=node('section','mz-rebate');container.append(shell);
    if(!token()){shell.append(node('p','mz-promo-note','Entre na sua conta para consultar o rebate acumulado e a taxa fixa.'));return;}
    const render=async()=>{
      const data=await userApi('/api/promotions/rebate/status');shell.replaceChildren();
      const hero=node('div','mz-rebate-hero');hero.append(node('span','mz-rebate-eyebrow','↺ RETORNO POR APOSTAS'),node('h2','','Rebate do Site'),node('p','',data.settings.enabled?'Apostas válidas confirmadas geram retorno pela porcentagem fixa configurada no Admin.':'A campanha está desativada no momento. O saldo acumulado continua registrado.'));
      const stats=node('div','mz-rebate-stats');for(const [label,value] of [['Volume de apostas',brl(data.volume_minor)],['Rebate disponível',brl(data.available_minor)],['Total resgatado',brl(data.claimed_minor)],['Taxa fixa',(Number(data.settings.rate_basis_points||0)/100).toLocaleString('pt-BR')+'%']]){const tile=node('div','mz-rebate-stat');tile.append(node('span','',label),node('strong','',value));stats.append(tile);}hero.append(stats);shell.append(hero);
      const claim=node('section','mz-rebate-claim');claim.append(node('h3','','Seu resgate'),node('p','',`Mínimo para receber: ${brl(data.settings.min_claim_minor)} · O valor é creditado no saldo disponível sem rollover adicional.`));
      const button=node('button','mz-rebate-btn','Receber '+brl(data.available_minor));button.type='button';button.disabled=!data.settings.enabled||Number(data.available_minor)<Number(data.settings.min_claim_minor);
      button.addEventListener('click',async()=>{button.disabled=true;try{const res=await userApi('/api/promotions/rebate/redeem',{});document.dispatchEvent(new Event('mz:wallet-updated'));await render();info(shell,`Rebate de ${brl(res.award.amount_minor)} creditado na carteira.`,true);}catch(error){info(shell,error.message);button.disabled=false;}});claim.append(button);shell.append(claim);
      const historyBox=node('details','mz-rebate-history');historyBox.append(node('summary','','Histórico de resgates'));if(!data.history.length)historyBox.append(node('p','','Nenhum resgate ainda.'));
      for(const entry of data.history){const line=node('div','mz-agency-line');line.append(node('span','',String(entry.created_at).slice(0,16)),node('strong','',brl(entry.amount_minor)));historyBox.append(line);}shell.append(historyBox);
    };
    try{await render();}catch(error){info(shell,error.message);}
  }

  async function rescueSection(container){
    const shell=node('section','mz-rescue');container.append(shell);
    if(!token()){shell.append(node('p','mz-promo-note','Faça login para consultar seus fundos de resgate.'));return;}
    const render=async()=>{
      const data=await userApi('/api/promotions/rescue/status');shell.replaceChildren();
      const hero=node('div','mz-rescue-hero');hero.append(node('span','mz-rebate-eyebrow','✦ FUNDO DE RECUPERAÇÃO'),node('h2','','Fundos de Resgate'),node('p','',data.settings.enabled?'As perdas líquidas elegíveis do dia anterior são calculadas sobre apostas e ganhos confirmados.':'Campanha desativada no momento.'));
      const day=node('span','mz-rescue-period','Período apurado: '+String(data.period_key).split('-').reverse().join('/'));hero.append(day);
      const current=data.current||{};
      const stat=node('div','mz-rebate-stats');for(const [label,value] of [['Apostas do período',brl(current.bet_minor)],['Ganhos do período',brl(current.win_minor)],['Perda líquida',brl(current.loss_minor)],['Recompensa apurada',brl(current.amount_minor)]]){const tile=node('div','mz-rebate-stat');tile.append(node('span','',label),node('strong','',value));stat.append(tile);}hero.append(stat);shell.append(hero);
      const claim=node('section','mz-rescue-claim'+(current.state==='AVAILABLE'?' is-available':''));claim.append(node('h3','','Fundo disponível'),node('strong','mz-rescue-amount',brl(current.amount_minor)));
      const message=current.state==='CLAIMED'?'Recompensa já resgatada.':current.state==='AVAILABLE'?`Resgate disponível somente hoje · rollover: ${Number(current.rollover_x||0).toLocaleString('pt-BR')}x`:data.settings.enabled?'Sem recompensa elegível para o período anterior.':'Fundos de resgate indisponíveis.';
      claim.append(node('p','',message));const button=node('button','mz-rebate-btn',current.state==='CLAIMED'?'Resgatado':'Receber recompensa');button.type='button';button.disabled=current.state!=='AVAILABLE'||!data.settings.enabled;
      button.addEventListener('click',async()=>{button.disabled=true;try{const response=await userApi('/api/promotions/rescue/redeem',{});document.dispatchEvent(new Event('mz:wallet-updated'));await render();info(shell,`Fundo de ${brl(response.award.amount_minor)} creditado ${response.award.status==='LOCKED'?'na conta bônus com rollover.':'na carteira.'}`,true);}catch(error){info(shell,error.message);button.disabled=false;}});claim.append(button);shell.append(claim);
      const levels=node('section','mz-rescue-tiers');levels.append(node('h3','','Faixas de resgate'));
      if(!data.tiers.length)levels.append(node('p','','Nenhuma faixa habilitada no Admin.'));
      for(const tier of data.tiers){const row=node('div','mz-rescue-tier');row.append(node('span','',tier.title+' · Perda mínima '+brl(tier.minimum_minor)),node('strong','',(Number(tier.basis_points)/100).toLocaleString('pt-BR')+'%'));levels.append(row);}shell.append(levels);
      const history=node('details','mz-rebate-history');history.append(node('summary','','Histórico dos períodos'));
      if(!data.history.length)history.append(node('p','','Nenhum período apurado.'));
      for(const entry of data.history){const state={INELIGIBLE:'Não elegível',AVAILABLE:'Disponível',CLAIMED:'Resgatado',EXPIRED:'Expirado'}[entry.state]||entry.state;const row=node('div','mz-rescue-tier');row.append(node('span','',`${entry.period_key} · ${state} · Perda ${brl(entry.loss_minor)}`),node('strong','',brl(entry.amount_minor)));history.append(row);}shell.append(history);
    };
    try{await render();}catch(error){info(shell,error.message);}
  }

  async function vipSection(container){
    const shell=node('section','mz-vip');container.append(shell);
    const paint=(payload,logged)=>{
      shell.replaceChildren();
      const levels=logged?payload.levels:[...configs.filter(v=>v.type==='vip')].map(v=>({id:0,title:v.title,level:Number(v.config.level||0),goal_minor:Number(v.config.goal_cents||0),bonus_minor:Number(v.config.bonus_cents||0),rollover_x:Number(v.config.rollover_x||0),reached:false,award:null})).sort((a,b)=>a.goal_minor-b.goal_minor);
      const volume=logged?Number(payload.volume.total_minor||0):0;
      const current=logged?Number(payload.current_level||0):0;
      const next=levels.find(level=>level.goal_minor>volume);
      const hero=node('div','mz-vip-hero');hero.append(node('span','mz-vip-eyebrow','♛ CLUBE DE BENEFÍCIOS'),node('h2','','VIP '+current),node('p','','Nível atual · metas calculadas por apostas válidas confirmadas.'));
      const totals=node('div','mz-vip-totals');totals.append(node('div','','Apostas acumuladas'),node('strong','',logged?brl(volume):'Entre para consultar'),node('div','','Apostas neste mês'),node('strong','',logged?brl(payload.volume.month_minor):'—'));hero.append(totals);
      if(next){const p=node('div','mz-vip-progress');p.setAttribute('role','progressbar');p.setAttribute('aria-valuenow',String(volume));p.setAttribute('aria-valuemax',String(next.goal_minor));p.setAttribute('aria-label','Progresso de apostas VIP');const fill=node('span');fill.style.width=(logged?Math.min(100,volume/next.goal_minor*100):0)+'%';p.append(fill);hero.append(p,node('small','',`Próxima meta: VIP ${next.level} • ${brl(next.goal_minor)}`));}
      else if(logged&&levels.length)hero.append(node('small','','Todas as metas VIP ativas foram alcançadas.'));
      shell.append(hero);
      const intro=node('div','mz-vip-title');intro.append(node('h3','','Níveis e recompensas'),node('span','',`${levels.length} ${levels.length===1?'nível ativo':'níveis ativos'}`));shell.append(intro);
      if(!levels.length){shell.append(node('p','mz-vip-empty','Não há níveis VIP ativos no painel administrativo.'));return;}
      const grid=node('div','mz-vip-grid');
      for(const level of levels){const reached=logged&&level.reached,claimed=!!level.award;
        const card=node('article','mz-vip-card'+(claimed?' is-claimed':reached?' is-reached':''));
        card.append(node('span','mz-vip-badge',claimed?'✓ RECEBIDO':reached?'✦ META ATINGIDA':'🔒 EM PROGRESSO'),node('h4','',level.title),node('p','','Meta: '+brl(level.goal_minor)),node('strong','','Bônus de upgrade: '+brl(level.bonus_minor)),node('small','','Rollover: '+Number(level.rollover_x).toLocaleString('pt-BR')+'x'));
        const btn=node('button','mz-vip-claim',claimed?'Resgatado':reached&&level.bonus_minor>0?'Resgatar bônus':level.bonus_minor<=0?'Bônus não configurado':'Meta pendente');btn.type='button';btn.disabled=!reached||claimed||level.bonus_minor<=0;
        if(!btn.disabled)btn.addEventListener('click',async()=>{btn.disabled=true;btn.textContent='Processando…';try{const data=await userApi('/api/promotions/vip/redeem',{campaign_id:level.id});document.dispatchEvent(new Event('mz:wallet-updated'));const [updated,benefits]=await Promise.all([userApi('/api/promotions/vip/status'),userApi('/api/promotions/vip/benefits')]);paint({...updated,...benefits,benefit_settings:benefits.settings,membership:benefits.member,current_level:benefits.member.effective_level},true);info(shell,`Bônus VIP de ${brl(data.award.amount_minor)} creditado ${data.award.status==='LOCKED'?'na conta bônus, sujeito ao rollover.':'na carteira.'}`,true);}catch(error){info(shell,error.message);btn.disabled=false;btn.textContent='Resgatar bônus';}});
        card.append(btn);grid.append(card);
      }shell.append(grid);
      if(logged){
        const benefits=node('section','mz-vip-benefits');benefits.append(node('h3','','Benefícios recorrentes'));
        const settings=payload.benefit_settings||{},membership=payload.membership||{};
        benefits.append(node('p','mz-vip-benefits-note',!Number(settings.enabled)?'Benefícios recorrentes desativados no Admin.':membership.suspended?'Manutenção não cumprida: benefícios suspensos até atingir a meta do mês.':`Regra de manutenção: ${settings.maintenance_mode==='downgrade'?'redução mensal':'VIP vitalício'} · Nível efetivo: VIP ${membership.effective_level||0}`));
        const benefitGrid=node('div','mz-vip-benefit-grid');
        for(const [kind,label] of [['daily','Diário'],['weekly','Semanal'],['monthly','Mensal']]){
          const current=payload.benefits?.find(item=>item.kind===kind&&item.period_key===payload.periods?.[kind]);
          const tile=node('article','mz-vip-benefit-card');tile.append(node('strong','',`Bônus ${label}`),node('b','',current?brl(current.amount_minor):'Indisponível'),node('small','',current?`Período ${current.period_key} · ${current.state==='CLAIMED'?'Recebido':`Rollover ${Number(current.rollover_x)}x`}`:'Sem recompensa liberada'));
          const btn=node('button','mz-vip-claim',current?.state==='AVAILABLE'?'Resgatar':current?.state==='CLAIMED'?'Resgatado':'Indisponível');btn.type='button';btn.disabled=current?.state!=='AVAILABLE';
          if(!btn.disabled)btn.addEventListener('click',async()=>{btn.disabled=true;try{const result=await userApi('/api/promotions/vip/benefits/redeem',{kind});document.dispatchEvent(new Event('mz:wallet-updated'));const [vip,b]=await Promise.all([userApi('/api/promotions/vip/status'),userApi('/api/promotions/vip/benefits')]);paint({...vip,...b,benefit_settings:b.settings,membership:b.member},true);info(shell,`Bônus ${label} creditado: ${brl(result.award.amount_minor)}.`,true);}catch(e){info(shell,e.message);btn.disabled=false;}});
          tile.append(btn);benefitGrid.append(tile);
        }
        benefits.append(benefitGrid);
        if(payload.reviews?.length){const hist=node('div','mz-vip-review-history');hist.append(node('h4','','Histórico de manutenção'));for(const row of payload.reviews.slice(0,6))hist.append(node('p','',`${row.period_key} • VIP ${row.before_level} → ${row.after_level} • Apostas: ${brl(row.volume_minor)} / ${brl(row.required_minor)} • ${row.rule_applied}`));benefits.append(hist);}
        shell.append(benefits);
      }
    };
    if(!token()){paint({},false);return;}
    try{const [vip,b]=await Promise.all([userApi('/api/promotions/vip/status'),userApi('/api/promotions/vip/benefits')]);paint({...vip,...b,benefit_settings:b.settings,membership:b.member,current_level:b.member.effective_level},true);}catch(error){info(shell,error.message);}
  }


  async function rouletteSection(container){
    const shell=node('section','mz-roulette mz-promo-box');container.append(shell);
    const spinDuration=ms=>Number(ms||4600);
    const buildMessage=result=>{
      if(Number(result?.award?.amount_minor||0)<=0)return 'Você girou e não ganhou prêmio desta vez.';
      const rollover=result.award.status==='LOCKED' ? ` Rollover necessário: ${brl(result.award.wager_required_minor)}.` : '';
      return `Parabéns! Você ganhou ${brl(result.award.amount_minor)}.${rollover}`;
    };
    async function render(){
      shell.replaceChildren();shell.append(node('h3','','🎯 Giro da Sorte'));
      if(!token()){shell.append(node('p','mz-promo-note','Entre na sua conta para consultar rodadas e participar.'));return;}
      let payload;
      try{payload=await userApi('/api/promotions/roulette/status');}catch(error){info(shell,error.message);return;}
      if(!payload.campaigns?.length){shell.append(node('p','mz-promo-note','Nenhuma campanha ativa no momento.'));return;}
      for(const campaign of payload.campaigns){
        const panel=node('article','mz-roulette-campaign');panel.append(node('h4','','Giro da Sorte'));
        const badge=node('div','mz-roulette-count',`${campaign.available_spins} ${campaign.available_spins===1?'rodada disponível':'rodadas disponíveis'}`);panel.append(badge);
        const cfg=campaign.config||{};
        let currentRotation=0;
        const wheelWrap=node('div','mz-roulette-stage');
        const pointer=node('div','mz-roulette-pointer','▼');pointer.setAttribute('aria-hidden','true');wheelWrap.append(pointer);
        const wheel=node('div','mz-roulette-wheel');wheel.setAttribute('role','img');wheel.setAttribute('aria-label','Giro da Sorte com 8 partes; o resultado é validado no servidor.');
        (cfg.segments||[]).forEach((segment,index)=>{
          const labelWrap=node('div','mz-roulette-label-wrap');
          labelWrap.style.setProperty('--segment-angle',`${index*45}deg`);
          const label=node('span','mz-roulette-segment-label '+(segment.kind==='win'?'is-win':'is-lose'),segment.kind==='win'?segment.label:'Sem prêmio');
          labelWrap.append(label);wheel.append(labelWrap);
        });
        wheel.append(node('span','mz-roulette-wheel-center','🎁'));wheelWrap.append(wheel);panel.append(wheelWrap);
        const chance=Number(cfg.win_chance_percent||50);
        panel.append(node('p','mz-promo-note',`Chance total de ganho: ${chance.toFixed(chance % 1 ? 2 : 0)}%. Prêmios distribuídos em 4 faixas entre ${brl(cfg.reward_min_cents)} e ${brl(cfg.reward_max_cents)}.`));
        const sources=[];
        if(Number(cfg.spins_per_deposit)>0)sources.push(`${cfg.spins_per_deposit} rodada(s) por depósito pago de pelo menos ${brl(cfg.deposit_min_cents)}`);
        if(Number(cfg.spins_per_referral)>0)sources.push(`${cfg.spins_per_referral} rodada(s) por novo jogador cadastrado pelo seu link`);
        panel.append(node('p','mz-promo-note',sources.join(' • ')));
        const resultBox=node('div','mz-roulette-result','✨ Pronto para girar e testar sua sorte?');
        panel.append(resultBox);
        const button=node('button','mz-promo-action mz-roulette-spin',campaign.available_spins?'🎯 Girar agora':'Sem rodadas disponíveis');button.type='button';button.disabled=!campaign.available_spins;
        button.addEventListener('click',async()=>{
          panel.classList.remove('is-win','is-loss');
          resultBox.className='mz-roulette-result is-spinning';
          resultBox.textContent='🎡 Girando... boa sorte!';
          button.disabled=true;button.textContent='Girando...';
          try{
            const result=await userApi('/api/promotions/roulette/spin',{campaign_id:campaign.id});
            const segments=(result.display?.segments||cfg.segments||[]);
            const selectedIndex=Number(result.display?.selected_index||0);
            const perSegment=360/Math.max(segments.length||8,1);
            const spins=7;
            const normalized=((currentRotation%360)+360)%360;
            const delta=(360-((normalized+(selectedIndex*perSegment))%360))%360;
            currentRotation=currentRotation+(spins*360)+delta;
            wheel.querySelectorAll('.mz-roulette-label-wrap').forEach(el=>el.classList.remove('is-selected'));
            wheel.classList.add('is-spinning');
            wheel.style.transform=`rotate(${currentRotation}deg)`;
            document.dispatchEvent(new Event('mz:wallet-updated'));
            const delay=window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 120 : spinDuration(result.display?.delay_ms);
            await new Promise(resolve=>setTimeout(resolve,delay));
            wheel.classList.remove('is-spinning');
            const labels=wheel.querySelectorAll('.mz-roulette-label-wrap');
            if(labels[selectedIndex])labels[selectedIndex].classList.add('is-selected');
            const won=Number(result.award?.amount_minor||0)>0;
            panel.classList.add(won?'is-win':'is-loss');
            resultBox.className='mz-roulette-result '+(won?'is-win':'is-loss');
            resultBox.textContent=won?`🎉 Você ganhou ${brl(result.award.amount_minor)}!`:'😕 Não foi desta vez. Tente novamente quando tiver nova rodada!';
            const remaining=Number(result.remaining_spins||0);
            badge.textContent=`${remaining} ${remaining===1?'rodada disponível':'rodadas disponíveis'}`;
            button.disabled=remaining<=0;
            button.textContent=remaining>0?'🎯 Girar novamente':'Sem rodadas disponíveis';
            const history=shell.querySelector('.mz-roulette-history');
            if(history){
              const desc=Number(result.award?.amount_minor||0)>0?brl(result.award.amount_minor):'Sem prêmio';
              const entry=node('p','',`${new Date().toISOString().slice(0,16).replace('T',' ')} • ${desc} • ${result.award?.status==='LOCKED'?'Bônus com rollover':'Concluído'}`);
              history.insertBefore(entry,history.children[1]||null);
            }
          }catch(error){info(panel,error.message);button.disabled=false;button.textContent='🎯 Girar agora';}
        });
        panel.append(button);shell.append(panel);
      }
      if(payload.history?.length){const list=node('div','mz-roulette-history');list.append(node('h4','','Minhas rodadas'));
        for(const spin of payload.history.slice(0,12)){const desc=Number(spin.prize_minor||0)>0?brl(spin.prize_minor):'Sem prêmio';list.append(node('p','',`${String(spin.created_at).slice(0,16)} • ${desc} • ${spin.status==='LOCKED'?'Bônus com rollover':'Concluído'}`));}shell.append(list);
      }
    }
    await render();
  }


  async function envelopeSection(container){
    const shell=node('section','mz-envelope mz-promo-box');container.append(shell);
    const paint=async()=>{
      shell.replaceChildren();shell.append(node('h3','','✉️ Envelope Vermelho'));
      if(!token()){shell.append(node('p','mz-promo-note','Entre na sua conta para consultar o Envelope Vermelho de hoje.'));return;}
      let data;
      try{data=await userApi('/api/promotions/envelope/status');}catch(error){info(shell,error.message);return;}
      if(!data.active||!data.campaign){shell.append(node('p','mz-promo-note','Nenhum Envelope Vermelho ativo no momento.'));return;}
      const cfg=data.campaign.config||{};
      const card=node('article','mz-envelope-card'+(data.available?' is-available':data.claimed_today?' is-claimed':''));
      const glow=node('div','mz-envelope-glow');card.append(glow);
      const flap=node('div','mz-envelope-flap');card.append(flap);
      const coin=node('div','mz-envelope-coin','🎁');card.append(coin);
      const title=node('h4','',data.available?'Seu envelope está disponível':data.claimed_today?'Envelope de hoje já aberto':'Envelope indisponível');card.append(title);
      const message=node('p','mz-envelope-message',String(cfg.message||'Abra seu envelope diário e descubra sua recompensa.'));card.append(message);
      const ranges=node('div','mz-envelope-ranges');
      ranges.append(node('span','',`Base: ${brl(cfg.reward_min_cents)} – ${brl(cfg.reward_max_cents)}`),node('span','',`Multiplicador: ${Number(cfg.multiplier_min||0).toLocaleString('pt-BR')}x – ${Number(cfg.multiplier_max||0).toLocaleString('pt-BR')}x`),node('span','',`Rollover: ${Number(cfg.rollover_x||0).toLocaleString('pt-BR')}x`));card.append(ranges);
      const action=node('button','mz-envelope-open',data.available?'Abrir envelope':data.claimed_today?'Volte amanhã':'Indisponível');action.type='button';action.disabled=!data.available;card.append(action);shell.append(card);
      const result=node('div','mz-envelope-result hidden');shell.append(result);
      if(data.today){result.classList.remove('hidden');result.textContent=`Hoje: ${brl(data.today.final_amount_minor)} • multiplicador ${Number(data.today.multiplier).toLocaleString('pt-BR')}x`;}
      action.addEventListener('click',async()=>{
        action.disabled=true;action.textContent='Abrindo...';card.classList.add('is-opening');
        try{
          const res=await userApi('/api/promotions/envelope/claim',{});
          await new Promise(resolve=>setTimeout(resolve,900));
          card.classList.remove('is-opening');card.classList.add('is-claimed');
          action.textContent='Envelope aberto';
          result.classList.remove('hidden');result.textContent=`🎉 Você recebeu ${brl(res.final_amount_minor)} (${brl(res.base_amount_minor)} × ${Number(res.multiplier).toLocaleString('pt-BR')}x).${res.award.status==='LOCKED'?' Rollover: '+brl(res.award.wager_required_minor)+'.':''}`;
          document.dispatchEvent(new Event('mz:wallet-updated'));
        }catch(error){card.classList.remove('is-opening');info(shell,error.message);action.disabled=false;action.textContent='Abrir envelope';}
      });
      if(data.history?.length){const hist=node('details','mz-envelope-history');hist.append(node('summary','','Histórico de envelopes'));for(const item of data.history.slice(0,10))hist.append(node('p','',`${item.day_key} • ${brl(item.final_amount_minor)} • ${Number(item.multiplier).toLocaleString('pt-BR')}x • ${item.status==='LOCKED'?'com rollover':'liberado'}`));shell.append(hist);}
    };
    await paint();
  }

  let envelopePopupBusy=false;
  function closeEnvelopePopup(){
    const popup=document.getElementById('mz-envelope-popup');
    if(!popup)return;
    popup.classList.add('is-closing');
    setTimeout(()=>popup.remove(),180);
  }
  async function maybeOpenEnvelope(){
    if(envelopePopupBusy||!token()||document.getElementById('mz-envelope-popup'))return;
    envelopePopupBusy=true;
    try{
      const data=await userApi('/api/promotions/envelope/status');
      if(!data?.available){envelopePopupBusy=false;return;}
      const overlay=node('div','mz-envelope-popup');overlay.id='mz-envelope-popup';overlay.setAttribute('role','dialog');overlay.setAttribute('aria-modal','true');overlay.setAttribute('aria-label','Envelope Vermelho disponível');
      const card=node('div','mz-envelope-popup-card');
      const close=node('button','mz-envelope-popup-close','×');close.type='button';close.setAttribute('aria-label','Fechar');close.addEventListener('click',closeEnvelopePopup);card.append(close);
      const sparkle=node('div','mz-envelope-popup-sparkles');for(let i=0;i<12;i++){const dot=node('i','');dot.style.setProperty('--i',String(i));sparkle.append(dot);}card.append(sparkle);
      const visual=node('button','mz-envelope-popup-visual');visual.type='button';visual.setAttribute('aria-label','Abrir Envelope Vermelho');
      visual.append(node('span','mz-envelope-popup-flap'),node('span','mz-envelope-popup-body'),node('span','mz-envelope-popup-seal','🎁'));
      card.append(visual);
      const title=node('h2','','Envelope Vermelho disponível!');card.append(title);
      const message=node('p','mz-envelope-popup-message',data.campaign?.message||'Você tem uma recompensa esperando por você.');card.append(message);
      const action=node('button','mz-envelope-popup-action','Abrir envelope');action.type='button';card.append(action);
      const result=node('div','mz-envelope-popup-result hidden');card.append(result);
      const open=async()=>{
        if(action.disabled)return;
        action.disabled=true;visual.disabled=true;action.textContent='Abrindo...';card.classList.add('is-opening');
        try{
          const res=await userApi('/api/promotions/envelope/claim',{});
          await new Promise(resolve=>setTimeout(resolve,1050));
          card.classList.remove('is-opening');card.classList.add('is-opened');
          title.textContent='Parabéns!';message.textContent='Seu Envelope Vermelho foi aberto.';
          result.classList.remove('hidden');result.textContent=`🎉 Você ganhou ${brl(res.final_amount_minor)}!`;
          action.textContent='Fechar';action.disabled=false;visual.disabled=true;
          action.onclick=closeEnvelopePopup;
          document.dispatchEvent(new Event('mz:wallet-updated'));
        }catch(error){
          card.classList.remove('is-opening');action.disabled=false;visual.disabled=false;action.textContent='Abrir envelope';
          result.classList.remove('hidden');result.textContent=error.message;
        }
      };
      visual.addEventListener('click',open);action.addEventListener('click',open);
      overlay.append(card);document.body.append(overlay);
      requestAnimationFrame(()=>overlay.classList.add('is-visible'));
    }catch{}finally{envelopePopupBusy=false;}
  }


  async function cashwheelSection(container){
    const shell=node('section','mz-cashwheel mz-promo-box');container.append(shell);
    const buildCashwheelSegments=(cfg,session,history=[])=>{
      const target=Number(cfg.target_cents||0),progress=Number(session.progress_minor||0);
      const first=!(history?.length);
      const min=Number(cfg.spin_min_cents||50),max=Number(cfg.spin_max_cents||1000);
      const laterMaxPct=Math.max(.01,Number(cfg.later_spin_max_percent||8));
      const firstMinPct=Math.max(1,Number(cfg.first_spin_min_percent||60));
      const firstMaxPct=Math.min(90,Math.max(firstMinPct,Number(cfg.first_spin_max_percent||90)));
      let low=first?Math.max(1,Math.floor(target*firstMinPct/100)):Math.max(1,min);
      let high=first?Math.floor(target*firstMaxPct/100):Math.min(max,Math.floor(target*laterMaxPct/100));
      const remaining=Math.max(0,target-progress);high=Math.max(low,Math.min(high,remaining||high));
      const vals=[];for(let i=0;i<6;i++)vals.push(Math.round(low+((high-low)*i/5)));
      return [
        {kind:'NO_WIN',label:'Não ganhou\nnada',amount:0},
        {kind:'CASH_BONUS',label:'Bônus\nem moeda',amount:null},
        ...vals.map(v=>({kind:'PROGRESS',label:'+'+brl(v).replace(',00',''),amount:v}))
      ];
    };
    const formatExpiry=value=>{const raw=String(value||'').replace('T',' ').slice(0,16);return raw||'—';};
    const animateWheel=(wheel,selectedIndex)=>{
      const segmentSize=45;
      const centerOffset=Number(selectedIndex||0)*segmentSize + segmentSize/2;
      const finalRotation=360*6 + (360-centerOffset);
      wheel.style.setProperty('--mz-cashwheel-spin-final', `${finalRotation}deg`);
      wheel.classList.remove('is-spinning'); void wheel.offsetWidth; wheel.classList.add('is-spinning');
    };
    const paint=async()=>{
      shell.replaceChildren();shell.append(node('h3','','💸 Roleta de Saque'));
      if(!token()){shell.append(node('p','mz-promo-note','Entre na sua conta para participar.'));return;}
      let data;try{data=await userApi('/api/promotions/cashwheel/status');}catch(e){info(shell,e.message);return;}
      if(!data.active){shell.append(node('p','mz-promo-note','Roleta de Saque indisponível no momento.'));return;}
      const cfg=data.campaign?.config||{},session=data.session||{};
      const target=Number(cfg.target_cents||0),progress=Number(session.progress_minor||0),pct=target>0?Math.min(100,(progress/target)*100):0;
      const card=node('article','mz-cashwheel-card mz-cashwheel-card-premium');
      const banner=node('div','mz-cashwheel-hero');
      const titleWrap=node('div','mz-cashwheel-hero-copy');
      titleWrap.append(node('span','mz-cashwheel-kicker','SAQUE RÁPIDO'),node('h4','mz-cashwheel-hero-title','Roleta Premium'),node('p','mz-cashwheel-hero-text','Gire, acumule saldo e alcance a meta para liberar seu resgate.'));
      banner.append(titleWrap,node('div','mz-cashwheel-hero-badge','PIX • PRÊMIOS'));
      card.append(banner);
      const top=node('div','mz-cashwheel-top');top.append(node('span','','Saldo da campanha'),node('strong','',brl(progress)));card.append(top);
      const meter=node('div','mz-cashwheel-meter');const fill=node('i','');fill.style.width=pct+'%';meter.append(fill);card.append(meter);
      const meterRow=node('div','mz-cashwheel-meter-row');meterRow.append(node('span','',`${pct.toFixed(2).replace('.',',')}% concluído`),node('strong','',`Meta ${brl(target)}`));card.append(meterRow);
      const stage=node('div','mz-cashwheel-stage');stage.append(node('div','mz-cashwheel-pointer','▼'));
      const wheelWrap=node('div','mz-cashwheel-wheel-wrap');
      const wheel=node('div','mz-cashwheel-wheel');
      const wheelSegments=buildCashwheelSegments(cfg,session,data.history||[]);wheelSegments.forEach((segment,index)=>{const label=node('span','mz-cashwheel-label mz-cashwheel-label-'+segment.kind.toLowerCase(),segment.label);label.style.setProperty('--seg',String(index));wheel.append(label);});
      wheel.append(node('span','mz-cashwheel-center','R$'));
      wheelWrap.append(node('div','mz-cashwheel-coins mz-cashwheel-coins-left','🪙'),node('div','mz-cashwheel-coins mz-cashwheel-coins-right','✨'),wheel,node('div','mz-cashwheel-base-glow',''));
      stage.append(wheelWrap);card.append(stage);
      const spins=node('div','mz-cashwheel-spins',`${Number(data.free_spins||0)} rodada(s) grátis disponível(is) hoje`);card.append(spins);
      const result=node('div','mz-cashwheel-result','Gire para aumentar seu saldo até a meta.');card.append(result);
      const spin=node('button','mz-promo-action mz-cashwheel-spin',data.free_spins>0 && progress<target?'🎡 GIRAR AGORA':'Sem rodadas disponíveis');spin.disabled=!(data.free_spins>0&&progress<target);card.append(spin);
      const claim=node('button','mz-cashwheel-claim',progress>=target?'💰 RESGATAR '+brl(target):'Resgate liberado ao atingir a meta');claim.disabled=progress<target;card.append(claim);
      const meta=node('div','mz-cashwheel-meta');meta.append(node('span','',`Validade: ${formatExpiry(session.expires_at)}`));if(Number(cfg.referral_bonus_cents||0)>0)meta.append(node('span','',`Indicação ativa ajuda +${brl(cfg.referral_bonus_cents)}`));meta.append(node('span','',`Faixa por giro: ${brl(Number(cfg.spin_min_cents||0))} até ${brl(Number(cfg.spin_max_cents||0))}`));card.append(meta);
      spin.addEventListener('click',async()=>{spin.disabled=true;spin.textContent='Girando...';result.textContent='🎯 Girando a roleta premium...';try{const r=await userApi('/api/promotions/cashwheel/spin',{});animateWheel(wheel,Number(r.selected_index||0));await new Promise(x=>setTimeout(x,4200));const newProgress=Number(r.session?.progress_minor||progress);const newPct=target>0?Math.min(100,(newProgress/target)*100):0;top.querySelector('strong').textContent=brl(newProgress);fill.style.width=newPct+'%';meterRow.querySelector('span').textContent=`${newPct.toFixed(2).replace('.',',')}% concluído`;spins.textContent=`${Number(r.free_spins||0)} rodada(s) grátis disponível(is) hoje`;if(r.reward_type==='NO_WIN'){result.textContent='😕 Não ganhou nada. Tente novamente no próximo giro.';}else if(r.reward_type==='CASH_BONUS'){result.textContent=`🎁 Você ganhou ${brl(r.prize_minor)} em bônus direto!`;document.dispatchEvent(new Event('mz:wallet-updated'));}else{result.textContent=`🎉 Você avançou ${brl(r.prize_minor)} rumo à meta!`;}const reached=newProgress>=target;claim.disabled=!reached;claim.textContent=reached?`💰 RESGATAR ${brl(target)}`:'Resgate liberado ao atingir a meta';spin.disabled=Number(r.free_spins||0)<=0||reached;spin.textContent=spin.disabled?'Sem rodadas disponíveis':'🎡 GIRAR AGORA';}catch(e){result.textContent=e.message;spin.disabled=false;spin.textContent='🎡 GIRAR AGORA';}});
      claim.addEventListener('click',async()=>{claim.disabled=true;claim.textContent='Resgatando...';try{const r=await userApi('/api/promotions/cashwheel/claim',{});document.dispatchEvent(new Event('mz:wallet-updated'));result.textContent=`✅ ${brl(r.award.amount_minor)} resgatados com sucesso!`;claim.textContent='Resgatado';}catch(e){result.textContent=e.message;claim.disabled=false;claim.textContent='💰 RESGATAR '+brl(target);}});
      shell.append(card);
      if(data.history?.length){const hist=node('details','mz-cashwheel-history');hist.append(node('summary','','Histórico de giros'));for(const h of data.history.slice(0,10)){const desc=h.reward_type==='NO_WIN'?'Sem prêmio':h.reward_type==='CASH_BONUS'?`Bônus direto ${brl(h.prize_minor)}`:`Avanço +${brl(h.prize_minor)}`;hist.append(node('p','',`${String(h.created_at).slice(0,16)} • ${desc}`));}shell.append(hist);}
    };
    await paint();
  }

  // Regras educativas: somente campos publicados pelo Admin; módulos não ativados não prometem créditos.
  const ruleTexts={
    agency:['Somente jogadores cadastrados pelo seu link exclusivo integram sua equipe direta.','As comissões incidem apenas sobre apostas válidas e confirmadas depois da ativação administrativa; depósitos não geram comissão de agência.','O percentual aplicado depende da faixa alcançada no momento da aposta e é registrado no histórico. O pagamento é creditado no saldo de afiliado pela rotina do servidor.','Baús e comissão da agência possuem condições separadas: o Baú do Tesouro exige depósito mínimo por indicado.'],
    chests:['Compartilhe seu link exclusivo; apenas contas novas cadastradas por ele entram na sua lista de indicados.','Cada indicado deve realizar depósitos confirmados cuja soma atinja o mínimo configurado para o baú; depósitos pendentes ou cancelados não contam.','Ao atingir a quantidade de amigos qualificados da meta, resgate o baú uma vez. O bônus é creditado na carteira, sujeito ao rollover definido no Admin.'],
    rebate:['A taxa é fixa para todos os jogadores, sem níveis nem metas de volume. A porcentagem é definida no painel administrativo.','Cada aposta válida confirmada após a ativação gera rebate proporcional ao valor apostado, independentemente do resultado.','A taxa usada é a vigente no momento da aposta. Frações de centavo são acumuladas e só viram saldo resgatável ao atingir um centavo.','O resgate depende do mínimo configurado, credita a carteira CASH e não exige rollover adicional. Apostas anteriores à ativação não geram rebate.'],
    coupons:['Digite o código recebido de um canal oficial e clique em Trocar código.','Cada código pode ser resgatado uma única vez por conta e está sujeito ao estoque disponível.','O valor recebido e a exigência de apostas (rollover) dependem do cupom configurado; o resgate exibe o resultado.'],
    checkin:['A sequência é diária; a virada ocorre às 21h no horário de Brasília. Se perder um dia, a sequência recomeça.','Cada card mostra sua recompensa, o depósito mínimo, as apostas necessárias e o rollover configurados.','Para receber, cumpra os requisitos com transações confirmadas no período, faça login e use o botão de resgate; há um resgate por dia.'],
    rescue:['São consideradas apenas apostas e ganhos PlayFiver registrados como concluídos na conta CASH. A perda líquida do período é a diferença positiva entre apostas e ganhos; depósitos e bônus não entram no cálculo.','A apuração usa o dia anterior completo, de 00h a 00h no horário de Brasília. Somente dias a partir da data efetiva de ativação no Admin entram na campanha.','A maior faixa de perda mínima alcançada determina a taxa aplicada; a recompensa é arredondada para baixo em centavos.','O fundo é solicitado no dia seguinte ao da perda; se não for resgatado nesse dia, expira. Após receber, o bônus fica sujeito ao rollover indicado no card.'],
    vip:['O progresso de upgrade utiliza apostas válidas confirmadas e a meta acumulada de cada nível.','Cada nível pode ter bônus de upgrade, diário, semanal e mensal e uma meta de manutenção; valores não configurados não geram recompensa.','O programa pode preservar o nível e suspender benefícios ou aplicar redução mensal, conforme a regra definida no Admin.','Benefícios recorrentes são gerados pela rotina do servidor e precisam estar disponíveis antes do resgate; valores com rollover exigem apostas válidas para liberação.'],
    cashwheel:['Você recebe a quantidade diária de rodadas gratuitas definida no Admin. Resultados de avanço aumentam apenas o saldo interno da campanha e não entram na carteira.','O primeiro giro de avanço pode aproximar o saldo de até 90% da meta; os giros seguintes acrescentam valores menores. Também pode existir a opção "Não ganhou nada, tente novamente".','Somente a opção específica de bônus direto em moeda credita um prêmio antes da meta. Fora dessa opção, o saldo só pode ser resgatado após alcançar 100% da meta.','Indicações válidas podem acrescentar ajuda ao saldo interno. A campanha possui prazo de validade e, se a meta não for atingida, a sessão expira conforme a configuração.'],
    lottery:['A campanha pode prever giros por dia e recompensa por completar uma coleção de cartas.','A composição, o resultado e as condições de cada rodada serão definidos quando o sorteio for implementado.','Giros, coleção e resgates ainda não estão disponíveis nesta versão.'],
    roulette:['Depósitos PAID de valor igual ou superior ao mínimo dão rodadas; só contam pagamentos iniciados após a ativação.','Cada novo usuário ativo cadastrado pelo seu link após a ativação gera as rodadas configuradas, mesmo sem depósito. O mesmo evento não concede rodadas duas vezes.','O Admin define a chance total de ganho em %, o intervalo mínimo e máximo de prêmio e o rollover. A roleta exibe 4 faixas premiadas e 4 faixas sem prêmio apenas como representação visual.','Cada giro consome 1 rodada e o resultado é apurado no servidor; se houver prêmio, o crédito vai para CASH ou BONUS conforme o rollover configurado.'],
  };
  const ruleFields={
    agency:[['level','Faixa','integer'],['team_bet_min_cents','Meta de apostas da equipe','money'],['commission_percent','Comissão indicada','percent']],
    chests:[['referral_count','Indicados exigidos','integer'],['referred_deposit_min_cents','Depósito mínimo de cada indicado','money'],['bonus_cents','Recompensa','money'],['rollover_x','Rollover','multiple']],
    coupons:[['quantity','Quantidade total cadastrada','integer'],['bonus_min_cents','Bônus mínimo','money'],['bonus_max_cents','Bônus máximo','money'],['rollover_x','Rollover','multiple']],
    checkin:[['day','Dia','integer'],['reward_min_cents','Bônus mínimo','money'],['reward_max_cents','Bônus máximo','money'],['extra_cents','Recompensa extra','money'],['deposit_min_cents','Depósito mínimo no período','money'],['bet_min_cents','Apostas exigidas no período','money'],['rollover_x','Rollover','multiple']],
    rescue:[['level','Nível','integer'],['loss_min_cents','Perda mínima','money'],['refund_percent','Taxa prevista','percent'],['rollover_x','Rollover','multiple']],
    vip:[['level','VIP','integer'],['goal_cents','Meta acumulada','money'],['maintenance_cents','Manutenção mensal','money'],['bonus_cents','Upgrade','money'],['daily_bonus_cents','Diário','money'],['weekly_bonus_cents','Semanal','money'],['monthly_bonus_cents','Mensal','money'],['rollover_x','Rollover','multiple']],
    cashwheel:[['target_cents','Meta de saldo','money'],['duration_days','Validade (dias)','integer'],['free_spins_per_day','Rodadas por dia','integer'],['spin_min_cents','Prêmio mínimo por giro','money'],['spin_max_cents','Prêmio máximo por giro','money'],['referral_bonus_cents','Ajuda por indicação','money'],['rollover_x','Rollover','multiple']],
    lottery:[['spins_per_day','Rodadas por dia','integer'],['collection_bonus_cents','Prêmio da coleção','money'],['rollover_x','Rollover','multiple']],
    roulette:[['deposit_min_cents','Depósito mínimo','money'],['spins_per_deposit','Rodadas por depósito','integer'],['spins_per_referral','Rodadas por indicação','integer'],['win_chance_percent','Chance de ganho','percent'],['reward_min_cents','Prêmio mínimo previsto','money'],['reward_max_cents','Prêmio máximo previsto','money'],['rollover_x','Rollover','multiple']],
  };
  function appendRules(container,mod){
    const panel=node('details','mz-promo-rules');
    const title=node('summary','mz-promo-rules-heading');title.append(node('span','mz-promo-rules-icon','ⓘ'),node('strong','','Como funciona • Regras e requisitos'),node('span','mz-promo-rules-chevron','⌄'));
    panel.append(title);
    const body=node('div','mz-promo-rules-body');
    const steps=node('ol','mz-promo-rules-steps');for(const text of (ruleTexts[mod.id]||[]))steps.append(node('li','',text));body.append(steps);
    const active=mod.id==='rebate'?[]:configs.filter(item=>item.type===mod.type);
    if(active.length){
      body.append(node('h4','','Condições publicadas nesta campanha'));
      for(const item of active){
        const group=node('div','mz-promo-rules-campaign');group.append(node('h5','',item.title));
        const list=node('dl','mz-promo-rules-values');
        for(const [key,label,kind] of (ruleFields[mod.id]||[])){
          if(item.config?.[key]===undefined||item.config?.[key]===null)continue;
          const line=node('div','');line.append(node('dt','',label),node('dd','',fmt(item.config[key],kind)));list.append(line);
        }
        group.append(list);body.append(group);
      }
    }else if(mod.id!=='rebate')body.append(node('p','mz-promo-rules-muted','Não há configurações ativas publicadas no momento.'));
    body.append(node('p','mz-promo-rules-muted','Condições exibidas conforme as configurações ativas. A disponibilidade do benefício depende das validações do sistema.'));
    panel.append(body);container.append(panel);
  }

  function node(tag,cls,text){const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;}
  const fmt=(value,kind)=>kind==='money'?new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format((Number(value)||0)/100):kind==='percent'?(Number(value)||0).toLocaleString('pt-BR')+'%':kind==='multiple'?String(value??0)+'x':String(value??'—');
  for(const mod of definitions){const btn=node('button','mz-promo-tile');btn.type='button';btn.dataset.promo=mod.id;btn.append(node('span','',mod.icon),node('strong','',mod.title),node('small','',mod.desc));directory.append(btn);}
  async function refresh(){
    const response=await fetch(base+'/api/promotions/configs',{headers:{Accept:'application/json'},credentials:'same-origin'});
    if(!response.ok)throw Error('Não foi possível consultar as configurações. Execute a migração 018.');
    const payload=await response.json();configs=Array.isArray(payload.items)?payload.items:[];
  }
  function showIndex(){detail.classList.add('hidden');detail.replaceChildren();directory.classList.remove('hidden');section?.classList.remove('promo-detail-mode');}
  function openModule(id){
    const mod=definitions.find(m=>m.id===id);if(!mod)return;
    const minimalLayout=new Set(['checkin','vip','chests','agency','rebate','rescue','roulette','cashwheel']);
    directory.classList.add('hidden');detail.classList.remove('hidden');detail.replaceChildren();section?.classList.add('promo-detail-mode');
    const back=node('button','mz-promo-back','← Voltar às promoções');back.type='button';back.addEventListener('click',showIndex);detail.append(back);
    if(!minimalLayout.has(mod.id)){
      const hero=node('header','mz-promo-hero');hero.append(node('div','',mod.icon),node('h2','',mod.title),node('p','',mod.desc));detail.append(hero);
    }
    if(mod.id==='coupons')couponSection(detail);
    else if(mod.id==='checkin')checkinSection(detail);
    else if(mod.id==='vip')vipSection(detail);
    else if(mod.id==='chests')chestSection(detail);
    else if(mod.id==='agency')agencySection(detail);
    else if(mod.id==='rebate')rebateSection(detail);
    else if(mod.id==='rescue')rescueSection(detail);
    else if(mod.id==='roulette')rouletteSection(detail);
    else if(mod.id==='cashwheel')cashwheelSection(detail);
    else{const status=node('section','mz-promo-box');status.append(node('h3','','Disponibilidade'));
      status.append(node('p','mz-promo-note','Módulo em preparação. Os resgates desta promoção ainda não estão habilitados.'));const action=node('button','mz-promo-action','Resgate indisponível');action.type='button';action.disabled=true;status.append(action);detail.append(status);}
    appendRules(detail,mod);
    detail.scrollIntoView({behavior:'smooth',block:'start'});
  }
  document.addEventListener('mz:open-promotion',event=>{if(definitions.some(item=>item.id===event.detail?.id))openModule(event.detail.id);});
  directory.addEventListener('click',event=>{const button=event.target.closest('button[data-promo]');if(button)openModule(button.dataset.promo);});
  document.querySelectorAll('[data-section="promotions"]').forEach(button=>button.addEventListener('click',showIndex));
  document.addEventListener('mz:auth-ready',()=>setTimeout(maybeOpenEnvelope,180));
  document.addEventListener('mz:wallet-updated',()=>setTimeout(maybeOpenEnvelope,250));
  refresh().catch(error=>{directory.prepend(node('p','mz-promo-status',error.message));});
  setTimeout(maybeOpenEnvelope,350);
})();
