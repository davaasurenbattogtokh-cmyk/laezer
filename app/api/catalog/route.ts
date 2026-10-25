import {env} from 'cloudflare:workers';
import {catalog} from '../../../server/store.js';
export const dynamic='force-dynamic';
export async function GET(){try{return Response.json({products:await catalog(env.DB)},{headers:{'Cache-Control':'no-store'}})}catch{return Response.json({error:'Бүтээгдэхүүнийг ачаалж чадсангүй.'},{status:503})}}
