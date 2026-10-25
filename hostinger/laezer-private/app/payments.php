<?php
declare(strict_types=1);
namespace Laezer;
function qpay_readiness(array $config): array {
    $q=$config['qpay']??[];$required=['client_id','client_secret','invoice_code','public_origin','callback_secret'];$missing=[];$errors=[];
    foreach($required as $key)if(!is_string($q[$key]??null)||trim($q[$key])==='')$missing[]='QPAY_'.strtoupper($key);
    $environment=$q['environment']??'sandbox';if(!in_array($environment,['sandbox','production'],true))$errors[]='environment';
    if(!empty($q['callback_secret'])&&strlen($q['callback_secret'])<32)$errors[]='callback_secret';
    if(!empty($q['public_origin'])){$url=parse_url($q['public_origin']);if(!$url||($url['scheme']??'')!=='https'||empty($url['host'])||!in_array($url['path']??'',['','/'],true)||isset($url['query'])||isset($url['fragment'])||isset($url['user'])||isset($url['pass']))$errors[]='public_origin';}
    $configured=!$missing&&!$errors;$enabled=($q['enabled']??false)===true;
    return ['provider'=>'qpay','environment'=>$environment,'enabled'=>$enabled,'configured'=>$configured,'available'=>false,'phase'=>!$enabled?'disabled':(!$configured?'configuration_required':'adapter_pending'),'missing'=>$missing,'errors'=>$errors];
}
function callback_state(array $attempt, string $secret): string {
    if(strlen($secret)<32)fail('QPay тохиргоо бэлэн биш байна.',503);
    return hash_hmac('sha256',implode('|',[$attempt['id'],$attempt['order_id'],$attempt['environment'],$attempt['amount'],$attempt['currency']]),$secret);
}
function verify_callback_state(array $attempt, string $secret, string $state): bool {
    return strlen($secret)>=32&&preg_match('/^[a-f0-9]{64}$/D',$state)&&hash_equals(callback_state($attempt,$secret),$state);
}
// This helper accepts evidence ONLY from a future server-side Merchant API adapter.
// HTTP callbacks and browser success messages must never call it as evidence.
function record_verified_qpay_payment(\PDO $db, string $attemptId, array $evidence): array {
    return transaction($db,function() use($db,$attemptId,$evidence) {
        $a=one($db,'SELECT * FROM payment_attempts WHERE id=? FOR UPDATE',[$attemptId]);
        if(!$a)fail('Нэхэмжлэх олдсонгүй.',404);
        $paymentId=$evidence['paymentId']??'';$amount=$evidence['amount']??null;
        if($a['provider']!=='qpay'||!$a['provider_invoice_id']||($evidence['environment']??null)!==$a['environment']||($evidence['invoiceId']??null)!==$a['provider_invoice_id']||($evidence['status']??null)!=='PAID'||($evidence['currency']??null)!=='MNT'||!is_int($amount)||$amount!==(int)$a['amount']||!is_string($paymentId)||!preg_match('/^[a-zA-Z0-9_.:-]{1,200}$/D',$paymentId))fail('Төлбөрийн баталгаажуулалтын мэдээлэл зөрсөн байна.',422);
        if(!in_array($a['status'],['pending','paid'],true)||($a['provider_payment_id']!==null&&$a['provider_payment_id']!==$paymentId))fail('Нэхэмжлэх баталгаажуулах төлөвт биш байна.',409);
        $order=one($db,'SELECT * FROM orders WHERE id=? FOR UPDATE',[$a['order_id']]);
        if(!$order||(int)$order['total']!==$amount||$order['payment']!=='qpay'||$order['status']==='cancelled')fail('Захиалгын мэдээлэл зөрсөн байна.',409);
        try {query($db,"UPDATE payment_attempts SET status='paid',provider_payment_id=?,paid_at=COALESCE(paid_at,?),updated_at=? WHERE id=?",[$paymentId,now(),now(),$attemptId]);}
        catch(\PDOException $e){if((int)($e->errorInfo[1]??0)===1062)fail('Гүйлгээ өөр нэхэмжлэхэд бүртгэлтэй байна.',409);throw $e;}
        query($db,"UPDATE orders SET payment_status='paid',paid_at=COALESCE(paid_at,?) WHERE id=?",[now(),$a['order_id']]);return ['orderId'=>$a['order_id'],'paymentStatus'=>'paid'];
    });
}
