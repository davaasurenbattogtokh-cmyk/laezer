import {signIn,signOut,sessionCookie} from './password-auth.js';
import {sameOrigin} from './access.js';
import {OrderError} from './orders.js';
export async function authResponse(request,{db,env,dev=false}){
 const json=(value,status=200,extra={})=>Response.json(value,{status,headers:{'Cache-Control':'no-store',...extra}});
 try{
  sameOrigin(request);const action=new URL(request.url).pathname.split('/').pop();
  if(action==='logout'){await signOut({db,request,dev});return json({ok:true,redirect:'/login'},200,{'Set-Cookie':sessionCookie('',dev,true)})}
  if(action!=='login')return json({error:'Хуудас олдсонгүй.'},404);
  if(!request.headers.get('content-type')?.includes('application/json'))return json({error:'Мэдээллийн хэлбэр буруу байна.'},415);
  const raw=await request.text();if(raw.length>4096)return json({error:'Хүсэлт хэт том байна.'},413);let data;try{data=JSON.parse(raw)}catch{return json({error:'Мэдээлэл буруу байна.'},400)}
  const result=await signIn({db,env,username:data?.username,password:data?.password,network:request.headers.get('cf-connecting-ip')||'local'});
  return json({actor:result.actor,redirect:result.redirect},200,{'Set-Cookie':sessionCookie(result.token,dev)});
 }catch(error){if(error instanceof OrderError)return json({error:error.message},error.status,error.status===429?{'Retry-After':'900'}:{});console.error('Login operation failed');return json({error:'Нэвтрэх үйлчилгээ түр боломжгүй байна.'},503)}
}
