import { calculateCart, MERCHANT, money } from '../public/catalog.js';
export class OrderError extends Error{constructor(message,status=400){super(message);this.status=status}}
export function validateOrder(input,products){
 if(!input||typeof input!=='object')throw new OrderError('Захиалгын мэдээлэл буруу байна.');
 const name=String(input.name||'').trim(),phone=String(input.phone||'').trim(),address=String(input.address||'').trim(),note=String(input.note||'').trim(),payment=input.payment;
 if(name.length<2||name.length>80)throw new OrderError('Хүлээн авагчийн нэрээ зөв оруулна уу.');
 if(!/^\d{8}$/.test(phone))throw new OrderError('8 оронтой утасны дугаар оруулна уу.');
 if(address.length<4||address.length>500)throw new OrderError('Хүргэлтийн дэлгэрэнгүй хаягаа оруулна уу.');
 if(note.length>1000)throw new OrderError('Тэмдэглэл 1000 тэмдэгтээс богино байна.');
 if(!['bank','cod'].includes(payment))throw new OrderError('Төлбөрийн хэлбэрээ сонгоно уу.');
 if(!Array.isArray(input.items)||!input.items.length||input.items.length>100)throw new OrderError('Сагс хоосон эсвэл буруу байна.');
 if(products&&input.items.some(i=>{const p=products.find(p=>p.sku===i?.sku);return !p||p.price===null}))throw new OrderError('Бүтээгдэхүүн захиалах боломжгүй эсвэл reseller үнэ тохируулаагүй байна.');
 const seen=new Set();for(const item of input.items){if(!item||seen.has(item.sku))throw new OrderError('Сагсны мөр давхардсан байна.');seen.add(item.sku)}
 let totals;try{totals=calculateCart(input.items.map(i=>({sku:i.sku,qty:i.qty})),products)}catch{throw new OrderError('Бүтээгдэхүүн эсвэл тоо ширхэг буруу байна.')}
 return{name,phone,address,note,payment,...totals};
}
async function hash(value){const bytes=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(value));return[...new Uint8Array(bytes)].map(b=>b.toString(16).padStart(2,'0')).join('')}
function receipt(row){return{id:row.id,createdAt:row.created_at,payment:row.payment,paymentStatus:row.payment_status||'unverified',paidAt:row.paid_at||null,items:JSON.parse(row.items),subtotal:row.subtotal,delivery:row.delivery,total:row.total,notificationStatus:row.notification_status,bank:row.payment==='bank'?{bank:MERCHANT.bank,account:MERCHANT.account,holder:MERCHANT.holder}:null}}
export async function createOrder({input,key,db,notify,products,actor=null}){
 if(!db)throw new OrderError('Захиалгын үйлчилгээ түр боломжгүй байна. Дахин оролдоно уу.',503);
 if(typeof key!=='string'||!/^[a-zA-Z0-9-]{16,100}$/.test(key))throw new OrderError('Захиалгын баталгаажуулах түлхүүр буруу байна.');
 const order=validateOrder(input,products),channel=actor?.role==='reseller'?'reseller':'retail',resellerId=channel==='reseller'?actor.id:null,requestHash=await hash(JSON.stringify({name:order.name,phone:order.phone,address:order.address,note:order.note,payment:order.payment,items:input.items.map(i=>({sku:i.sku,qty:i.qty})),channel,resellerId}));
 let previous=await db.prepare('SELECT * FROM orders WHERE idempotency_key = ?').bind(key).first();
 if(previous){if(previous.request_hash!==requestHash)throw new OrderError('Мэдээлэл өөрчлөгдсөн байна. Захиалгаа дахин эхлүүлнэ үү.',409);return receipt(previous)}
 if(input.expectedTotal!==undefined&&input.expectedTotal!==order.total)throw new OrderError('Бүтээгдэхүүний үнэ шинэчлэгдсэн байна. Сагсны дүнгээ дахин шалгаад захиална уу.',409);
 const id='LZ-'+new Date().toISOString().slice(2,10).replaceAll('-','')+'-'+crypto.randomUUID().slice(0,8).toUpperCase();
 const row={id,idempotency_key:key,request_hash:requestHash,customer_name:order.name,phone:order.phone,address:order.address,note:order.note,payment:order.payment,items:JSON.stringify(order.items),subtotal:order.subtotal,delivery:order.delivery,total:order.total,status:'new',notification_status:'pending',created_at:new Date().toISOString(),channel,reseller_id:resellerId};
 try{await db.prepare('INSERT INTO orders (id,idempotency_key,request_hash,customer_name,phone,address,note,payment,items,subtotal,delivery,total,status,notification_status,created_at,channel,reseller_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)').bind(...Object.values(row)).run()}catch(error){
  previous=await db.prepare('SELECT * FROM orders WHERE idempotency_key = ?').bind(key).first();if(previous&&previous.request_hash===requestHash)return receipt(previous);throw error;
 }
 if(notify){try{const sent=await notify({...order,id,createdAt:row.created_at,channel,resellerName:actor?.name});row.notification_status=sent?'sent':'pending';await db.prepare('UPDATE orders SET notification_status = ? WHERE id = ?').bind(row.notification_status,id).run()}catch{/* The durable order remains available if notification fails. */}}
 return receipt(row);
}
export async function notifyMerchant(order){
 const email='Alzakhguibaatar381@gmail.com';
 const body={_subject:`Шинэ захиалга ${order.id} · ${money(order.total)}`,_template:'table',_captcha:'false','Төрөл':order.channel==='reseller'?'Reseller — '+order.resellerName:'Жижиглэн','Захиалгын дугаар':order.id,'Огноо':order.createdAt,'Хүлээн авагч':order.name,'Утас':order.phone,'Хаяг':order.address,'Бараа':order.items.map(i=>`${i.name} × ${i.qty} = ${money(i.lineTotal)}`).join(' | '),'Барааны дүн':money(order.subtotal),'Хүргэлт':money(order.delivery),'НИЙТ ТӨЛӨХ':money(order.total),'Төлбөрийн хэлбэр':order.payment==='cod'?'Хүргэлтээр бэлнээр':'Дансаар шилжүүлэх','Тэмдэглэл':order.note||'—'};
 const response=await fetch('https://formsubmit.co/ajax/'+encodeURIComponent(email),{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body),signal:AbortSignal.timeout(7000)});if(!response.ok)return false;
 const result=await response.json();return result.success===true||result.success==='true';
}
