import {hashPassword,validateUsername} from './passwords.js';
import {PRODUCTS} from '../public/catalog.js';
import {OrderError} from './orders.js';
export const HERO_DEFAULTS=[
 ['hero-1','LaEzer-ийн албан ёсны брэндийн зураг — бүтээгдэхүүн барьсан модель','REVEAL YOUR TRUE TEXTURE','Байгалиас.\nТаны арьсанд.','Байгалийн урам, шинжлэх ухааны хослол. LaEzer-ийн ертөнцөд тавтай морил.','Брендийн тухай','#nature'],
 ['hero-2','LaEzer Cica Capsule Spicule Ampoule, центелла навчтай','CICA CAPSULE SPICULE AMPOULE','Арчилгааны\nшинэ мэдрэмж.','Сика капсул спикул ампул. Өдөр тутмын арчилгааг шинээр нээ.','Бүтээгдэхүүн нээх','#collection'],
 ['hero-3','LaEzer Callus Repair Cream-ийн онгорхой сав','CALLUS REPAIR CREAM','Зөөлөн бүтэц.\nТайван арчилгаа.','Callus Repair Cream. Өөртөө үлдээх хэдхэн хором.','Цувралтай танилцах','#collection'],
 ['hero-4','LaEzer Callus Repair Ampoule-ийн албан ёсны бүтээгдэхүүний зураг','CALLUS REPAIR AMPOULE','Дусал бүрд\nтаны мөч.','Callus Repair Ampoule. Хулганаа хөдөлгөж, ойроос мэдрээрэй.','Хөдөлгөөнөөр мэдрэх','#try']
];
export function fail(message,status=400){throw new OrderError(message,status)}
export const now=()=>new Date().toISOString();
export async function seedCatalog(db){
 if(!db)fail('Үйлчилгээ түр боломжгүй байна.',503);
 if(await db.prepare("SELECT value FROM settings WHERE key='catalog-v7'").first())return;
 const time=now(),statements=PRODUCTS.map((p,i)=>db.prepare('INSERT OR IGNORE INTO products (sku,name,en,step,role,description,tags,image,retail_price,reseller_price,active,bundle,sort_order,revision,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NULL,1,?,?,1,?)').bind(p.sku,p.name,p.en,p.step,p.role,p.desc,JSON.stringify(p.tags),p.bundle?'/assets/products/ampoule.png':`/assets/reference/${['cica','ampoule','cream'][i]}.png`,p.price,p.bundle?1:0,i,time));
 HERO_DEFAULTS.forEach(([id,alt,kicker,title,body,cta,href],i)=>statements.push(db.prepare('INSERT OR IGNORE INTO hero_banners (id,desktop_image,mobile_image,alt,kicker,title,body,cta,href,active,sort_order,revision,updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,1,?)').bind(id,`/assets/official/hero-${i+1}-pc.webp`,`/assets/official/hero-${i+1}-mo.webp`,alt,kicker,title,body,cta,href,i,time)));
 statements.push(db.prepare("INSERT OR IGNORE INTO settings (key,value) VALUES ('catalog-v7','1')"));await db.batch(statements);
}
export function productView(row,audience='retail'){
 const p={sku:row.sku,name:row.name,en:row.en,step:row.step,role:row.role,desc:row.description,tags:JSON.parse(row.tags),image:row.image,price:audience==='reseller'?row.reseller_price:row.retail_price,bundle:!!row.bundle};
 if(audience==='admin')Object.assign(p,{retailPrice:row.retail_price,resellerPrice:row.reseller_price,active:!!row.active,sortOrder:row.sort_order,revision:row.revision});return p;
}
export async function catalog(db,audience='retail'){
 await seedCatalog(db);const result=await db.prepare(`SELECT * FROM products ${audience==='admin'?'':'WHERE active=1'} ORDER BY sort_order,sku`).all();return result.results.map(row=>productView(row,audience));
}
export async function banners(db,all=false){await seedCatalog(db);return(await db.prepare(`SELECT * FROM hero_banners ${all?'':'WHERE active=1'} ORDER BY sort_order,id`).all()).results}
export function text(value,label,min=0,max=500){if(typeof value!=='string')fail(`${label} буруу байна.`);value=value.trim();if(value.length<min||value.length>max)fail(`${label}: ${min}–${max} тэмдэгт оруулна уу.`);return value}
export function imagePath(value){value=text(value,'Зургийн зам',1,500);if(!/^\/(assets|media)\/[a-zA-Z0-9_./-]+$/.test(value)||value.includes('..'))fail('Сайтад байршуулсан зураг сонгоно уу.');return value}
export function price(value,nullable=false){if(nullable&&(value===null||value===''))return null;if(!Number.isSafeInteger(value)||value<1||value>100000000)fail('Үнэ 1–100,000,000₮ хооронд бүхэл тоо байна.');return value}
export function whole(value,min,max,label){if(!Number.isInteger(value)||value<min||value>max)fail(`${label} буруу байна.`);return value}
export function validateProduct(input){
 const sku=text(input.sku,'Код',2,40).toUpperCase();if(!/^[A-Z0-9-]+$/.test(sku))fail('Код нь латин үсэг, тоо, зурааснаас бүрдэнэ.');
 return{sku,name:text(input.name,'Нэр',2,150),en:text(input.en||'','Англи нэр',0,180),step:text(input.step||'','Алхам',0,80),role:text(input.role||'','Зориулалт',0,100),description:text(input.desc||'','Тайлбар',0,2000),tags:JSON.stringify((Array.isArray(input.tags)?input.tags:[]).slice(0,8).map(t=>text(t,'Орц',1,60))),image:imagePath(input.image),retailPrice:price(input.retailPrice),resellerPrice:price(input.resellerPrice,true),active:input.active===false?0:1,bundle:input.bundle?1:0,sortOrder:whole(input.sortOrder??0,0,999,'Эрэмбэ')};
}
export async function saveProduct(db,input){
 const p=validateProduct(input),existing=await db.prepare('SELECT revision FROM products WHERE sku=?').bind(p.sku).first();
 if(existing){if(input.revision!==existing.revision)fail('Энэ бүтээгдэхүүн өөрчлөгдсөн байна. Шинэчлээд дахин хадгална уу.',409);const result=await db.prepare('UPDATE products SET name=?,en=?,step=?,role=?,description=?,tags=?,image=?,retail_price=?,reseller_price=?,active=?,bundle=?,sort_order=?,revision=revision+1,updated_at=? WHERE sku=? AND revision=?').bind(p.name,p.en,p.step,p.role,p.description,p.tags,p.image,p.retailPrice,p.resellerPrice,p.active,p.bundle,p.sortOrder,now(),p.sku,input.revision).run();if(!result.meta?.changes)fail('Өөрчлөлт давхацлаа. Дахин шинэчилнэ үү.',409)}
 else{if(input.revision)fail('Бүтээгдэхүүн олдсонгүй.',404);try{await db.prepare('INSERT INTO products (sku,name,en,step,role,description,tags,image,retail_price,reseller_price,active,bundle,sort_order,revision,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)').bind(...Object.values(p),now()).run()}catch{fail('Ийм кодтой бүтээгдэхүүн бүртгэлтэй байна.',409)}}
 return p.sku;
}
export async function saveBanner(db,input){
 const id=text(input.id,'Баннер',1,40),previous=await db.prepare('SELECT * FROM hero_banners WHERE id=?').bind(id).first();if(!previous)fail('Баннер олдсонгүй.',404);
 if(previous.revision!==input.revision)fail('Баннер шинэчлэгдсэн байна. Дахин ачаална уу.',409);
 const active=input.active?1:0;if(!active&&previous.active){const count=await db.prepare('SELECT count(*) AS n FROM hero_banners WHERE active=1').first();if(count.n<=1)fail('Дор хаяж нэг баннер идэвхтэй байна.')}
 const href=text(input.href,'Холбоос',1,80);if(!/^#[a-zA-Z0-9-]+$/.test(href))fail('Сайтын хэсгийн холбоос сонгоно уу.');
 const result=await db.prepare('UPDATE hero_banners SET desktop_image=?,mobile_image=?,alt=?,kicker=?,title=?,body=?,cta=?,href=?,active=?,sort_order=?,revision=revision+1,updated_at=? WHERE id=? AND revision=? AND (?=1 OR (SELECT count(*) FROM hero_banners WHERE active=1)>1)').bind(imagePath(input.desktop_image),imagePath(input.mobile_image),text(input.alt,'Зургийн тайлбар',2,200),text(input.kicker,'Дээд мөр',0,100),text(input.title,'Гарчиг',2,150),text(input.body,'Тайлбар',0,500),text(input.cta,'Товчны нэр',1,60),href,active,whole(input.sort_order,0,99,'Эрэмбэ'),now(),id,input.revision,active).run();if(!result.meta?.changes)fail('Өөрчлөлт давхацлаа. Дахин ачаална уу.',409);
}
export async function saveAccount(db,input){
 const email=text(input.email,'Имэйл',5,254).toLowerCase();if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))fail('Холбоо барих имэйлээ зөв оруулна уу.');
 const name=text(input.name,'Нэр',2,100),phone=text(input.phone||'','Утас',0,30);if(phone&&!/^\d{8}$/.test(phone))fail('8 оронтой утас оруулна уу.');
 const previous=input.id?await db.prepare("SELECT * FROM accounts WHERE id=? AND role='reseller'").bind(text(input.id,'Бүртгэл',1,100)).first():await db.prepare("SELECT * FROM accounts WHERE email=? AND role='reseller'").bind(email).first();
 if(input.id&&!previous)fail('Reseller олдсонгүй.',404);
 const username=validateUsername(input.username);if(username==='admin')fail('Энэ нэвтрэх нэр админд зориулагдсан байна.');
 const collision=await db.prepare('SELECT id FROM accounts WHERE username=? AND id<>?').bind(username,previous?.id||'').first();if(collision)fail('Ийм нэвтрэх нэр бүртгэлтэй байна.',409);
 const password=input.password===undefined?'':input.password;if(typeof password!=='string')fail('Нууц үгийн хэлбэр буруу байна.');
 if((!previous||!previous.password_hash)&&!password)fail('Шинэ нууц үг тохируулна уу.');
 const passwordHash=password?await hashPassword(password):previous.password_hash,active=input.active===false?0:1,time=now();
 if(previous){const revoke=password||previous.username!==username||previous.active!==active;try{await db.prepare("UPDATE accounts SET name=?,email=?,phone=?,username=?,password_hash=?,active=?,login_version=login_version+?,updated_at=? WHERE id=? AND role='reseller'").bind(name,email,phone,username,passwordHash,active,revoke?1:0,time,previous.id).run()}catch{fail('Имэйл эсвэл нэвтрэх нэр давхардсан байна.',409)}return previous.id}
 const id=crypto.randomUUID();try{await db.prepare("INSERT INTO accounts (id,email,name,phone,role,username,password_hash,active,created_at,updated_at) VALUES (?,?,?,?,'reseller',?,?,1,?,?)").bind(id,email,name,phone,username,passwordHash,time,time).run()}catch{fail('Имэйл эсвэл нэвтрэх нэр давхардсан байна.',409)}return id;
}
export const ORDER_STATUSES={new:'Шинэ',confirmed:'Баталгаажсан',shipped:'Хүргэлтэд',completed:'Дууссан',cancelled:'Цуцлагдсан'};
export function orderView(row){return{id:row.id,name:row.customer_name,phone:row.phone,address:row.address,note:row.note,payment:row.payment,paymentStatus:row.payment_status||'unverified',paidAt:row.paid_at||null,items:JSON.parse(row.items),subtotal:row.subtotal,delivery:row.delivery,total:row.total,status:row.status,notificationStatus:row.notification_status,createdAt:row.created_at,channel:row.channel,resellerName:row.reseller_name||null,resellerEmail:row.reseller_email||null}}
export async function listOrders(db,actor,searchParams){
 const channel=searchParams.get('channel')||'retail',status=searchParams.get('status')||'',q=(searchParams.get('q')||'').slice(0,100),page=Math.max(1,Math.min(100000,parseInt(searchParams.get('page')||'1')||1)),values=[],clauses=[];
 if(actor.role==='reseller'){clauses.push("o.channel='reseller' AND o.reseller_id=?");values.push(actor.id)}else{if(!['retail','reseller'].includes(channel))fail('Захиалгын төрөл буруу байна.');clauses.push('o.channel=?');values.push(channel)}
 if(status){if(!ORDER_STATUSES[status])fail('Төлөв буруу байна.');clauses.push('o.status=?');values.push(status)}
 if(q){clauses.push('(o.id LIKE ? OR o.customer_name LIKE ? OR o.phone LIKE ? OR a.name LIKE ?)');for(let i=0;i<4;i++)values.push('%'+q+'%')}
 const where=clauses.join(' AND '),from='FROM orders o LEFT JOIN accounts a ON a.id=o.reseller_id',count=await db.prepare(`SELECT count(*) AS count,COALESCE(sum(o.total),0) AS total ${from} WHERE ${where}`).bind(...values).first();
 const rows=(await db.prepare(`SELECT o.*,a.name AS reseller_name,a.email AS reseller_email ${from} WHERE ${where} ORDER BY o.created_at DESC,o.id DESC LIMIT 20 OFFSET ?`).bind(...values,(page-1)*20).all()).results;
 return{orders:rows.map(orderView),count:count.count,total:count.total,page,pages:Math.ceil(count.count/20)};
}
