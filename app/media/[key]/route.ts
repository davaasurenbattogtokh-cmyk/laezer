import {env} from 'cloudflare:workers';
export const dynamic='force-dynamic';
export async function GET(request:Request){const key=new URL(request.url).pathname.slice('/media/'.length);if(!/^[a-f0-9-]{36}\.(png|jpg|webp)$/.test(key)||!env.BUCKET)return new Response('Not found',{status:404});const object=await env.BUCKET.get(key);if(!object)return new Response('Not found',{status:404});return new Response(object.body,{headers:{'Content-Type':object.httpMetadata?.contentType||'application/octet-stream','Cache-Control':'public,max-age=31536000,immutable','X-Content-Type-Options':'nosniff'}})}
