import {qpayReadiness} from '../../../../server/payments/qpay.js';
import {storeImage} from '../../../../server/media.js';
import {env} from 'cloudflare:workers';
import {currentActor} from '../../../../server/password-auth.js';
import {authorize,sameOrigin} from '../../../../server/access.js';
import {catalog,banners,saveProduct,saveBanner,saveAccount,listOrders,ORDER_STATUSES,fail,text} from '../../../../server/store.js';
import {OrderError,createOrder,notifyMerchant} from '../../../../server/orders.js';
const json=(data:any,status=200,headers={})=>Response.json(data,{status,headers:{'Cache-Control':'no-store',...headers}});
export const dynamic='force-dynamic';
async function body(request:Request){if(!request.headers.get('content-type')?.includes('application/json'))fail('JSON мэдээлэл шаардлагатай.',415);const raw=await request.text();if(raw.length>20000)fail('Хүсэлт хэт том байна.',413);try{return JSON.parse(raw)}catch{fail('Мэдээлэл буруу байна.')}}
async function dispatch(request:Request){try{
 const db=env.DB,url=new URL(request.url),path=url.pathname.replace('/api/portal/',''),write=request.method==='POST';
 if(write)sameOrigin(request);
 const actor=await currentActor({db,env,request,dev:import.meta.env.DEV});
 if(path==='me'&&!write)return json({actor,signIn:'/login',signOut:'/api/auth/logout'});
 if(path==='catalog'&&!write){authorize(actor,'reseller');return json({products:await catalog(db,'reseller')})}
 if(path==='orders'&&!write){authorize(actor);return json(await listOrders(db,actor,url.searchParams))}
 if(path==='orders'&&write){authorize(actor,'reseller');const input=await body(request),products=await catalog(db,'reseller');const order=await createOrder({input,key:request.headers.get('Idempotency-Key'),db,products,actor,notify:import.meta.env.DEV?undefined:notifyMerchant});return json({order},201)}
 authorize(actor,'admin');
 if(path==='admin/payments'&&!write)return json({qpay:qpayReadiness(env)});
 if(path==='admin/products'&&!write)return json({products:await catalog(db,'admin')});
 if(path==='admin/products'&&write){await saveProduct(db,await body(request));return json({ok:true})}
 if(path==='admin/banners'&&!write)return json({banners:await banners(db,true)});
 if(path==='admin/banners'&&write){await saveBanner(db,await body(request));return json({ok:true})}
 if(path==='admin/accounts'&&!write){const result=await db.prepare("SELECT id,email,name,phone,username,active,created_at,(password_hash IS NOT NULL) AS credentials_ready FROM accounts WHERE role='reseller' ORDER BY created_at DESC").all();return json({accounts:result.results})}
 if(path==='admin/accounts'&&write){const id=await saveAccount(db,await body(request));return json({id})}
 if(path==='admin/status'&&write){const input=await body(request);if(!ORDER_STATUSES[input.status])fail('Төлөв буруу байна.');const result=await db.prepare('UPDATE orders SET status=? WHERE id=?').bind(input.status,text(input.id,'Захиалга',1,80)).run();if(!result.meta?.changes)fail('Захиалга олдсонгүй.',404);return json({ok:true})}
 if(path==='admin/upload'&&write){
  if(Number(request.headers.get('content-length'))>8500000)fail('Зураг 8MB-аас бага байна.',413);
  const data=await request.formData(),file=data.get('file');if(!(file instanceof File))fail('Зураг сонгоно уу.');return json({url:await storeImage(env.BUCKET,file)},201);
 }
 return json({error:'Хуудас олдсонгүй.'},404);
 }catch(error){if(error instanceof OrderError)return json({error:error.message},error.status);console.error('Portal operation failed');return json({error:'Үйлчилгээтэй холбогдож чадсангүй. Дахин оролдоно уу.'},503)}}
export const GET=dispatch;export const POST=dispatch;
