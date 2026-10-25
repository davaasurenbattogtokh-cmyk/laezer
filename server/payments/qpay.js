import {OrderError} from '../orders.js';
const ENDPOINTS={sandbox:'https://merchant-sandbox.qpay.mn',production:'https://merchant.qpay.mn'};
const unavailable=()=>new OrderError('QPay холболт хараахан идэвхжээгүй байна.',503);
// Credentials and flags alone must never release untested checkout/payment routes.
export const QPAY_ADAPTER_IMPLEMENTED=false;
const required=['QPAY_CLIENT_ID','QPAY_CLIENT_SECRET','QPAY_INVOICE_CODE','QPAY_PUBLIC_ORIGIN','QPAY_CALLBACK_SECRET'];
export function qpayReadiness(env={}){
 const environment=env.QPAY_ENVIRONMENT||'sandbox',missing=required.filter(name=>typeof env[name]!=='string'||!env[name].trim());
 const errors=[];if(!ENDPOINTS[environment])errors.push('environment');
 if(env.QPAY_CALLBACK_SECRET&&env.QPAY_CALLBACK_SECRET.length<32)errors.push('callback_secret');
 if(env.QPAY_PUBLIC_ORIGIN){try{const url=new URL(env.QPAY_PUBLIC_ORIGIN);if(url.protocol!=='https:'||url.username||url.password||url.pathname!=='/'||url.search||url.hash||url.hostname==='localhost')errors.push('public_origin')}catch{errors.push('public_origin')}}
 const enabled=env.QPAY_ENABLED==='true',configured=!missing.length&&!errors.length;
 return{provider:'qpay',environment,enabled,configured,available:enabled&&configured&&QPAY_ADAPTER_IMPLEMENTED,phase:!enabled?'disabled':!configured?'configuration_required':'adapter_pending',missing,errors};
}
export function qpayConfig(env){const status=qpayReadiness(env);if(!status.configured)throw unavailable();return{environment:status.environment,baseUrl:ENDPOINTS[status.environment],clientId:env.QPAY_CLIENT_ID,clientSecret:env.QPAY_CLIENT_SECRET,invoiceCode:env.QPAY_INVOICE_CODE,publicOrigin:new URL(env.QPAY_PUBLIC_ORIGIN).origin,callbackSecret:env.QPAY_CALLBACK_SECRET}}
function positiveAmount(value){const string=String(value);if(!/^\d+(?:\.0{1,2})?$/.test(string))throw new OrderError('Төлбөрийн дүн буруу байна.',422);const amount=Number(string);if(!Number.isSafeInteger(amount)||amount<=0)throw new OrderError('Төлбөрийн дүн буруу байна.',422);return amount}
export async function prepareQpayAttempt(db,orderId,environment='sandbox'){
 if(!ENDPOINTS[environment])throw new OrderError('Төлбөрийн орчин буруу байна.');
 const order=await db.prepare('SELECT * FROM orders WHERE id=?').bind(orderId).first();
 if(!order||order.payment!=='qpay'||order.status==='cancelled'||order.payment_status==='paid')throw new OrderError('Энэ захиалгад QPay нэхэмжлэх үүсгэх боломжгүй.',409);
 const time=new Date().toISOString(),attempt={id:'PAY-'+crypto.randomUUID(),order_id:order.id,provider:'qpay',environment,amount:positiveAmount(order.total),currency:'MNT',status:'prepared',created_at:time,updated_at:time};
 await db.prepare('INSERT INTO payment_attempts (id,order_id,provider,environment,amount,currency,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)').bind(...Object.values(attempt)).run();return attempt;
}
async function callbackKey(secret){return crypto.subtle.importKey('raw',new TextEncoder().encode(secret),{name:'HMAC',hash:'SHA-256'},false,['sign','verify'])}
const stateMessage=attempt=>new TextEncoder().encode([attempt.id,attempt.order_id,attempt.environment,attempt.amount,attempt.currency].join('|'));
export async function callbackState(attempt,secret){if(typeof secret!=='string'||secret.length<32)throw unavailable();const signature=await crypto.subtle.sign('HMAC',await callbackKey(secret),stateMessage(attempt));return[...new Uint8Array(signature)].map(b=>b.toString(16).padStart(2,'0')).join('')}
export async function verifyCallbackState(attempt,secret,state){if(!/^[a-f0-9]{64}$/.test(state||'')||typeof secret!=='string'||secret.length<32)return false;return crypto.subtle.verify('HMAC',await callbackKey(secret),new Uint8Array(state.match(/../g).map(x=>parseInt(x,16))),stateMessage(attempt))}
export async function buildQpayInvoiceRequest(attempt,env){
 const config=qpayConfig(env);if(attempt.environment!==config.environment||attempt.provider!=='qpay'||attempt.currency!=='MNT')throw new OrderError('Нэхэмжлэхийн орчин эсвэл валют зөрсөн байна.',422);
 const callback=new URL('/api/payments/qpay/callback',config.publicOrigin);callback.searchParams.set('attempt',attempt.id);callback.searchParams.set('state',await callbackState(attempt,config.callbackSecret));
 return{invoice_code:config.invoiceCode,sender_invoice_no:attempt.id,invoice_receiver_code:'terminal',invoice_description:'LaEzer '+attempt.order_id,amount:positiveAmount(attempt.amount),callback_url:callback.toString()};
}
// Only a future server-side Merchant API adapter may supply this evidence.
// HTTP callback bodies, query strings, browser "success" pages are never evidence.
export function validateVerifiedQpayPayment(attempt,evidence){
 if(attempt.provider!=='qpay'||!attempt.provider_invoice_id||evidence?.environment!==attempt.environment||evidence.invoiceId!==attempt.provider_invoice_id||evidence.status!=='PAID'||evidence.currency!=='MNT'||positiveAmount(evidence.amount)!==attempt.amount||typeof evidence.paymentId!=='string'||!evidence.paymentId||evidence.paymentId.length>200)throw new OrderError('Төлбөрийн баталгаажуулалтын мэдээлэл зөрсөн байна.',422);
 return{paymentId:evidence.paymentId,invoiceId:evidence.invoiceId,amount:attempt.amount,currency:'MNT'};
}
export async function recordVerifiedQpayPayment(db,attemptId,evidence){
 const attempt=await db.prepare('SELECT * FROM payment_attempts WHERE id=?').bind(attemptId).first();if(!attempt)throw new OrderError('Нэхэмжлэх олдсонгүй.',404);
 const payment=validateVerifiedQpayPayment(attempt,evidence);
 if(!['pending','paid'].includes(attempt.status))throw new OrderError('Нэхэмжлэх баталгаажуулах төлөвт биш байна.',409);
 if(attempt.provider_payment_id&&attempt.provider_payment_id!==payment.paymentId)throw new OrderError('Өөр гүйлгээгээр төлөгдсөн байна.',409);
 const order=await db.prepare('SELECT id,total,payment FROM orders WHERE id=?').bind(attempt.order_id).first();if(!order||order.total!==attempt.amount||order.payment!=='qpay')throw new OrderError('Захиалгын дүн эсвэл төлбөрийн хэлбэр зөрсөн байна.',409);
 const time=new Date().toISOString();
 try{await db.batch([
  db.prepare("UPDATE payment_attempts SET status='paid',provider_payment_id=?,paid_at=COALESCE(paid_at,?),updated_at=? WHERE id=? AND status IN ('pending','paid') AND (provider_payment_id IS NULL OR provider_payment_id=?)").bind(payment.paymentId,time,time,attempt.id,payment.paymentId),
  db.prepare("UPDATE orders SET payment_status='paid',paid_at=COALESCE(paid_at,?) WHERE id=? AND total=? AND payment='qpay' AND EXISTS (SELECT 1 FROM payment_attempts WHERE id=? AND status='paid' AND provider_payment_id=?)").bind(time,attempt.order_id,attempt.amount,attempt.id,payment.paymentId)
 ])}catch{throw new OrderError('Гүйлгээг бүртгэж чадсангүй. Баталгаажуулалтыг дахин шалгана уу.',409)}
 const saved=await db.prepare('SELECT status,provider_payment_id FROM payment_attempts WHERE id=?').bind(attempt.id).first();if(saved.status!=='paid'||saved.provider_payment_id!==payment.paymentId)throw new OrderError('Баталгаажуулалтын төлөв өөрчлөгдсөн байна.',409);
 return{orderId:attempt.order_id,paymentStatus:'paid'};
}
export function qpayNotConnected(){throw unavailable()}
