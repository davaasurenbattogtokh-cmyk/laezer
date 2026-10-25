const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
const cursor=document.querySelector('.cursor');
if(matchMedia('(pointer:fine)').matches&&!reduced){
 document.addEventListener('pointermove',e=>{cursor.style.display='flex';cursor.style.left=e.clientX+'px';cursor.style.top=e.clientY+'px'});
 document.addEventListener('pointerover',e=>{const el=e.target.closest('[data-cursor]');cursor.classList.toggle('active',!!el);cursor.querySelector('span').textContent=el?(el.dataset.cursor||'Glow'):''});
 document.addEventListener('pointerout',e=>{if(!e.relatedTarget)cursor.style.display='none'});
}
const io=new IntersectionObserver(items=>items.forEach(item=>{if(item.isIntersecting){item.target.classList.add('visible');io.unobserve(item.target)}}),{threshold:.08});document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
const menu=document.querySelector('.menu'),nav=document.querySelector('.header nav');
menu.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',open);menu.setAttribute('aria-label',open?'Цэс хаах':'Цэс нээх');nav.classList.toggle('open',open)});
nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{menu.setAttribute('aria-expanded','false');menu.setAttribute('aria-label','Цэс нээх');nav.classList.remove('open')}));
if(!reduced){
 const flower=document.querySelector('.flower'),nature=document.querySelector('.nature'),details=[...document.querySelectorAll('.ingredient-detail')];let pending=false;
 const scrollEffects=()=>{pending=false;if(flower&&nature){const box=nature.getBoundingClientRect(),p=Math.max(0,Math.min(1,(innerHeight-box.top)/(innerHeight+box.height)));flower.style.transform=`rotate(${-22+p*18}deg) scale(${.83+p*.24})`}
 for(const el of details){const box=el.parentElement.getBoundingClientRect();if(box.bottom>0&&box.top<innerHeight){const p=Math.max(-1,Math.min(1,(innerHeight/2-box.top-box.height/2)/innerHeight));el.style.setProperty('--ingredient-drift',(p*25).toFixed(2)+'px')}}};
 addEventListener('scroll',()=>{if(!pending){pending=true;requestAnimationFrame(scrollEffects)}},{passive:true});scrollEffects();
}
