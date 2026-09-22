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
   {id:'rebate',title:'Rebate',icon:'↺',desc:'Faixas de retorno configuradas.',type:'rebate',fields:[['level','Nível','integer'],['bet_volume_cents','Apostas acumuladas','money'],['rebate_percent','Retorno','percent']]},
   {id:'coupons',title:'Troca de Recompensas',icon:'🎟️',desc:'Consulte as regras de cupons ativos.',type:'coupons',fields:[['quantity','Quantidade','integer'],['bonus_min_cents','Bônus mínimo','money'],['bonus_max_cents','Bônus máximo','money']]},
   {id:'checkin',title:'Nível e Check-in',icon:'📅',desc:'Dias, requisitos e recompensas publicados.',type:'checkin',fields:[['day','Dia','integer'],['reward_min_cents','Valor mínimo','money'],['reward_max_cents','Valor máximo','money'],['bet_min_cents','Apostas necessárias','money']]},
   {id:'rescue',title:'Fundos de Resgate',icon:'🛟',desc:'Faixas de compensação diária.',type:'rescue',fields:[['loss_min_cents','Perda mínima','money'],['refund_percent','Percentual','percent'],['rollover_x','Rollover','multiple']]},
   {id:'weekly',title:'Compensação Semanal',icon:'🗓️',desc:'Tabela de compensações semanais.',type:'weekly',fields:[['loss_min_cents','Perda mínima semanal','money'],['refund_percent','Compensação','percent'],['rollover_x','Rollover','multiple']]},
   {id:'vip',title:'Clube VIP',icon:'👑',desc:'Níveis e benefícios configurados.',type:'vip',fields:[['level','VIP','integer'],['goal_cents','Meta','money'],['bonus_cents','Bônus','money'],['rollover_x','Rollover','multiple']]},
   {id:'cashwheel',title:'Roleta de Saque',icon:'🎡',desc:'Condições da campanha configurada.',type:'cashwheel',fields:[['target_cents','Meta da campanha','money'],['duration_days','Validade (dias)','integer'],['free_spins_per_day','Rodadas grátis/dia','integer']]},
   {id:'lottery',title:'Sorteio de Cartas',icon:'🃏',desc:'Prêmio de coleção e regras publicadas.',type:'lottery',fields:[['spins_per_day','Rodadas por dia','integer'],['collection_bonus_cents','Prêmio coleção','money'],['rollover_x','Rollover','multiple']]},
   {id:'roulette',title:'Roleta de Boas-vindas',icon:'🎯',desc:'Rodadas por depósito e indicação.',type:'roulette',fields:[['deposit_min_cents','Depósito mínimo','money'],['spins_per_deposit','Rodadas por depósito','integer'],['spins_per_referral','Rodadas por indicado','integer']]},
   {id:'envelope',title:'Envelope Vermelho',icon:'✉️',desc:'Regras e valores publicados.',type:'envelope',fields:[['reward_min_cents','Bônus mínimo','money'],['reward_max_cents','Bônus máximo','money'],['rollover_x','Rollover','multiple']]}
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


  // Regras educativas: somente campos publicados pelo Admin; módulos não ativados não prometem créditos.
  const ruleTexts={
    agency:['Compartilhe seu link de indicação para formar sua equipe.','Metas de volume e taxas de comissão dependem da faixa publicada.','O cálculo e o pagamento de comissões ainda não estão disponíveis nesta versão.'],
    chests:['Convide amigos pelo seu link; cada baú possui uma meta de indicados elegíveis.','A qualificação exige o depósito mínimo publicado para a respectiva meta.','Os baús e seus resgates ainda não estão disponíveis nesta versão.'],
    rebate:['A faixa de retorno depende do volume de apostas válidas indicado na tabela.','Os percentuais exibidos são configurações publicadas pelo administrador.','O cálculo e o resgate do rebate ainda não estão disponíveis nesta versão.'],
    coupons:['Digite o código recebido de um canal oficial e clique em Trocar código.','Cada código pode ser resgatado uma única vez por conta e está sujeito ao estoque disponível.','O valor recebido e a exigência de apostas (rollover) dependem do cupom configurado; o resgate exibe o resultado.'],
    checkin:['A sequência é diária; a virada ocorre às 21h no horário de Brasília. Se perder um dia, a sequência recomeça.','Cada card mostra sua recompensa, o depósito mínimo, as apostas necessárias e o rollover configurados.','Para receber, cumpra os requisitos com transações confirmadas no período, faça login e use o botão de resgate; há um resgate por dia.'],
    rescue:['As faixas exibem limites de perda e percentuais previstos para a campanha.','A forma de cálculo e o prazo de solicitação serão informados quando o processamento estiver disponível.','O cálculo e o resgate dos fundos ainda não estão disponíveis nesta versão.'],
    weekly:['As faixas publicadas relacionam perda mínima semanal e percentual de compensação.','Períodos e datas de disponibilização serão informados quando o processamento estiver disponível.','O cálculo e o resgate semanal ainda não estão disponíveis nesta versão.'],
    vip:['O progresso de upgrade utiliza apostas válidas confirmadas e a meta acumulada de cada nível.','Cada nível pode ter bônus de upgrade, diário, semanal e mensal e uma meta de manutenção; valores não configurados não geram recompensa.','O programa pode preservar o nível e suspender benefícios ou aplicar redução mensal, conforme a regra definida no Admin.','Benefícios recorrentes são gerados pela rotina do servidor e precisam estar disponíveis antes do resgate; valores com rollover exigem apostas válidas para liberação.'],
    cashwheel:['A campanha pode ter meta de saldo, período de participação e rodadas gratuitas por dia.','As regras para receber giros e a elegibilidade serão confirmadas na implementação do sorteio.','A roleta e os saques desta campanha ainda não estão disponíveis nesta versão.'],
    lottery:['A campanha pode prever giros por dia e recompensa por completar uma coleção de cartas.','A composição, o resultado e as condições de cada rodada serão definidos quando o sorteio for implementado.','Giros, coleção e resgates ainda não estão disponíveis nesta versão.'],
    roulette:['A configuração pode conceder rodadas por depósito mínimo ou indicação válida.','Quantidade de rodadas, elegibilidade e recompensas dependem das regras publicadas no Admin.','O processamento dos giros e os resgates ainda não estão disponíveis nesta versão.'],
    envelope:['O valor do bônus e o rollover dependem da campanha habilitada pelo Admin.','As condições de elegibilidade e distribuição serão informadas quando a funcionalidade for ativada.','A distribuição e o resgate do envelope ainda não estão disponíveis nesta versão.']
  };
  const ruleFields={
    agency:[['level','Faixa','integer'],['team_bet_min_cents','Meta de apostas da equipe','money'],['commission_percent','Comissão indicada','percent']],
    chests:[['referral_count','Indicados exigidos','integer'],['referred_deposit_min_cents','Depósito mínimo de cada indicado','money'],['bonus_cents','Recompensa','money'],['rollover_x','Rollover','multiple']],
    rebate:[['level','Nível','integer'],['bet_volume_cents','Volume necessário','money'],['rebate_percent','Taxa de retorno','percent']],
    coupons:[['quantity','Quantidade total cadastrada','integer'],['bonus_min_cents','Bônus mínimo','money'],['bonus_max_cents','Bônus máximo','money'],['rollover_x','Rollover','multiple']],
    checkin:[['day','Dia','integer'],['reward_min_cents','Bônus mínimo','money'],['reward_max_cents','Bônus máximo','money'],['extra_cents','Recompensa extra','money'],['deposit_min_cents','Depósito mínimo no período','money'],['bet_min_cents','Apostas exigidas no período','money'],['rollover_x','Rollover','multiple']],
    rescue:[['level','Nível','integer'],['loss_min_cents','Perda mínima','money'],['refund_percent','Taxa prevista','percent'],['rollover_x','Rollover','multiple']],
    weekly:[['level','Nível','integer'],['loss_min_cents','Perda mínima semanal','money'],['refund_percent','Taxa prevista','percent'],['rollover_x','Rollover','multiple']],
    vip:[['level','VIP','integer'],['goal_cents','Meta acumulada','money'],['maintenance_cents','Manutenção mensal','money'],['bonus_cents','Upgrade','money'],['daily_bonus_cents','Diário','money'],['weekly_bonus_cents','Semanal','money'],['monthly_bonus_cents','Mensal','money'],['rollover_x','Rollover','multiple']],
    cashwheel:[['target_cents','Meta de saldo','money'],['duration_days','Validade (dias)','integer'],['free_spins_per_day','Rodadas por dia','integer'],['referral_bonus_cents','Ajuda por indicação','money']],
    lottery:[['spins_per_day','Rodadas por dia','integer'],['collection_bonus_cents','Prêmio da coleção','money'],['rollover_x','Rollover','multiple']],
    roulette:[['deposit_min_cents','Depósito mínimo','money'],['spins_per_deposit','Rodadas por depósito','integer'],['spins_per_referral','Rodadas por indicação','integer'],['reward_min_cents','Prêmio mínimo previsto','money'],['reward_max_cents','Prêmio máximo previsto','money'],['rollover_x','Rollover','multiple']],
    envelope:[['reward_min_cents','Valor mínimo','money'],['reward_max_cents','Valor máximo','money'],['rollover_x','Rollover','multiple']]
  };
  function appendRules(container,mod){
    const panel=node('details','mz-promo-rules');
    const title=node('summary','mz-promo-rules-heading');title.append(node('span','mz-promo-rules-icon','ⓘ'),node('strong','','Como funciona • Regras e requisitos'),node('span','mz-promo-rules-chevron','⌄'));
    panel.append(title);
    const body=node('div','mz-promo-rules-body');
    const steps=node('ol','mz-promo-rules-steps');for(const text of (ruleTexts[mod.id]||[]))steps.append(node('li','',text));body.append(steps);
    const active=configs.filter(item=>item.type===mod.type);
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
    }else body.append(node('p','mz-promo-rules-muted','Não há configurações ativas publicadas no momento.'));
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
    const minimalLayout=new Set(['checkin','vip']);
    directory.classList.add('hidden');detail.classList.remove('hidden');detail.replaceChildren();section?.classList.add('promo-detail-mode');
    const back=node('button','mz-promo-back','← Voltar às promoções');back.type='button';back.addEventListener('click',showIndex);detail.append(back);
    if(!minimalLayout.has(mod.id)){
      const hero=node('header','mz-promo-hero');hero.append(node('div','',mod.icon),node('h2','',mod.title),node('p','',mod.desc));detail.append(hero);
    }
    if(mod.id==='coupons')couponSection(detail);
    else if(mod.id==='checkin')checkinSection(detail);
    else if(mod.id==='vip')vipSection(detail);
    else{const status=node('section','mz-promo-box');status.append(node('h3','','Disponibilidade'));
      status.append(node('p','mz-promo-note','Módulo em preparação. Os resgates desta promoção ainda não estão habilitados.'));const action=node('button','mz-promo-action','Resgate indisponível');action.type='button';action.disabled=true;status.append(action);detail.append(status);}
    appendRules(detail,mod);
    detail.scrollIntoView({behavior:'smooth',block:'start'});
  }
  document.addEventListener('mz:open-promotion',event=>{if(definitions.some(item=>item.id===event.detail?.id))openModule(event.detail.id);});
  directory.addEventListener('click',event=>{const button=event.target.closest('button[data-promo]');if(button)openModule(button.dataset.promo);});
  document.querySelectorAll('[data-section="promotions"]').forEach(button=>button.addEventListener('click',showIndex));
  refresh().catch(error=>{directory.prepend(node('p','mz-promo-status',error.message));});
})();
