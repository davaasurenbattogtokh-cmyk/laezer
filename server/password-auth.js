import {digest,randomToken,normalizeUsername,verifyPassword,validPasswordHash} from './passwords.js';
import {OrderError} from './orders.js';
export const SESSION_TTL=8*60*60*1000;
const WINDOW=15*60*1000;
export const sessionCookieName=dev=>dev?'laezer_dev_session':'__Host-laezer_session';
export function sessionCookie(token,dev=false,clear=false){return`${sessionCookieName(dev)}=${clear?'':token}; Path=/; HttpOnly; SameSite=Strict; Max-Age=${clear?0:SESSION_TTL/1000}${dev?'':'; Secure'}`}
const adminName=env=>normalizeUsername(env.ADMIN_LOGIN_NAME||'admin');
const adminVersion=env=>digest(adminName(env)+'|'+env.ADMIN_PASSWORD_HASH);
export const actorDestination=actor=>actor.role==='admin'?'/admin':'/reseller';
async function consumeLoginLimit(db,username,network,time){
 const window=Math.floor(time/WINDOW)*WINDOW,keys=['net:'+digest(network),'pair:'+digest(network+'|'+username)];
 await db.batch(keys.map(key=>db.prepare('INSERT INTO login_limits (key,window_start,attempts) VALUES (?,?,1) ON CONFLICT(key) DO UPDATE SET attempts=CASE WHEN window_start=? THEN attempts+1 ELSE 1 END,window_start=?').bind(key,window,window,window)));
 const counts=await Promise.all(keys.map(key=>db.prepare('SELECT attempts FROM login_limits WHERE key=?').bind(key).first()));
 if(counts[0].attempts>30||counts[1].attempts>5)throw new OrderError('Олон удаа оролдсон байна. 15 минутын дараа дахин оролдоно уу.',429);
 return keys[1];
}
export async function signIn({db,env,username,password,network='local',time=Date.now()}){
 if(!db||!validPasswordHash(env.ADMIN_PASSWORD_HASH))throw new OrderError('Нэвтрэх үйлчилгээний тохиргоо бэлэн биш байна.',503);
 const login=normalizeUsername(username);if(!login||login.length>40||typeof password!=='string'||!password||password.length>128)throw new OrderError('Нэвтрэх нэр эсвэл нууц үг буруу байна.',401);
 const pairKey=await consumeLoginLimit(db,login,network,time),isAdmin=login===adminName(env),account=isAdmin?null:await db.prepare("SELECT * FROM accounts WHERE username=? AND role='reseller'").bind(login).first();
 const hash=isAdmin?env.ADMIN_PASSWORD_HASH:account?.password_hash||env.ADMIN_PASSWORD_HASH;
 const correct=await verifyPassword(password,hash);
 if(!correct||(!isAdmin&&(!account||!account.active||!account.password_hash)))throw new OrderError('Нэвтрэх нэр эсвэл нууц үг буруу байна.',401);
 const actor=isAdmin?{id:'admin',name:'Админ',username:adminName(env),role:'admin'}:{id:account.id,name:account.name,username:account.username,email:account.email,phone:account.phone,role:'reseller'};
 const token=randomToken(),version=isAdmin?adminVersion(env):String(account.login_version);
 await db.batch([
  db.prepare('INSERT INTO login_sessions (token_hash,account_id,role,credential_version,created_at,expires_at) VALUES (?,?,?,?,?,?)').bind(digest(token),isAdmin?null:account.id,actor.role,version,time,time+SESSION_TTL),
  db.prepare('DELETE FROM login_limits WHERE key=?').bind(pairKey),
  db.prepare('DELETE FROM login_sessions WHERE token_hash IN (SELECT token_hash FROM login_sessions WHERE expires_at<=? LIMIT 100)').bind(time),
  db.prepare('DELETE FROM login_limits WHERE key IN (SELECT key FROM login_limits WHERE window_start<? LIMIT 100)').bind(time-24*60*60*1000)
 ]);return{actor,token,redirect:actorDestination(actor)};
}
function cookieToken(request,dev){const name=sessionCookieName(dev);return(request.headers.get('cookie')||'').split(';').map(v=>v.trim()).find(v=>v.startsWith(name+'='))?.slice(name.length+1)||''}
export async function currentActor({db,env,request,dev=false,time=Date.now()}){
 if(!db)return null;const token=cookieToken(request,dev);if(!/^[a-f0-9]{64}$/.test(token))return null;
 const session=await db.prepare('SELECT * FROM login_sessions WHERE token_hash=? AND expires_at>?').bind(digest(token),time).first();if(!session)return null;
 if(session.role==='admin'){if(!validPasswordHash(env.ADMIN_PASSWORD_HASH)||session.credential_version!==adminVersion(env)||session.account_id!==null)return null;return{id:'admin',name:'Админ',username:adminName(env),role:'admin'}}
 if(session.role!=='reseller'||!session.account_id)return null;
 const account=await db.prepare("SELECT * FROM accounts WHERE id=? AND active=1 AND role='reseller'").bind(session.account_id).first();if(!account?.password_hash||!account.username||session.credential_version!==String(account.login_version))return null;
 return{id:account.id,name:account.name,username:account.username,email:account.email,phone:account.phone,role:'reseller'};
}
export async function signOut({db,request,dev=false}){const token=cookieToken(request,dev);if(db&&/^[a-f0-9]{64}$/.test(token))await db.prepare('DELETE FROM login_sessions WHERE token_hash=?').bind(digest(token)).run()}
