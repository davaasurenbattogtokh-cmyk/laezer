<?php
declare(strict_types=1);
namespace Laezer;
function install(\PDO $db, array $input, array $config): void {
    $key=$config['setup_key']??'';$supplied=$input['setup_key']??'';
    if(!is_string($key)||strlen($key)<32||str_starts_with($key,'REPLACE_'))fail('Суулгах нууц түлхүүрийг тохируулна уу.',503);
    if(!is_string($supplied)||!hash_equals($key,$supplied))fail('Суулгах түлхүүр буруу байна.',403);
    if(installed($db))fail('Сайт аль хэдийн суулгагдсан байна.',409);
    if(($input['username']??'')!=='admin')fail('Админы нэвтрэх нэр admin байна.');
    if(!is_string($input['password']??null)||($input['password']??'')!==($input['confirm']??null))fail('Нууц үгийн давталт таарахгүй байна.');
    $hash=password_hash_safe($input['password']);
    $schema=file_get_contents(__DIR__.'/schema.sql');
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$schema) as $statement)if(trim($statement)!=='')$db->exec($statement);
    query($db,"INSERT IGNORE INTO settings (`key`,value) VALUES ('installation_guard','1')");
    transaction($db,function() use($db,$hash) {
        one($db,"SELECT value FROM settings WHERE `key`='installation_guard' FOR UPDATE");
        if(installed($db))fail('Сайт аль хэдийн суулгагдсан байна.',409);
        $seed=json_decode(file_get_contents(__DIR__.'/seed.json'),true,64,JSON_THROW_ON_ERROR);$time=now();
        foreach($seed['products'] as $p)query($db,'INSERT INTO products (sku,name,en,step,role,description,tags,image,retail_price,reseller_price,active,bundle,sort_order,revision,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NULL,1,?,?,1,?)',[$p['sku'],$p['name'],$p['en'],$p['step'],$p['role'],$p['desc'],json_encode_safe($p['tags']),$p['image'],$p['price'],!empty($p['bundle'])?1:0,$p['sortOrder'],$time]);
        foreach($seed['banners'] as $b)query($db,'INSERT INTO hero_banners (id,desktop_image,mobile_image,alt,kicker,title,body,cta,href,active,sort_order,revision,updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,1,?)',[$b['id'],$b['desktop_image'],$b['mobile_image'],$b['alt'],$b['kicker'],$b['title'],$b['body'],$b['cta'],$b['href'],$b['sort_order'],$time]);
        query($db,"INSERT INTO accounts (id,name,role,username,password_hash,active,created_at,updated_at) VALUES ('admin','Админ','admin','admin',?,1,?,?)",[$hash,$time,$time]);
        query($db,"INSERT INTO settings (`key`,value) VALUES ('installed',?)",[$time]);
    });
}
function setup_page(?string $error = null, bool $done = false): never {
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="mn"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LaEzer — Суулгах</title><link rel="stylesheet" href="/portal.css"><link rel="stylesheet" href="/login.css"><style>body{display:block}main{max-width:520px;margin:7vh auto;padding:28px}h1{font-size:28px;line-height:1.3;margin:28px 0 16px}p{line-height:1.8;font-size:14px}form{margin-top:28px}label{display:block;margin-bottom:18px;font-size:13px}input{display:block;width:100%;height:48px;margin-top:8px;padding:12px;border:1px solid #d7dccd;border-radius:8px;background:white;font:inherit}button{padding:15px 28px;border:0;border-radius:8px;background:#456a4d;color:white;font:inherit;cursor:pointer}</style><body><main><img src="/assets/official/laezer-logo.png" alt="LaEzer" width="130"><h1>Сайтыг суулгах</h1>';
    if($done){echo '<p>Суулгалт дууслаа. Суулгах хуудас автоматаар хаагдсан.</p><a href="/login">Нэвтрэх →</a>';}
    else{echo '<p>Hostinger-ийн database холболт config.php файлд тохируулагдсан байна. Суулгах түлхүүрээ оруулаад админы нууц үгийг тохируулна уу.</p>'.($error?'<p role="alert" style="color:#a12929">'.escape($error).'</p>':'').'<form method="post" action="/setup" autocomplete="off"><label>Суулгах түлхүүр<input name="setup_key" type="password" required autocomplete="off"></label><label>Админы нэр<input name="username" value="admin" readonly></label><label>Нууц үг<input name="password" type="password" minlength="10" maxlength="72" required autocomplete="new-password"></label><label>Нууц үг давтах<input name="confirm" type="password" minlength="10" maxlength="72" required autocomplete="new-password"></label><button type="submit">Суулгах</button></form>';
    } echo '</main></body></html>';exit;
}
function html_page(string $file, array $config, ?\PDO $db = null): never {
    $html=file_get_contents(__DIR__.'/templates/'.$file);
    if($file==='site.html'){
        if(!$db)fail('Үйлчилгээ бэлэн биш байна.',503);$html=render_hero($html,banners($db));
        $html=str_replace('</head>','<link rel="canonical" href="'.escape($config['origin']).'/"></head>',$html);
    }else header('X-Robots-Tag: noindex, nofollow');
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');echo $html;exit;
}
function serve_media(string $key): never {
    if(!preg_match('/^[a-f0-9]{32}\.(png|jpg|webp)$/D',$key))fail('Зураг олдсонгүй.',404);
    $path=LAEZER_PRIVATE_DIR.'/uploads/'.$key;if(!is_file($path))fail('Зураг олдсонгүй.',404);
    $mime=['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'][pathinfo($key,PATHINFO_EXTENSION)];
    header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="'.$key.'"');header('Cache-Control: public,max-age=31536000,immutable');readfile($path);exit;
}
function api(\PDO $db, array $config, string $path, string $method): never {
    header('Cache-Control: no-store');
    if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');fail('Хүсэлтийн арга зөвшөөрөгдөөгүй.',405);}
    if(str_starts_with($path,'/api/payments/qpay/'))respond(['error'=>'QPay холболт хараахан идэвхжээгүй байна.','code'=>'QPAY_NOT_CONNECTED'],503);
    if($method==='POST')same_origin($config);
    if($path==='/api/catalog'&&$method==='GET')respond(['products'=>catalog($db)]);
    if($path==='/api/orders'&&$method==='POST')respond(['order'=>create_order($db,body(10000),$_SERVER['HTTP_IDEMPOTENCY_KEY']??null,null,fn($row,$actor)=>notify_merchant($row,$actor,$config))],201);
    if($path==='/api/auth/login'&&$method==='POST'){
        $input=body(4096);$result=sign_in($db,$input['username']??null,$input['password']??null,$_SERVER['REMOTE_ADDR']??'unknown');
        set_session_cookie($result['token']);respond(['actor'=>$result['actor'],'redirect'=>$result['redirect']]);
    }
    if($path==='/api/auth/logout'&&$method==='POST'){sign_out($db);set_session_cookie('',true);respond(['ok'=>true,'redirect'=>'/login']);}
    $actor=current_actor($db);
    if($path==='/api/portal/me'&&$method==='GET')respond(['actor'=>$actor,'signIn'=>'/login','signOut'=>'/api/auth/logout']);
    if(!str_starts_with($path,'/api/portal/'))fail('Хуудас олдсонгүй.',404);
    authorize($actor);
    $route=substr($path,12);
    if($route==='catalog'&&$method==='GET'){authorize($actor,'reseller');respond(['products'=>catalog($db,'reseller')]);}
    if($route==='orders'&&$method==='GET')respond(list_orders($db,$actor,$_GET));
    if($route==='orders'&&$method==='POST'){authorize($actor,'reseller');respond(['order'=>create_order($db,body(),$_SERVER['HTTP_IDEMPOTENCY_KEY']??null,$actor,fn($row,$who)=>notify_merchant($row,$who,$config))],201);}
    authorize($actor,'admin');
    if($route==='admin/payments'&&$method==='GET')respond(['qpay'=>qpay_readiness($config)]);
    if($route==='admin/products'&&$method==='GET')respond(['products'=>catalog($db,'admin')]);
    if($route==='admin/products'&&$method==='POST'){save_product($db,body());respond(['ok'=>true]);}
    if($route==='admin/banners'&&$method==='GET')respond(['banners'=>banners($db,true)]);
    if($route==='admin/banners'&&$method==='POST'){save_banner($db,body());respond(['ok'=>true]);}
    if($route==='admin/accounts'&&$method==='GET'){
        $accounts=query($db,"SELECT id,email,name,phone,username,active,created_at,1 AS credentials_ready FROM accounts WHERE role='reseller' ORDER BY created_at DESC")->fetchAll();
        foreach($accounts as &$a){$a['active']=(int)$a['active'];$a['credentials_ready']=1;}respond(['accounts'=>$accounts]);
    }
    if($route==='admin/accounts'&&$method==='POST')respond(['id'=>save_account($db,body())]);
    if($route==='admin/status'&&$method==='POST'){
        $input=body();$status=$input['status']??null;if(!is_string($status)||!isset(ORDER_STATUSES[$status]))fail('Төлөв буруу байна.');$id=text($input['id']??null,'Захиалга',1,40);
        if(!one($db,'SELECT id FROM orders WHERE id=?',[$id]))fail('Захиалга олдсонгүй.',404);
        query($db,'UPDATE orders SET status=? WHERE id=?',[$status,$id]);respond(['ok'=>true]);
    }
    if($route==='admin/upload'&&$method==='POST'){
        if((int)($_SERVER['CONTENT_LENGTH']??0)>9*1024*1024)fail('Зураг 8MB-аас бага байна.',413);
        if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','multipart/form-data'))fail('Зургийн хэлбэр буруу байна.',415);
        respond(['url'=>store_image($_FILES['file']??[])],201);
    }
    fail('Хуудас олдсонгүй.',404);
}
function handle(): never {
    $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$method=$_SERVER['REQUEST_METHOD']??'GET';
    header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');header('X-Frame-Options: DENY');
    try{
        $config=config();$host=strtolower($_SERVER['HTTP_HOST']??'');$expected=strtolower(parse_url($config['origin'],PHP_URL_HOST));
        if(!in_array($host,[$expected,'www.'.$expected],true))fail('Зөвшөөрөгдөөгүй домэйн.',421);
        $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['SERVER_PORT']??'')==='443';
        if(!$secure||$host!==$expected){header('Location: '.$config['origin'].($_SERVER['REQUEST_URI']??'/'),true,308);exit;}
        if(str_starts_with($path,'/media/')&&$method==='GET')serve_media(substr($path,7));
        $db=database($config);$installed=installed($db);
        if($path==='/setup'){
            if($installed){header('Location: /login',true,303);exit;}
            if($method==='POST'){
                same_origin($config);
                if((int)($_SERVER['CONTENT_LENGTH']??0)>8192)fail('Хүсэлт хэт том байна.',413);
                try{install($db,$_POST,$config);setup_page(null,true);}catch(AppError $e){http_response_code($e->status);setup_page($e->getMessage());}
            }
            if($method!=='GET')fail('Хүсэлтийн арга зөвшөөрөгдөөгүй.',405);setup_page();
        }
        if(!$installed)fail('Сайт хараахан суулгагдаагүй байна. /setup хуудсаар суулгана уу.',503);
        if(str_starts_with($path,'/api/'))api($db,$config,$path,$method);
        if($method!=='GET'){header('Allow: GET');fail('Хүсэлтийн арга зөвшөөрөгдөөгүй.',405);}
        if($path==='/'||$path==='/index.php')html_page('site.html',$config,$db);
        if($path==='/login'){
            $actor=current_actor($db);if($actor){header('Location: '.($actor['role']==='admin'?'/admin':'/reseller'),true,303);exit;}html_page('login.html',$config);
        }
        if($path==='/admin'||$path==='/reseller'){
            $actor=current_actor($db);if(!$actor){header('Location: /login',true,303);exit;}
            $target=$actor['role']==='admin'?'/admin':'/reseller';if($target!==$path){header('Location: '.$target,true,303);exit;}html_page('portal.html',$config);
        }
        fail('Хуудас олдсонгүй.',404);
    }catch(\Throwable $e){
        $status=$e instanceof AppError?$e->status:503;$message=$e instanceof AppError?$e->getMessage():'Үйлчилгээтэй холбогдож чадсангүй. Дахин оролдоно уу.';
        if(!($e instanceof AppError))error_log('LaEzer request failed: '.get_class($e));
        if($status===429)header('Retry-After: 900');
        if(str_starts_with($path,'/api/'))respond(['error'=>$message],$status);
        http_response_code($status);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
        echo '<!doctype html><html lang="mn"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LaEzer</title><body><main><h1>LaEzer</h1><p>'.escape($message).'</p><a href="/">Эхлэл рүү</a></main></body></html>';exit;
    }
}
