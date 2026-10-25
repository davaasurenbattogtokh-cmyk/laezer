import {qpayNotConnected} from '../../../../../server/payments/qpay.js';
export const dynamic='force-dynamic';
// Reserved invoice / check / callback contract. Deliberately fails closed even if
// credentials are supplied. Implement and verify the adapter before releasing it.
function unavailable(request:Request){
 const action=new URL(request.url).pathname.split('/').pop();
 if(!['invoice','check','callback'].includes(action||''))return Response.json({error:'Хуудас олдсонгүй.'},{status:404,headers:{'Cache-Control':'no-store'}});
 try{qpayNotConnected()}catch{return Response.json({error:'QPay холболт хараахан идэвхжээгүй байна.',code:'QPAY_NOT_CONNECTED'},{status:503,headers:{'Cache-Control':'no-store'}})}
}
export const GET=unavailable;export const POST=unavailable;
