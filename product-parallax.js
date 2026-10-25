(()=>{
'use strict';
const section=document.querySelector('#try'),stage=document.querySelector('#product-parallax');if(!section||!stage)return;
const preference=matchMedia('(prefers-reduced-motion: reduce)'),layers=[{el:stage.querySelector('.floating-cica'),x:21,y:15,rotation:-12,tilt:5},{el:stage.querySelector('.floating-ampoule'),x:35,y:24,rotation:9,tilt:7},{el:stage.querySelector('.floating-cream'),x:26,y:18,rotation:-7,tilt:6}];
let targetX=0,targetY=0,x=0,y=0,frameId=0,lastTime=0,touchActive=false;
function paint(){for(const layer of layers){layer.el.style.transform=`translate3d(${(x*layer.x).toFixed(2)}px,${(y*layer.y).toFixed(2)}px,0) rotateX(${(-y*layer.tilt*.6).toFixed(2)}deg) rotateY(${(x*layer.tilt).toFixed(2)}deg) rotateZ(${(layer.rotation+x*2).toFixed(2)}deg)`}stage.style.setProperty('--pointer-x',x.toFixed(4));stage.style.setProperty('--pointer-y',y.toFixed(4))}
function tick(time){frameId=0;const dt=Math.min(40,time-lastTime||16),ease=1-Math.pow(.86,dt/16);lastTime=time;x+=(targetX-x)*ease;y+=(targetY-y)*ease;if(Math.abs(targetX-x)+Math.abs(targetY-y)<.002){x=targetX;y=targetY;paint();lastTime=0;return}paint();frameId=requestAnimationFrame(tick)}
function move(nx,ny){if(preference.matches)return;targetX=Math.max(-1,Math.min(1,nx));targetY=Math.max(-1,Math.min(1,ny));if(!frameId)frameId=requestAnimationFrame(tick)}
function reset(){touchActive=false;move(0,0)}
section.addEventListener('pointermove',event=>{if(event.pointerType==='touch'&&!touchActive)return;const box=stage.getBoundingClientRect();move(((event.clientX-box.left)/box.width-.5)*2,((event.clientY-box.top)/box.height-.5)*2)},{passive:true});
section.addEventListener('pointerdown',event=>{if(event.pointerType==='touch')touchActive=true},{passive:true});section.addEventListener('pointerleave',reset);section.addEventListener('pointercancel',reset);section.addEventListener('pointerup',event=>{if(event.pointerType==='touch')reset()});
stage.addEventListener('keydown',event=>{const directions={ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1],Home:[0,0],Escape:[0,0]};if(directions[event.key]){event.preventDefault();move(...directions[event.key])}});stage.addEventListener('blur',reset);
preference.addEventListener('change',()=>{if(frameId)cancelAnimationFrame(frameId);frameId=0;x=y=targetX=targetY=0;paint()});
new IntersectionObserver(entries=>{if(!entries[0].isIntersecting){if(frameId)cancelAnimationFrame(frameId);frameId=0;x=y=targetX=targetY=0;paint()}},{threshold:0}).observe(stage);
paint();
})();
