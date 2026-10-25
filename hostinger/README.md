# LaEzer — Hostinger Shared Hosting

Энэ хавтас нь **laezer.shop** домэйнд зориулсан **PHP 8.2+ / MySQL 8 эсвэл MariaDB 10.6+** хувилбар. Node.js, Cloudflare Workers, D1, R2, Composer шаардахгүй. Өмнөх дизайн, албан ёсны зураг/лого, бүтээгдэхүүний хөдөлгөөн, монгол контент болон админ/reseller цонх хадгалагдсан.

## Суулгах — hPanel

1. `laezer.shop`-ийг Hostinger дээр **Custom PHP/HTML** сайт болгон нэмнэ. Домэйны DNS-ийг hPanel дээр тухайн сайтдаа заасан утгаар холбоно. SSL суулгаж **Force HTTPS** идэвхжүүлнэ.
2. PHP 8.2 буюу түүнээс шинэ хувилбар сонгоно. `pdo_mysql`, `mbstring`, `fileinfo` extension идэвхтэй байна. Имэйл мэдэгдэл ашиглах бол `curl` хэрэгтэй. `upload_max_filesize = 8M`, `post_max_size = 10M` буюу түүнээс их байна.
3. hPanel → **Databases → MySQL Databases** хэсэгт шинэ, хоосон database болон хэрэглэгч үүсгэнэ. Database нэр, хэрэглэгч нь Hostinger-ийн бүтэн prefix-тэй байна.
4. Энэ `hostinger` хавтасны **public_html доторх файлуудыг** домэйны `public_html` руу хуулна. `.htaccess` файлыг мөн хуулна. **laezer-private хавтсыг public_html-ийн гадна, зэрэгцүүлж** байрлуулна:

   ```text
   domains/laezer.shop/public_html/index.php
   domains/laezer.shop/public_html/.htaccess
   domains/laezer.shop/public_html/assets/...
   domains/laezer.shop/laezer-private/app/...
   domains/laezer.shop/laezer-private/config.php
   ```

   Repository-г бүхлээр нь `public_html` руу байрлуулахгүй. Root дахь `app`, `server`, `package.json` нь өмнөх Cloudflare хувилбарт хамаарна.
5. `laezer-private/config.php.example`-ийг `config.php` нэрээр хуулна. `origin` нь `https://laezer.shop`. Database-ийн host/name/user/password-ийг бөглөнө. `setup_key`-д password manager-аар үүсгэсэн **дор хаяж 32 тэмдэгт санамсаргүй түлхүүр** тавина. `REPLACE_...` загвар утгатай бол суулгагч ажиллахгүй. Config-ийг GitHub-д commit хийхгүй.
6. Браузераар `https://laezer.shop/setup` нээнэ. Суулгах түлхүүр, **admin** нэвтрэх нэр, админы ашиглах нууц үг болон давталтыг оруулна. Өмнө тохиролцсон нууц үгээ энд тохируулж болно. Суулгагч хүснэгтүүд, дөрвөн бүтээгдэхүүн, дөрвөн баннер болон админыг үүсгэнэ. Reseller үнэ анх хоосон; админаас бодитоор тохируулна.
7. Суулгалт дуусвал `/setup` автоматаар хаагдаж `/login` руу шилждэг болно. Админ нэвтрээд reseller үнэ, хүргэлт/дансны нийтлэгдсэн мэдээлэл болон баннеруудаа нягтална. Reseller хэрэглэгчийг админ өөрөө нэр/нууц үгээр үүсгэнэ.
8. Бодит ашиглалтаас өмнө туршилтын жижиглэн болон reseller захиалга өгч, админд тусдаа харагдаж байгааг, зураг байршуулах, төлөв шинэчлэх, гарах үйлдлийг шалгана. Туршилтын захиалгыг төлбөр төлөгдсөн гэж үзэхгүй.

Public хуудас ChatGPT нэвтрэлт шаардахгүй. Админ/reseller нь аппын `/login` цонхоор орно. `www.laezer.shop`-ийг бас хостинг/SSL-д нэмсэн бол үндсэн `laezer.shop` руу шилжүүлнэ. Домэйны бусад хаягийг зөвшөөрөхгүй.

## ZIP бэлдэх

Repository-г татсан компьютер дээр Python 3 суусан бол:

```sh
python3 hostinger/bin/package.py /absolute/path/laezer-hostinger.zip
```

ZIP нь шууд байрлуулах `public_html`, `laezer-private` болон энэ зааврыг агуулна. Нууц тохиргоо, хэрэглэгчийн зураг, database dump, node_modules орохгүй. Config-ийг сервер дээр тусад нь бөглөнө.

