# LaEzer — laezer.shop / Hostinger PHP Git deployment

Энэ **hostinger-shared** branch нь Hostinger shared hosting-ийн **PHP/HTML сайт → Git deployment**-д зориулсан бэлэн document root. `index.php`, зураг, CSS, JS нь branch-ийн үндсэн хавтаст байрлана. Node.js, pnpm, Next.js, Vinext, build command болон output directory шаардахгүй.

## Зураг дээрх `.next` алдаа

`main` branch-ийн Cloudflare/Vinext эх кодыг Node.js Web App горимоор deploy хийсэн учраас Hostinger `.next` output хүлээсэн. `.next`-ийг `dist` болговол эхний build output алдаа арилж болох ч Cloudflare D1/R2 runtime нь Hostinger-ийн Node сервер дээр шууд ажиллахгүй.

Тиймээс энэ branch-ийг **PHP/HTML сайтын Git integration**-аар байрлуулна. Node.js Web App-ийн build settings цонхонд энэ branch-ийг deploy хийхгүй.

## Hostinger дээр сонгох утгууд

| Тохиргоо | Утга |
| --- | --- |
| Website type | Custom PHP/HTML |
| Domain | `laezer.shop` |
| Repository | `davaasurenbattogtokh-cmyk/laezer` |
| Branch | `hostinger-shared` |
| Root directory | Домэйны `public_html` |
| PHP | 8.2 буюу түүнээс шинэ |
| Build command / Output directory | Шаардахгүй |

1. Hostinger → тухайн **PHP/HTML сайтны Dashboard → Advanced → Git** хэсгээр repository-г холбоно. Дээрх branch-ийг сонгож deploy хийнэ. Node.js хувилбарын dashboard дээр байгаа бол эхлээд PHP/HTML сайтны deployment хэсэгт шилжинэ. Өмнөх файлууд/өгөгдөл байвал нөөцөлж авна; Git deployment target файлуудыг дарж шинэчилж болзошгүй.
2. Домэйн `laezer.shop`-ийг энэ сайтад холбоод SSL/Force HTTPS идэвхжүүлнэ. DNS-ийн утгыг Hostinger-ийн тухайн сайтдаа өгсөн заавраар тохируулна. Repository дотор домэйн бичигдсэнээр DNS автоматаар өөрчлөгдөхгүй.
3. MySQL database болон database user үүсгэнэ. PHP-ийн `pdo_mysql`, `mbstring`, `fileinfo` extension идэвхтэй байна. Имэйл ашиглавал `curl` хэрэгтэй. `upload_max_filesize=8M`, `post_max_size=10M` буюу түүнээс их байна.
4. `public_html`-ийн **гадна, зэрэгцээ** `laezer-private` хавтас үүсгэнэ. GitHub дахь `_deployment/config.php.example`-ийг тэнд `config.php` нэрээр хуулж, database нэр/хэрэглэгч/нууц үг болон `setup_key`-г бөглөнө. `setup_key` нь дор хаяж 32 тэмдэгт санамсаргүй утга байна; `REPLACE_...` загвар ажиллахгүй.

   ```text
   domains/laezer.shop/public_html/index.php
   domains/laezer.shop/public_html/.htaccess
   domains/laezer.shop/public_html/_app/...
   domains/laezer.shop/laezer-private/config.php
   domains/laezer.shop/laezer-private/uploads/...
   ```

5. `https://laezer.shop/setup` нээж суулгах түлхүүр, `admin` нэр болон админы нууц үгийг тохируулна. Суулгагч MySQL хүснэгтүүд, бүтээгдэхүүн, баннер болон админыг үүсгэнэ. Суулгалт дуусмагц `/setup` дахин ажиллахгүй. Админ/reseller хоёулаа `/login` цонхоор орно.
6. Админаас reseller үнэ тохируулж, reseller хэрэглэгч үүсгэнэ. Жижиглэн/reseller туршилтын захиалга, баннер, зураг upload, гарах үйлдлийг live орчинд шалгана.

## Домэйн ба нууц тохиргоо

- Үндсэн origin: **https://laezer.shop**. `_deployment/config.php.example` дотор тохируулсан.
- Public canonical холбоосыг origin-оос үүсгэнэ. Login болон өөрчлөлтийн POST хүсэлт origin-ийг шалгана. `www.laezer.shop` мөн Hostinger/SSL-д холбогдсон бол үндсэн домэйнд redirect хийнэ.
- Нууц тохиргоо болон админы байршуулсан зургууд Git document root-ийн гадна хадгалагдана. Deploy хийхэд config/upload дарж солигдохгүй.
- `_app`, `_deployment` нь зөвхөн immutable source/template/schema файлууд. Root `.htaccess` болон тухайн хавтасны `.htaccess` тэдгээрийн HTTP хандалтыг хаана. `.htaccess`-ийг устгахгүй; энэ branch нь Apache/LiteSpeed PHP hosting-д зориулагдсан.
- Нууц үг plaintext хадгалахгүй; MySQL-д bcrypt hash байна. Database болон нууц тохиргоо GitHub-ээс автоматаар үүсэхгүй/шилжихгүй.
- QPay идэвхгүй. `enabled` эсвэл credentials тавьснаар нээгдэхгүй. Имэйл мэдэгдэл анх идэвхгүй; өөрийн баталгаажуулсан хаягаар тусад нь тохируулж шалгана.

## Шинэчлэлт ба шалгалт

Hostinger auto-deployment-ийг **hostinger-shared** branch-тэй холбоно. Цаашдын PHP deployment өөрчлөлтийг энэ branch дээр хийнэ. `main` нь Cloudflare эх код болон анхны folder-based PHP багцыг хадгалсан архив/хөгжүүлэлтийн хувилбар хэвээр.

PHP/MySQL backend нь 18 integration test, 36 local HTTP assertion-оор шалгагдсан. Энэ deployment layout-ийн PHP entrypoint, private config болон image path-ийг мөн локал HTTP/MySQL-ийн 36 assertion-оор баталсан. Hostinger account дээрх бодит Git deployment, Apache/LiteSpeed rewrite, DNS, SSL-ийг одоогоор шалгаагүй.

Шинэчлэхээс өмнө MySQL, private config, uploads-аа нөөцөлнө. Аль хэдийн ашиглаж буй production database дээр setup/test script ажиллуулахгүй.

Hostinger-ийн албан ёсны PHP Git deployment заавар:
https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/
