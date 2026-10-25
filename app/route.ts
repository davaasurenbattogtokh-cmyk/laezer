import html from '../content/site.html?raw';
import {env} from 'cloudflare:workers';
import {banners} from '../server/store.js';
import {renderHero} from '../server/render.js';
export const dynamic='force-dynamic';
export async function GET(){let content=html;try{content=renderHero(html,await banners(env.DB))}catch{console.error('Hero configuration unavailable')};return new Response(content,{headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}})}
