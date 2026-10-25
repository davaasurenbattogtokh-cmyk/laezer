import {fail} from './store.js';
export async function storeImage(bucket,file){
 if(!bucket)fail('Зураг хадгалах үйлчилгээ бэлэн биш байна.',503);
 if(!file||typeof file.arrayBuffer!=='function'||file.size<12||file.size>8*1024*1024)fail('8MB хүртэл PNG, JPG, WebP зураг сонгоно уу.');
 const bytes=new Uint8Array(await file.arrayBuffer()),isPng=bytes.slice(0,8).join(',')==='137,80,78,71,13,10,26,10',isJpg=bytes[0]===255&&bytes[1]===216&&bytes[2]===255,isWebp=String.fromCharCode(...bytes.slice(0,4))==='RIFF'&&String.fromCharCode(...bytes.slice(8,12))==='WEBP';
 const ext=isPng?'png':isJpg?'jpg':isWebp?'webp':null;if(!ext)fail('PNG, JPG эсвэл WebP зураг шаардлагатай.');const key=crypto.randomUUID()+'.'+ext;
 await bucket.put(key,bytes,{httpMetadata:{contentType:ext==='jpg'?'image/jpeg':`image/${ext}`}});return'/media/'+key;
}
