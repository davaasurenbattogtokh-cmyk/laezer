<?php
declare(strict_types=1);
namespace Laezer;
const BANK = ['bank'=>'Хаан банк','account'=>'5374444955','holder'=>'А.Цэндаюуш'];
function order_customer(array $input): array {
    $name=text($input['name']??null,'Хүлээн авагч',2,80);$phone=text($input['phone']??null,'Утас',8,8);
    if(!preg_match('/^[0-9]{8}$/D',$phone))fail('8 оронтой утасны дугаар оруулна уу.');
    $address=text($input['address']??null,'Хүргэлтийн хаяг',4,500);$note=text($input['note']??'','Тэмдэглэл',0,1000);
    $payment=$input['payment']??null;if(!in_array($payment,['bank','cod'],true))fail('Төлбөрийн хэлбэрээ сонгоно уу.');
    $raw=$input['items']??null;if(!is_array($raw)||!array_is_list($raw)||count($raw)<1||count($raw)>100)fail('Сагс хоосон эсвэл буруу байна.');
    $items=[];$seen=[];foreach($raw as $item){if(!is_array($item))fail('Сагсны мэдээлэл буруу байна.');$sku=text($item['sku']??null,'Код',2,40);if(isset($seen[$sku]))fail('Сагсны мөр давхардсан байна.');$seen[$sku]=true;$items[]=['sku'=>$sku,'qty'=>whole($item['qty']??null,1,99,'Тоо ширхэг')];}
    return compact('name','phone','address','note','payment','items');
}
function order_receipt(array $row): array {
    return ['id'=>$row['id'],'createdAt'=>$row['created_at'],'payment'=>$row['payment'],'paymentStatus'=>$row['payment_status']??'unverified','paidAt'=>$row['paid_at']??null,'items'=>json_decode($row['items'],true,32,JSON_THROW_ON_ERROR),'subtotal'=>(int)$row['subtotal'],'delivery'=>(int)$row['delivery'],'total'=>(int)$row['total'],'notificationStatus'=>$row['notification_status'],'bank'=>$row['payment']==='bank'?BANK:null];
}
function create_order(\PDO $db, array $input, mixed $key, ?array $actor = null, ?callable $notify = null): array {
    if(!is_string($key)||!preg_match('/^[a-zA-Z0-9-]{16,100}$/D',$key))fail('Захиалгын баталгаажуулах түлхүүр буруу байна.');
    $customer=order_customer($input);$channel=($actor['role']??'')==='reseller'?'reseller':'retail';$resellerId=$channel==='reseller'?$actor['id']:null;
    $fingerprint=hash('sha256',json_encode_safe([$customer,$channel,$resellerId]));
    $previous=one($db,'SELECT * FROM orders WHERE idempotency_key=?',[$key]);
    if($previous){if(!hash_equals($previous['request_hash'],$fingerprint))fail('Мэдээлэл өөрчлөгдсөн байна. Захиалгаа дахин эхлүүлнэ үү.',409);return order_receipt($previous);}
    try { $row=transaction($db,function() use($db,$input,$key,$customer,$channel,$resellerId,$fingerprint) {
        $products=catalog($db,$channel,true);$bySku=array_column($products,null,'sku');$lines=[];$subtotal=0;
        foreach($customer['items'] as $item){$p=$bySku[$item['sku']]??null;if(!$p||$p['price']===null)fail('Бүтээгдэхүүн захиалах боломжгүй эсвэл reseller үнэ тохируулаагүй байна.');$line=['sku'=>$p['sku'],'name'=>$p['name'],'price'=>$p['price'],'qty'=>$item['qty'],'lineTotal'=>$p['price']*$item['qty']];$lines[]=$line;$subtotal+=$line['lineTotal'];}
        $delivery=$subtotal>=250000?0:5000;$total=$subtotal+$delivery;
        if(array_key_exists('expectedTotal',$input)&&(!is_int($input['expectedTotal'])||$input['expectedTotal']!==$total))fail('Бүтээгдэхүүний үнэ шинэчлэгдсэн байна. Сагсны дүнгээ дахин шалгаад захиална уу.',409);
        $row=['id'=>'LZ-'.gmdate('ymd').'-'.strtoupper(substr(uid(),0,8)),'idempotency_key'=>$key,'request_hash'=>$fingerprint,'customer_name'=>$customer['name'],'phone'=>$customer['phone'],'address'=>$customer['address'],'note'=>$customer['note'],'payment'=>$customer['payment'],'items'=>json_encode_safe($lines),'subtotal'=>$subtotal,'delivery'=>$delivery,'total'=>$total,'status'=>'new','notification_status'=>'pending','created_at'=>now(),'channel'=>$channel,'reseller_id'=>$resellerId];
        query($db,'INSERT INTO orders (id,idempotency_key,request_hash,customer_name,phone,address,note,payment,items,subtotal,delivery,total,status,notification_status,created_at,channel,reseller_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',array_values($row));return $row;
    }); } catch(\PDOException $e) {
        // A concurrent retry can win the unique key. Return only the same request.
        $previous=one($db,'SELECT * FROM orders WHERE idempotency_key=?',[$key]);
        if($previous){if(!hash_equals($previous['request_hash'],$fingerprint))fail('Захиалгын түлхүүр давхардсан байна.',409);return order_receipt($previous);}throw $e;
    }
    if($notify){try{$sent=$notify($row,$actor);if($sent){query($db,"UPDATE orders SET notification_status='sent' WHERE id=?",[$row['id']]);$row['notification_status']='sent';}}catch(\Throwable){/* Durable order survives a notification failure. */}}
    return order_receipt($row);
}
function notify_merchant(array $row, ?array $actor, array $config): bool {
    $settings=$config['notifications']??[];$email=$settings['email']??'';
    if(empty($settings['enabled'])||!filter_var($email,FILTER_VALIDATE_EMAIL)||!function_exists('curl_init'))return false;
    $body=['_subject'=>'LaEzer шинэ захиалга '.$row['id'],'_template'=>'table','_captcha'=>'false','Төрөл'=>$row['channel'],'Reseller'=>$actor['name']??'','Захиалгын дугаар'=>$row['id'],'Хүлээн авагч'=>$row['customer_name'],'Утас'=>$row['phone'],'Хаяг'=>$row['address'],'Бараа'=>$row['items'],'НИЙТ'=>$row['total'],'Төлбөр'=>$row['payment'],'Тэмдэглэл'=>$row['note']];
    $curl=curl_init('https://formsubmit.co/ajax/'.rawurlencode($email));
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode_safe($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $response=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if(!is_string($response)||$status<200||$status>=300)return false;$result=json_decode($response,true);return in_array($result['success']??false,[true,'true'],true);
}
