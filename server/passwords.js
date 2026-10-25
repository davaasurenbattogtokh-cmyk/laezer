import {scrypt,randomBytes,timingSafeEqual,createHash} from 'node:crypto';
import {promisify} from 'node:util';
import {OrderError} from './orders.js';
const derive=promisify(scrypt),N=32768,r=8,p=3;
export const digest=value=>createHash('sha256').update(value).digest('hex');
export const randomToken=()=>randomBytes(32).toString('hex');
export function normalizeUsername(value){return typeof value==='string'?value.trim().toLowerCase():''}
export function validateUsername(value){const username=normalizeUsername(value);if(!/^[a-z0-9][a-z0-9._-]{2,39}$/.test(username))throw new OrderError('Нэвтрэх нэр 3–40 латин үсэг, тоо, цэг, зурааснаас бүрдэнэ.');return username}
export async function hashPassword(password){if(typeof password!=='string'||password.length<10||password.length>128)throw new OrderError('Нууц үг 10–128 тэмдэгт байна.');const salt=randomBytes(16),key=await derive(password,salt,64,{N,r,p,maxmem:64*1024*1024});return`scrypt$${N}$${r}$${p}$${salt.toString('hex')}$${key.toString('hex')}`}
const DUMMY=`scrypt$${N}$${r}$${p}$${'0'.repeat(32)}$${'0'.repeat(128)}`;
export function validPasswordHash(value){return typeof value==='string'&&/^scrypt\$32768\$8\$3\$[a-f0-9]{32}\$[a-f0-9]{128}$/.test(value)}
export async function verifyPassword(password,encoded){if(typeof password!=='string'||password.length>128)return false;const valid=validPasswordHash(encoded),parts=(valid?encoded:DUMMY).split('$'),actual=await derive(password,Buffer.from(parts[4],'hex'),64,{N,r,p,maxmem:64*1024*1024});return timingSafeEqual(Buffer.from(actual),Buffer.from(parts[5],'hex'))&&valid}
