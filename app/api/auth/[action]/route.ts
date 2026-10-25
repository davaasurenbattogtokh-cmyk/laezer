import {env} from 'cloudflare:workers';
import {authResponse} from '../../../../server/auth-handler.js';
export const dynamic='force-dynamic';
export async function POST(request:Request){return authResponse(request,{db:env.DB,env,dev:import.meta.env.DEV})}
