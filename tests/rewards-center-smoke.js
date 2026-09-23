// Smoke test without network or external DOM packages.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
class Element {
  constructor(tag='div'){this.tagName=tag;this.children=[];this.className='';this.dataset={};this.style={setProperty(){}};this.listeners={};this._text='';this.type='';this.classList={add:()=>{},remove:()=>{},toggle:()=>{}};}
  set textContent(s){this.children=[];this._text=String(s)}
  get textContent(){return this._text+this.children.map(x=>x.textContent||'').join(' ')}
  append(...children){this.children.push(...children)}
  prepend(...children){this.children.unshift(...children)}
  replaceChildren(...children){this.children=[...children];this._text=''}
  addEventListener(name,fn){this.listeners[name]=fn}
  closest(){return null}
  querySelector(){return null}
  querySelectorAll(){return []}
  scrollIntoView(){}
}
const directory=new Element(),detail=new Element(),rewardsCenter=new Element();
const section=new Element();detail.closest=()=>section;
const elements={'mz-promo-directory':directory,'mz-promo-detail':detail,'mz-rewards-center':rewardsCenter};
const document={getElementById:id=>elements[id]||null,createElement:tag=>new Element(tag),querySelectorAll:()=>[],addEventListener:()=>{}};
const localStorage={getItem:k=>k==='igaming_token'?'logged-in-test-token':null};
const configTypes=['roulette','vip','cashwheel','checkin','chests','coupons','rebate','agency','rescue','lottery'];
const mocked={
 '/api/promotions/configs':{items:configTypes.map(type=>({type,config:{},title:type}))},
 '/api/promotions/roulette/status':{campaigns:[{available_spins:0}]},
 '/api/promotions/cashwheel/status':{active:true,free_spins:0,session:{progress_minor:0},campaign:{config:{target_cents:10000}}},
 '/api/promotions/status':{available_today:false,claimed_today:true,history:[{id:'1',promotion_type:'checkin',title:'Dia 1',amount_minor:200,status:'COMPLETED',created_at:'2026-09-22 20:00:00'}]},
 '/api/promotions/chests/status':{campaigns:[]},
 '/api/promotions/agency/status':{affiliate_balance_minor:0},
 '/api/promotions/rebate/status':{available_minor:0,settings:{enabled:true,min_claim_minor:100}},
 '/api/promotions/rescue/status':{current:{}},
 '/api/promotions/lottery/status':{active:true,free_spins:50,complete:false},
 '/api/promotions/vip/status':{levels:[]},
 '/api/promotions/vip/benefits':{benefits:[]}
};
const fetch=async url=>{const path=String(url).replace(/^.*?(\/api\/promotions\/)/,'/api/promotions/');assert.ok(path in mocked,`Unexpected path ${path}`);return {ok:true,json:async()=>mocked[path]}};
const context={document,window:{IGAMING:{basePath:''}},localStorage,fetch,Intl,console,setTimeout:()=>0,Event:class{}};
vm.runInNewContext(fs.readFileSync('public/assets/promotions.js','utf8'),context);
const flatten=node=>[node,...node.children.flatMap(flatten)];
setImmediate(()=>setImmediate(()=>setImmediate(()=>{
  try{
    const promoCards=flatten(directory).filter(el=>el.dataset.promo==='lottery');
    assert.equal(promoCards.length,1,'Lottery promotion card should exist');
    assert.match(promoCards[0].textContent,/Disponível/);
    assert.match(promoCards[0].textContent,/50 rodada/);
    const center=flatten(rewardsCenter);
    assert.ok(center.some(el=>el.dataset.promo==='lottery'),'Rewards center should link available lottery');
    assert.match(rewardsCenter.textContent,/R\$\s?2,00/,'Real redemption history should be displayed');
    assert.doesNotMatch(rewardsCenter.textContent,/Chance de carta útil|Campanha ativa/);
    console.log('PASS: 50 lottery spins appear as available in compact card and central; redemption history shown; no probabilities exposed.');
  }catch(err){console.error(err);process.exitCode=1}
})));
