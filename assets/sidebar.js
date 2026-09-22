/* MZ90 V8: drawer independente; não altera a barra inferior ou o login. */
(() => {
  'use strict';
  const drawer=document.getElementById('mz-home-drawer');
  const toggle=document.getElementById('mz-drawer-toggle');
  const backdrop=document.getElementById('mz-drawer-backdrop');
  const closeButton=document.getElementById('mz-drawer-close');
  if(!drawer||!toggle||!backdrop||!closeButton)return;
  let previousFocus=null;
  const isOpen=()=>drawer.classList.contains('is-open');
  function setOpen(open){
    if(open===isOpen())return;
    if(open)previousFocus=document.activeElement;
    if(!open){drawer.inert=true;drawer.setAttribute('aria-hidden','true');}
    drawer.classList.toggle('is-open',open);
    backdrop.classList.toggle('hidden',!open);
    backdrop.setAttribute('aria-hidden',String(!open));
    toggle.setAttribute('aria-expanded',String(open));
    document.body.classList.toggle('mz-drawer-open',open);
    if(open){drawer.inert=false;drawer.setAttribute('aria-hidden','false');closeButton.focus();}
    else if(previousFocus?.isConnected)previousFocus.focus();
  }
  toggle.addEventListener('click',()=>setOpen(!isOpen()));
  closeButton.addEventListener('click',()=>setOpen(false));
  backdrop.addEventListener('click',()=>setOpen(false));
  document.addEventListener('keydown',event=>{
    if(!isOpen())return;
    if(event.key==='Escape'){event.preventDefault();setOpen(false);return;}
    if(event.key==='Tab'){
      const targets=[...drawer.querySelectorAll('button:not([disabled]),a[href]')].filter(el=>el.getClientRects().length);
      if(!targets.length)return;
      const first=targets[0],last=targets[targets.length-1];
      if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
      else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
    }
  });
  drawer.addEventListener('click',event=>{
    const promo=event.target.closest('[data-drawer-promo]');
    if(promo){
      const id=promo.dataset.drawerPromo;
      setOpen(false);
      document.querySelector('.bottom-item[data-section="promotions"]')?.click();
      document.dispatchEvent(new CustomEvent('mz:open-promotion',{detail:{id}}));
      return;
    }
    const section=event.target.closest('[data-drawer-section]');
    if(section){setOpen(false);document.querySelector('.bottom-item[data-section="'+section.dataset.drawerSection+'"]')?.click();
      if(section.dataset.drawerSection==='profile'&&!document.querySelector('.bottom-item[data-section="profile"]'))document.getElementById('profile-nav')?.click();
      return;}
    if(event.target.closest('[data-drawer-deposit]')){setOpen(false);document.getElementById('deposit-nav')?.click();}
  });
})();
