<?php
declare(strict_types=1);
namespace Laezer;
const ORDER_STATUSES = ['new'=>'Шинэ','confirmed'=>'Баталгаажсан','shipped'=>'Хүргэлтэд','completed'=>'Дууссан','cancelled'=>'Цуцлагдсан'];
function product_view(array $row, string $audience = 'retail'): array {
    $p=['sku'=>$row['sku'],'name'=>$row['name'],'en'=>$row['en'],'step'=>$row['step'],'role'=>$row['role'],'desc'=>$row['description'],
        'tags'=>json_decode($row['tags'],true,32,JSON_THROW_ON_ERROR),'image'=>$row['image'],
        'price'=>$audience==='reseller'?($row['reseller_price']===null?null:(int)$row['reseller_price']):(int)$row['retail_price'],'bundle'=>(bool)$row['bundle']];
    if ($audience==='admin') $p += ['retailPrice'=>(int)$row['retail_price'],'resellerPrice'=>$row['reseller_price']===null?null:(int)$row['reseller_price'],'active'=>(bool)$row['active'],'sortOrder'=>(int)$row['sort_order'],'revision'=>(int)$row['revision']];
    return $p;
}
function catalog(\PDO $db, string $audience = 'retail', bool $lock = false): array {
    $rows=query($db,'SELECT * FROM products '.($audience==='admin'?'':'WHERE active=1 ').'ORDER BY sort_order,sku'.($lock?' FOR UPDATE':''))->fetchAll();
    return array_map(fn($r)=>product_view($r,$audience),$rows);
}
function banners(\PDO $db, bool $all = false): array {
    $rows=query($db,'SELECT * FROM hero_banners '.($all?'':'WHERE active=1 ').'ORDER BY sort_order,id')->fetchAll();
    foreach ($rows as &$r) foreach (['revision','active','sort_order'] as $key) $r[$key]=(int)$r[$key]; return $rows;
}
function image_path(mixed $value): string {
    $value=text($value,'Зургийн зам',1,500);
    if (!preg_match('~^/(assets|media)/[a-zA-Z0-9_./-]+$~D',$value) || str_contains($value,'..')) fail('Сайтад байршуулсан зураг сонгоно уу.'); return $value;
}
function save_product(\PDO $db, array $input): string {
    $sku=strtoupper(text($input['sku']??null,'Код',2,40)); if (!preg_match('/^[A-Z0-9-]+$/D',$sku)) fail('Бүтээгдэхүүний код буруу байна.');
    $tags=$input['tags']??[]; if (!is_array($tags) || !array_is_list($tags)) fail('Орцын мэдээлэл буруу байна.');
    $tags=array_map(fn($v)=>text($v,'Орц',1,60),array_slice($tags,0,8));
    $values=[text($input['name']??null,'Нэр',2,150),text($input['en']??'','Англи нэр',0,180),text($input['step']??'','Алхам',0,80),text($input['role']??'','Зориулалт',0,100),text($input['desc']??'','Тайлбар',0,2000),json_encode_safe($tags),image_path($input['image']??null),price($input['retailPrice']??null),price($input['resellerPrice']??null,true),($input['active']??true)===false?0:1,!empty($input['bundle'])?1:0,whole($input['sortOrder']??0,0,999,'Эрэмбэ'),now()];
    transaction($db,function() use($db,$input,$sku,$values) {
        $previous=one($db,'SELECT revision FROM products WHERE sku=? FOR UPDATE',[$sku]);
        if ($previous) {
            if (!is_int($input['revision']??null) || $input['revision']!==(int)$previous['revision']) fail('Бүтээгдэхүүн өөрчлөгдсөн байна. Шинэчлээд хадгална уу.',409);
            query($db,'UPDATE products SET name=?,en=?,step=?,role=?,description=?,tags=?,image=?,retail_price=?,reseller_price=?,active=?,bundle=?,sort_order=?,updated_at=?,revision=revision+1 WHERE sku=?',[...$values,$sku]);
        } else {
            if (!empty($input['revision'])) fail('Бүтээгдэхүүн олдсонгүй.',404);
            query($db,'INSERT INTO products (name,en,step,role,description,tags,image,retail_price,reseller_price,active,bundle,sort_order,updated_at,sku) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$values,$sku]);
        }
    }); return $sku;
}
function save_banner(\PDO $db, array $input): void {
    $id=text($input['id']??null,'Баннер',1,40);
    $href=text($input['href']??null,'Холбоос',1,80); if (!preg_match('/^#[a-zA-Z0-9-]+$/D',$href)) fail('Сайтын хэсгийн холбоос сонгоно уу.');
    $active=!empty($input['active'])?1:0;
    $values=[image_path($input['desktop_image']??null),image_path($input['mobile_image']??null),text($input['alt']??null,'Зургийн тайлбар',2,200),text($input['kicker']??'','Дээд мөр',0,100),text($input['title']??null,'Гарчиг',2,150),text($input['body']??'','Тайлбар',0,500),text($input['cta']??null,'Товчны нэр',1,60),$href,$active,whole($input['sort_order']??0,0,99,'Эрэмбэ'),now(),$id];
    transaction($db,function() use($db,$input,$id,$active,$values) {
        // All banner writes lock in the same order, preserving one active slide.
        $rows=query($db,'SELECT id,active,revision FROM hero_banners ORDER BY id FOR UPDATE')->fetchAll(); $previous=null;$count=0;
        foreach($rows as $r) { if($r['id']===$id)$previous=$r; $count+=(int)$r['active']; }
        if (!$previous) fail('Баннер олдсонгүй.',404);
        if (!is_int($input['revision']??null) || $input['revision']!==(int)$previous['revision']) fail('Баннер шинэчлэгдсэн байна. Дахин ачаална уу.',409);
        if (!$active && (int)$previous['active'] && $count<=1) fail('Дор хаяж нэг баннер идэвхтэй байна.');
        query($db,'UPDATE hero_banners SET desktop_image=?,mobile_image=?,alt=?,kicker=?,title=?,body=?,cta=?,href=?,active=?,sort_order=?,updated_at=?,revision=revision+1 WHERE id=?',$values);
    });
}
function save_account(\PDO $db, array $input): string {
    $email=strtolower(text($input['email']??null,'Имэйл',5,254)); if(!filter_var($email,FILTER_VALIDATE_EMAIL))fail('Холбоо барих имэйлээ зөв оруулна уу.');
    $name=text($input['name']??null,'Нэр',2,100);$phone=text($input['phone']??'','Утас',0,30);
    if($phone!==''&&!preg_match('/^[0-9]{8}$/D',$phone))fail('8 оронтой утас оруулна уу.');
    $login=username($input['username']??null); if($login==='admin')fail('Энэ нэвтрэх нэр админд зориулагдсан байна.');
    $password=$input['password']??'';if(!is_string($password))fail('Нууц үгийн хэлбэр буруу байна.');
    $newHash=$password!==''?password_hash_safe($password):null;
    try { return transaction($db,function() use($db,$input,$email,$name,$phone,$login,$password,$newHash) {
        $id=isset($input['id'])?text($input['id'],'Бүртгэл',1,40):null;
        $previous=$id?one($db,"SELECT * FROM accounts WHERE id=? AND role='reseller' FOR UPDATE",[$id]):one($db,"SELECT * FROM accounts WHERE email=? AND role='reseller' FOR UPDATE",[$email]);
        if($id&&!$previous)fail('Reseller олдсонгүй.',404);
        if(!$previous&&$newHash===null)fail('Шинэ нууц үг тохируулна уу.');
        $active=($input['active']??true)===false?0:1;
        if($previous) {
            $revoke=$password!==''||$previous['username']!==$login||(int)$previous['active']!==$active;
            query($db,"UPDATE accounts SET name=?,email=?,phone=?,username=?,password_hash=?,active=?,login_version=login_version+?,updated_at=? WHERE id=? AND role='reseller'",[$name,$email,$phone,$login,$newHash??$previous['password_hash'],$active,$revoke?1:0,now(),$previous['id']]); return $previous['id'];
        }
        $id=uid();query($db,"INSERT INTO accounts (id,email,name,phone,role,username,password_hash,active,created_at,updated_at) VALUES (?,?,?,?,'reseller',?,?,?,?,?)",[$id,$email,$name,$phone,$login,$newHash,$active,now(),now()]);return $id;
    }); } catch(\PDOException $e) { if((int)($e->errorInfo[1]??0)===1062)fail('Имэйл эсвэл нэвтрэх нэр давхардсан байна.',409);throw $e; }
}
function order_view(array $row): array {
    return ['id'=>$row['id'],'name'=>$row['customer_name'],'phone'=>$row['phone'],'address'=>$row['address'],'note'=>$row['note'],'payment'=>$row['payment'],'paymentStatus'=>$row['payment_status'],'paidAt'=>$row['paid_at'],'items'=>json_decode($row['items'],true,32,JSON_THROW_ON_ERROR),'subtotal'=>(int)$row['subtotal'],'delivery'=>(int)$row['delivery'],'total'=>(int)$row['total'],'status'=>$row['status'],'notificationStatus'=>$row['notification_status'],'createdAt'=>$row['created_at'],'channel'=>$row['channel'],'resellerName'=>$row['reseller_name']??null,'resellerEmail'=>$row['reseller_email']??null];
}
function list_orders(\PDO $db, array $actor, array $params): array {
    authorize($actor);$values=[];$clauses=[];
    if($actor['role']==='reseller'){$clauses[]="o.channel='reseller' AND o.reseller_id=?";$values[]=$actor['id'];}
    else{$channel=$params['channel']??'retail';if(!in_array($channel,['retail','reseller'],true))fail('Захиалгын төрөл буруу байна.');$clauses[]='o.channel=?';$values[]=$channel;}
    $status=$params['status']??'';if($status!==''){if(!is_string($status)||!isset(ORDER_STATUSES[$status]))fail('Төлөв буруу байна.');$clauses[]='o.status=?';$values[]=$status;}
    $search=$params['q']??'';if(!is_string($search))fail('Хайлт буруу байна.');$search=mb_substr($search,0,100);
    if($search!==''){$clauses[]='(o.id LIKE ? OR o.customer_name LIKE ? OR o.phone LIKE ? OR a.name LIKE ?)';array_push($values,...array_fill(0,4,'%'.$search.'%'));}
    $page=max(1,min(100000,(int)($params['page']??1)));$from='FROM orders o LEFT JOIN accounts a ON a.id=o.reseller_id WHERE '.implode(' AND ',$clauses);
    $count=one($db,'SELECT count(*) AS count,COALESCE(sum(o.total),0) AS total '.$from,$values);
    $rows=query($db,'SELECT o.*,a.name AS reseller_name,a.email AS reseller_email '.$from.' ORDER BY o.created_at DESC,o.id DESC LIMIT 20 OFFSET ?',[...$values,($page-1)*20])->fetchAll();
    return ['orders'=>array_map(__NAMESPACE__.'\\order_view',$rows),'count'=>(int)$count['count'],'total'=>(int)$count['total'],'page'=>$page,'pages'=>(int)ceil((int)$count['count']/20)];
}
function store_image(array $file, ?string $directory = null): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??''))fail('Зураг байршуулах боломжгүй байна.');
    if(($file['size']??0)<12 || $file['size']>8*1024*1024)fail('8MB хүртэл PNG, JPG, WebP зураг сонгоно уу.',413);
    $info=@getimagesize($file['tmp_name']);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extension=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
    if(!$info||!$extension||$info['mime']!==$mime||$info[0]>10000||$info[1]>10000||$info[0]*$info[1]>50000000)fail('PNG, JPG эсвэл WebP зураг шаардлагатай.');
    $directory??=LAEZER_PRIVATE_DIR.'/uploads';if(!is_dir($directory)&&!mkdir($directory,0700,true))fail('Зураг хадгалах үйлчилгээ бэлэн биш байна.',503);
    $key=uid().'.'.$extension;
    if(!move_uploaded_file($file['tmp_name'],$directory.'/'.$key))fail('Зураг хадгалж чадсангүй.',503);chmod($directory.'/'.$key,0600);return '/media/'.$key;
}
function render_hero(string $html, array $rows): string {
    if(!$rows)return $html;$slides='';$dots='';$count=count($rows);
    foreach($rows as $i=>$b){$head=explode("\n",$b['title']);$heading=escape(array_shift($head));foreach($head as $line)$heading.='<br><em>'.escape($line).'</em>';$tag=$i?'h2':'h1';
        $slides.='<article class="official-slide '.($i?'':'is-current').'" role="group" aria-roledescription="slide" aria-label="'.($i+1).' / '.$count.'" aria-hidden="'.($i?'true':'false').'" '.($i?'inert':'').'><picture><source media="(max-width: 760px)" srcset="'.escape($b['mobile_image']).'"><img src="'.escape($b['desktop_image']).'" alt="'.escape($b['alt']).'" width="1920" height="1080" '.($i?'loading="lazy"':'fetchpriority="high"').'></picture><div class="official-copy"><span class="eyebrow">'.escape($b['kicker']).'</span><'.$tag.'>'.$heading.'</'.$tag.'><p>'.escape($b['body']).'</p><a class="button dark" href="'.escape($b['href']).'" data-cursor="Discover">'.escape($b['cta']).' <span>↗</span></a></div></article>';
        $dots.='<button class="slide-dot '.($i?'':'is-current').'" aria-label="'.($i+1).'-р зураг үзэх" aria-pressed="'.($i?'false':'true').'" data-slide="'.$i.'"><span>'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'</span></button>';
    }
    return preg_replace_callback('~<div class="official-slides">[\s\S]*?<div class="slide-arrows">~',fn()=>'<div class="official-slides">'.$slides.'</div><div class="official-controls"><span class="official-count" aria-live="polite">01 <i>/ '.str_pad((string)$count,2,'0',STR_PAD_LEFT).'</i></span><div class="slide-dots" role="group" aria-label="Hero зураг сонгох">'.$dots.'</div><div class="slide-arrows">',$html,1)??$html;
}