## Хадгалах өгөгдөл

- MySQL: бүтээгдэхүүн/хоёр төрлийн үнэ, баннер, админ/reseller, session digest, нэвтрэх хязгаарлалт, жижиглэн/reseller захиалга, төлбөрийн ирээдүйн бүтэц.
- `laezer-private/uploads`: админы байршуулсан PNG/JPEG/WebP. PHP ажиллуулах боломжгүй private байршлаас `/media/...` controller зөв MIME-ээр үйлчилнэ. Хавтас PHP хэрэглэгчид бичих эрхтэй байна; эхний upload дээр автоматаар үүснэ.
- Браузер: сагс. Checkout батлагдмагц захиалга MySQL-д хадгалагдана.
- Брендийн нийтлэл, review, холбоо барих, FAQ нь template дотор хэвээр.

Нууц үг нь тус бүр өөр salt-тай bcrypt hash; plaintext хадгалахгүй. Нууц үг 10–72 байт. Session 8 цаг хүчинтэй, Secure/HttpOnly/SameSite=Strict cookie, database-д token digest хадгална. Эрх/идэвхийг API хүсэлт бүрд шалгана. Reseller идэвхгүй болгох эсвэл нэр/нууц үг солиход өмнөх session хүчингүй болно. Reseller бусдын захиалга болон админ API-д хандахгүй.

## Имэйл ба QPay

Мэдэгдэл анх **идэвхгүй**. `notifications.email`-д захиалга хүлээн авах өөрийн баталгаажуулсан хаягийг тавьж, `enabled=true` болгосны дараа Formsubmit үйлчилгээний хаягийн идэвхжүүлэлт болон бодит хүргэлтийг тусад нь шалгана. Мэдэгдэл явж чадаагүй ч захиалга хадгалагдаж, админд харагдана.

QPay холболт **идэвхгүй хэвээр**. Credentials/flag тавихад идэвхжихгүй. MySQL invoice/payment хүснэгт, callback HMAC болон баталгаажсан төлбөрийн тусдаа төлөвийн суурь бий. Merchant API adapter, QR/deeplink UI, callback ownership болон sandbox end-to-end баталгаажуулалтыг дараа хэрэгжүүлнэ. Нийтийн QPay endpoint-ууд `503 QPAY_NOT_CONNECTED` буцаана.

## Шинэчлэх, нөөцлөх

- Шинэчлэхээс өмнө MySQL, `config.php`, `uploads`-аа нөөцөлнө. PHP source/public asset-аа шинэчилж болно; config болон uploads-аа дарж болохгүй.
- Одоогийн ChatGPT Sites-ийн database болон серверийн нууц тохиргоо GitHub/Hostinger руу автоматаар шилжихгүй. Энэ хувилбар шинэ database-тай эхэлнэ. Өмнөх бодит захиалга байвал тусад нь migration хийнэ; шууд SQLite dump-ийг MySQL-д импортлохгүй.
- Суулгагч аль хэдийн суусан database-ийг дахин seed хийхгүй. Цаашдын schema өөрчлөлтийг тусдаа migration-оор хийнэ.

## Шалгалт

PHP syntax болон жинхэнэ MySQL/MariaDB дээрх integration шалгалт:

Энэ хувилбарыг PHP 8.3 / MariaDB 10.11 дээр **18 integration test**, локал HTTP сервер дээр **36 assertion**-оор шалгасан. Бодит multipart upload болон original-byte media response орсон. Локал HTTP тест нь HTTPS transport-ийг fixture router-аар загварчилсан; Hostinger-ийн бодит SSL, DNS болон Apache/LiteSpeed rewrite тохиргоог сервер дээр шалгах шаардлагатай.

```sh
LAEZER_TEST_DSN='mysql:host=127.0.0.1;dbname=laezer_test;charset=utf8mb4' \
LAEZER_TEST_USER='test_user' LAEZER_TEST_PASSWORD='test_database_password' \
php hostinger/tests/run.php
```

Зөвхөн `_test` төгсгөлтэй **хоосон, disposable** database зөвшөөрнө. Production database дээр тест ажиллуулахгүй. Тест нь жинхэнэ захиалга/имэйл/QPay гүйлгээ илгээхгүй.

Hostinger account-д байршуулж, DNS/SSL болон тухайн серверийн PHP тохиргоог шалгасны дараа live ажиллагааг батална. Энэ source бэлэн болсон нь серверт автоматаар суусан гэсэн үг биш.
