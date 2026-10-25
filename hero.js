(()=>{
const hero=document.querySelector('.official-hero');if(!hero)return;
const slides=[...hero.querySelectorAll('.official-slide')],dots=[...hero.querySelectorAll('.slide-dot')],count=hero.querySelector('.official-count');let current=0;
function show(index){current=(index+slides.length)%slides.length;slides.forEach((slide,i)=>{const selected=i===current;slide.classList.toggle('is-current',selected);slide.setAttribute('aria-hidden',!selected);slide.inert=!selected});dots.forEach((dot,i)=>{dot.classList.toggle('is-current',i===current);dot.setAttribute('aria-pressed',i===current)});count.innerHTML=`0${current+1} <i>/ 0${slides.length}</i>`}
dots.forEach((dot,i)=>dot.addEventListener('click',()=>show(i)));document.querySelector('#hero-prev').onclick=()=>show(current-1);document.querySelector('#hero-next').onclick=()=>show(current+1);
hero.addEventListener('keydown',e=>{if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();show(current+(e.key==='ArrowRight'?1:-1))}});
let startX=0,startY=0;hero.addEventListener('touchstart',e=>{startX=e.changedTouches[0].clientX;startY=e.changedTouches[0].clientY},{passive:true});hero.addEventListener('touchend',e=>{let dx=e.changedTouches[0].clientX-startX,dy=e.changedTouches[0].clientY-startY;if(Math.abs(dx)>60&&Math.abs(dx)>Math.abs(dy)*1.5)show(current+(dx<0?1:-1))},{passive:true});
})();
