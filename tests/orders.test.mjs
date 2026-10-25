import test from 'node:test';
import assert from 'node:assert/strict';
import {DatabaseSync} from 'node:sqlite';
import {readFileSync,readdirSync} from 'node:fs';
import {calculateCart} from '../public/catalog.js';
import {createOrder,validateOrder,OrderError} from '../server/orders.js';
const payload={name:'Туршилтын хэрэглэгч',phone:'99999999',address:'Тест хүргэлтийн хаяг',note:'Локал шалгалт',payment:'bank',items:[{sku:'LZ-001',qty:1}]};
function database(){const db=new DatabaseSync(':memory:');for(const file of readdirSync('drizzle').filter(f=>f.endsWith('.sql')))db.exec(readFileSync('drizzle/'+file,'utf8'));return{raw:db,prepare(sql){const stmt=db.prepare(sql);return{bind(...params){return{first:async()=>stmt.get(...params),run:async()=>({success:true,...stmt.run(...params)})}}}}}}
test('catalog prices and the inclusive free delivery threshold',()=>{
 assert.equal(calculateCart([{sku:'LZ-001',qty:1}]).total,105000);
 assert.equal(calculateCart([{sku:'LZ-001',qty:1},{sku:'LZ-003',qty:1}]).delivery,0);
 assert.equal(calculateCart([{sku:'LZ-SET',qty:1}]).total,350000);
 assert.equal(calculateCart([]).total,0);
});
test('server ignores forged prices and rejects malformed quantities and products',()=>{
 assert.equal(validateOrder({...payload,total:1,items:[{sku:'LZ-001',qty:1,price:1}]}).total,105000);
 for(const items of [[{sku:'LZ-001',qty:0}],[{sku:'LZ-001',qty:1.1}],[{sku:'INVALID',qty:1}],[{sku:'LZ-001',qty:1},{sku:'LZ-001',qty:1}]])assert.throws(()=>validateOrder({...payload,items}),OrderError);
 assert.throws(()=>validateOrder({...payload,phone:'123'}),OrderError);
 assert.throws(()=>validateOrder({...payload,payment:'card'}),OrderError);
});
test('durable order is recorded once and retries return the same receipt',async()=>{
 const db=database();let sent=0;const args={input:payload,key:'test-order-idempotent-123',db,notify:async()=>{sent++;return true}};
 const first=await createOrder(args),again=await createOrder(args);
 assert.equal(first.id,again.id);assert.equal(sent,1);assert.equal(first.total,105000);assert.equal(first.bank.account,'5374444955');assert.equal(first.notificationStatus,'sent');
 assert.equal(db.raw.prepare('SELECT count(*) AS n FROM orders').get().n,1);
 await assert.rejects(()=>createOrder({...args,input:{...payload,address:'Өөр хаяг 1234'}}),e=>e.status===409);
});
test('notification failure does not erase an accepted order',async()=>{
 const db=database();const order=await createOrder({input:{...payload,payment:'cod'},key:'test-pending-notify-123',db,notify:async()=>{throw Error('mail offline')}});
 assert.equal(order.notificationStatus,'pending');assert.equal(order.bank,null);assert.equal(db.raw.prepare('SELECT count(*) AS n FROM orders').get().n,1);
});
test('concurrent retries create only one durable order',async()=>{
 const db=database();const args={input:payload,key:'test-parallel-order-123',db};
 const [a,b]=await Promise.all([createOrder(args),createOrder(args)]);assert.equal(a.id,b.id);assert.equal(db.raw.prepare('SELECT count(*) AS n FROM orders').get().n,1);
});
