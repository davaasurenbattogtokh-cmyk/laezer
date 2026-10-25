<?php
// Run only against a new disposable MySQL/MariaDB database ending in _test.
declare(strict_types=1);
foreach (['bootstrap','auth','store','orders','payments','http'] as $file) require __DIR__.'/../laezer-private/app/'.$file.'.php';
use function Laezer\{query,one,install,installed,catalog,banners,sign_in,current_actor,sign_out,authorize,save_account,save_product,save_banner,create_order,list_orders,render_hero,qpay_readiness,callback_state,verify_callback_state,record_verified_qpay_payment};
$dsn=getenv('LAEZER_TEST_DSN');if(!$dsn)throw new RuntimeException('Set LAEZER_TEST_DSN to a disposable _test database.');
$db=new PDO($dsn,getenv('LAEZER_TEST_USER')?:'root',getenv('LAEZER_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$name=$db->query('SELECT DATABASE()')->fetchColumn();if(!str_ends_with($name,'_test'))throw new RuntimeException('Refusing non-test database.');
if($db->query('SHOW TABLES')->fetchAll())throw new RuntimeException('Tests require an EMPTY disposable database.');
$count=0;
function check(bool $value,string $message='Assertion failed'):void{if(!$value)throw new RuntimeException($message);}
function rejects(callable $fn,int $status):void{try{$fn();}catch(Laezer\AppError $e){check($e->status===$status,'Wrong error status '.$e->status);return;}throw new RuntimeException('Expected rejection '.$status);}
function test(string $name,callable $fn):void{global $count;$fn();$count++;echo 'PASS '.$name."\n";}
$config=['origin'=>'https://laezer.shop','setup_key'=>str_repeat('test-install-key-',4),'qpay'=>[]];
$adminPassword='FixtureAdmin!ForTests';$partnerPassword='FixturePartner!ForTests';
test('Installation requires a private key and atomically seeds catalog',function()use($db,$config,$adminPassword){
    rejects(fn()=>install($db,['setup_key'=>'wrong'],$config),403);check(!installed($db));
    install($db,['setup_key'=>$config['setup_key'],'username'=>'admin','password'=>$adminPassword,'confirm'=>$adminPassword],$config);
    check(installed($db));check(count(catalog($db))===4);check(count(banners($db))===4);check(password_verify($adminPassword,one($db,"SELECT password_hash FROM accounts WHERE id='admin'")['password_hash']));
    rejects(fn()=>install($db,['setup_key'=>$config['setup_key']],$config),409);
});
$admin=sign_in($db,'admin',$adminPassword,'admin-test');
test('Shared login detects admin; password and session token are hashed',function()use($db,$admin){
    check($admin['actor']['role']==='admin'&&$admin['redirect']==='/admin');check(current_actor($db,$admin['token'])['id']==='admin');
    check(!one($db,'SELECT token_hash FROM login_sessions WHERE token_hash=?',[$admin['token']]));check(!isset($admin['actor']['password_hash']));
    rejects(fn()=>sign_in($db,'admin','incorrect','wrong-admin'),401);
});
$account=['name'=>'Partner One','email'=>'one@example.test','phone'=>'99999999','username'=>'partner-one','password'=>$partnerPassword,'active'=>true];
$partnerId=save_account($db,$account);$secondId=save_account($db,[...$account,'email'=>'two@example.test','username'=>'partner-two']);
$partner=sign_in($db,'PARTNER-ONE',$partnerPassword,'partner-test');$second=sign_in($db,'partner-two',$partnerPassword,'second-test');
test('Shared login detects reseller and never grants admin role',function()use($partner){check($partner['actor']['role']==='reseller'&&$partner['redirect']==='/reseller');rejects(fn()=>authorize($partner['actor'],'admin'),403);rejects(fn()=>authorize(null),401);});
test('Reserved names, duplicate names and blank new passwords are rejected',function()use($db,$account){
    rejects(fn()=>save_account($db,[...$account,'email'=>'duplicate@example.test']),409);
    rejects(fn()=>save_account($db,[...$account,'username'=>'admin']),400);
    rejects(fn()=>save_account($db,[...$account,'username'=>'new-user','email'=>'new@example.test','password'=>'']),400);
    rejects(fn()=>Laezer\password_hash_safe(str_repeat('x',73)),400);
});
test('Failed logins have persistent per-network/name rate limiting',function()use($db){for($i=0;$i<5;$i++)rejects(fn()=>sign_in($db,'unknown','incorrect','limited-test'),401);rejects(fn()=>sign_in($db,'unknown','incorrect','limited-test'),429);});
$products=catalog($db,'admin');$p=$products[0];$p['resellerPrice']=80000;save_product($db,$p);
test('Retail price stays separate from configured reseller price',function()use($db){check(catalog($db)[0]['price']===100000);check(!isset(catalog($db)[0]['resellerPrice']));check(catalog($db,'reseller')[0]['price']===80000);check(catalog($db,'reseller')[1]['price']===null);});
test('Stale product writes and invalid prices cannot overwrite records',function()use($db,$p){rejects(fn()=>save_product($db,$p),409);$current=catalog($db,'admin')[0];rejects(fn()=>save_product($db,[...$current,'retailPrice'=>-1]),400);});
$input=['name'=>'Test Buyer','phone'=>'99999999','address'=>'Ulaanbaatar test address','note'=>'','payment'=>'bank','items'=>[['sku'=>'LZ-001','qty'=>1]],'expectedTotal'=>105000];
$retail=create_order($db,$input,'retail-test-key-0001');
test('Retail orders use canonical prices, store durable receipts and retry once',function()use($db,$input,$retail){
    check($retail['total']===105000&&$retail['bank']['account']==='5374444955');check(count(list_orders($db,['role'=>'admin'],[])['orders'])===1);
    check(create_order($db,$input,'retail-test-key-0001')['id']===$retail['id']);
    rejects(fn()=>create_order($db,[...$input,'name'=>'Other Buyer'],'retail-test-key-0001'),409);
    rejects(fn()=>create_order($db,[...$input,'expectedTotal'=>1],'price-tamper-key-001'),409);
});
$partnerOrder=create_order($db,[...$input,'expectedTotal'=>85000,'role'=>'admin','price'=>1],'partner-order-key-001',$partner['actor']);
test('Reseller orders use server role, wholesale price and owner isolation',function()use($db,$partner,$second,$partnerOrder){
    check($partnerOrder['total']===85000);check(list_orders($db,$partner['actor'],[])['count']===1);check(list_orders($db,$second['actor'],[])['count']===0);
    check(list_orders($db,['role'=>'admin'],['channel'=>'reseller'])['count']===1);
});
test('Missing reseller prices, duplicate items, invalid quantities are rejected',function()use($db,$input,$partner){
    rejects(fn()=>create_order($db,[...$input,'items'=>[['sku'=>'LZ-002','qty'=>1]]],'missing-price-key-001',$partner['actor']),400);
    rejects(fn()=>create_order($db,[...$input,'items'=>[['sku'=>'LZ-001','qty'=>1],['sku'=>'LZ-001','qty'=>1]]],'duplicate-line-key-001'),400);
    rejects(fn()=>create_order($db,[...$input,'items'=>[['sku'=>'LZ-001','qty'=>1.5]]],'invalid-quantity-key-001'),400);
});
test('Free shipping applies at threshold; notification failures preserve orders',function()use($db,$input){
    $r=create_order($db,[...$input,'items'=>[['sku'=>'LZ-001','qty'=>3]],'expectedTotal'=>300000],'free-shipping-key-001',null,function(){throw new RuntimeException('Simulated mail failure');});
    check($r['delivery']===0&&$r['total']===300000&&$r['notificationStatus']==='pending');check(one($db,'SELECT id FROM orders WHERE id=?',[$r['id']])!==null);
});
test('Old orders retain their prices after product edits',function()use($db,$retail,$input){$p=catalog($db,'admin')[0];$p['retailPrice']=110000;save_product($db,$p);check(create_order($db,$input,'retail-test-key-0001')['total']===105000);});
test('Disabling, reactivation and password reset revoke previous sessions',function()use($db,$account,$partnerId,$partner,$partnerPassword){
    save_account($db,[...$account,'id'=>$partnerId,'password'=>'','active'=>false]);check(current_actor($db,$partner['token'])===null);
    save_account($db,[...$account,'id'=>$partnerId,'password'=>'','active'=>true]);check(current_actor($db,$partner['token'])===null);
    $session=sign_in($db,'partner-one',$partnerPassword,'reset-test');save_account($db,[...$account,'id'=>$partnerId,'password'=>'FixtureReset!ForTests']);check(current_actor($db,$session['token'])===null);
    rejects(fn()=>sign_in($db,'partner-one',$partnerPassword,'old-password-test'),401);
});
test('Session expiry and logout revoke access',function()use($db,$admin){check(current_actor($db,$admin['token'],time()+Laezer\SESSION_TTL+1)===null);sign_out($db,$admin['token']);check(current_actor($db,$admin['token'])===null);});
test('Banner revisions and minimum active slide are enforced',function()use($db){
    $rows=banners($db,true);$b=$rows[0];$b['title']='<script>alert(1)</script>';save_banner($db,$b);rejects(fn()=>save_banner($db,$b),409);
    for($i=0;$i<3;$i++){ $b=banners($db,true)[$i];$b['active']=false;save_banner($db,$b); }
    $last=banners($db,true)[3];$last['active']=false;rejects(fn()=>save_banner($db,$last),400);
    $html=render_hero('<div class="official-slides">old<div class="slide-arrows">',banners($db,true));check(str_contains($html,'&lt;script&gt;'));check(!str_contains($html,'<script>alert'));
});
test('CSRF checks require configured exact origin and reject cross-site writes',function(){$_SERVER['HTTP_ORIGIN']='https://evil.test';rejects(fn()=>Laezer\same_origin(['origin'=>'https://laezer.shop']),403);$_SERVER['HTTP_ORIGIN']='https://laezer.shop';$_SERVER['HTTP_SEC_FETCH_SITE']='cross-site';rejects(fn()=>Laezer\same_origin(['origin'=>'https://laezer.shop']),403);$_SERVER['HTTP_SEC_FETCH_SITE']='same-origin';Laezer\same_origin(['origin'=>'https://laezer.shop']);});
test('QPay remains disabled even with credentials and forged state fails',function()use($config){$r=qpay_readiness($config);check(!$r['available']&&count($r['missing'])===5);$a=['id'=>'p1','order_id'=>'o1','environment'=>'sandbox','amount'=>100,'currency'=>'MNT'];$key=str_repeat('k',32);$s=callback_state($a,$key);check(verify_callback_state($a,$key,$s));check(!verify_callback_state([...$a,'amount'=>1],$key,$s));check(!qpay_readiness(['qpay'=>['enabled'=>true,'environment'=>'sandbox','client_id'=>'fixture','client_secret'=>'fixture','invoice_code'=>'fixture','public_origin'=>'https://laezer.shop','callback_secret'=>$key]])['available']);});
test('QPay verified evidence checks amount, replay and separate fulfillment',function()use($db,$retail){
    query($db,"UPDATE orders SET payment='qpay' WHERE id=?",[$retail['id']]);
    query($db,"INSERT INTO payment_attempts (id,order_id,amount,status,provider_invoice_id,created_at,updated_at) VALUES (?,?,?,'pending','inv-fixture',?,?)",['pay-fixture',$retail['id'],$retail['total'],Laezer\now(),Laezer\now()]);
    $e=['paymentId'=>'payment-fixture','environment'=>'sandbox','invoiceId'=>'inv-fixture','status'=>'PAID','currency'=>'MNT','amount'=>$retail['total']];
    rejects(fn()=>record_verified_qpay_payment($db,'pay-fixture',[...$e,'amount'=>1]),422);
    record_verified_qpay_payment($db,'pay-fixture',$e);record_verified_qpay_payment($db,'pay-fixture',$e);
    $o=one($db,'SELECT status,payment_status FROM orders WHERE id=?',[$retail['id']]);check($o['payment_status']==='paid'&&$o['status']==='new');
    rejects(fn()=>record_verified_qpay_payment($db,'pay-fixture',[...$e,'paymentId'=>'different-payment']),409);
});
echo "OK: $count MySQL integration tests passed.\n";
