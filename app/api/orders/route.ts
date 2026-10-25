import {catalog} from '../../../server/store.js';
import { env } from 'cloudflare:workers';
import { createOrder, notifyMerchant, OrderError } from '../../../server/orders.js';
export const dynamic='force-dynamic';
export async function POST(request:Request){
 try{
  const origin=request.headers.get('origin');if(origin&&origin!==new URL(request.url).origin)return Response.json({error:'Зөвшөөрөгдөөгүй хүсэлт.'},{status:403});
  if(!request.headers.get('content-type')?.includes('application/json'))return Response.json({error:'JSON мэдээлэл шаардлагатай.'},{status:415});
  const body=await request.text();if(body.length>10000)return Response.json({error:'Хүсэлт хэт том байна.'},{status:413});
  let input;try{input=JSON.parse(body)}catch{return Response.json({error:'Мэдээлэл унших боломжгүй байна.'},{status:400})}
  const order=await createOrder({input,key:request.headers.get('Idempotency-Key'),db:env.DB,products:await catalog(env.DB),notify:import.meta.env.DEV?undefined:notifyMerchant});
  return Response.json({order},{status:201,headers:{'Cache-Control':'no-store'}});
 }catch(error){
  if(error instanceof OrderError)return Response.json({error:error.message},{status:error.status});
  console.error('Order storage failed');return Response.json({error:'Захиалгыг хадгалж чадсангүй. Сагс тань хадгалагдсан, дахин оролдоно уу.'},{status:503});
 }
}
