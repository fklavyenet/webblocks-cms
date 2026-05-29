# Native Local Development

## Amaç ve kapsam

Bu dokuman WebBlocks CMS gelistirme ortamini macOS uzerinde Docker/DDEV disinda, native PHP, Nginx, MySQL veya MariaDB ve Redis ile calistirmaya hazirlamak icindir. Hedef, canli sunucuya daha yakin bir yerel ortam kurmak, fakat mevcut DDEV akislarini bozmadan asamali gecis yapmaktir.

Yerel native ortam icin standart:

- yerel domainler `.test` uzantili olmalidir
- yerel CMS ve site URL'leri HTTPS olmalidir
- HTTP yalnizca HTTPS'e yonlendirmek icin kullanilmalidir
- ana CMS ornegi `https://webblocks-cms.test` uzerinden calismalidir

Bu dokuman canli sunucuya baglanma, canli deploy veya canli dogrulama adimi icermez. Canli ortam bilgileri operator tarafindan guvenli sekilde alinip yerel envantere islenmelidir.

## Neden DDEV hemen kaldırılmıyor

DDEV bugun hala projenin desteklenen ve test edilen yerel gelistirme yoludur. Mevcut dokumantasyon, composer scriptleri, test stratejisi, database backup/restore yardimcilari ve bazi operasyonel notlar DDEV-first kalir.

DDEV'i hemen kaldirmamak su riskleri azaltir:

- mevcut gelistirici makinelerinde calisan kurulumlari bozmamak
- release ve test komutlarindaki DDEV-first beklentileri ayni anda degistirmemek
- native ortam tamamlanmadan ekip icin geri donus yolunu korumak
- canli sunucuya daha yakin native servis davranisini kontrollu sekilde karsilastirmak

Native local mode bu asamada DDEV'in yerine gecen zorunlu tek yol degil, yan yana belgelenen asamali gecis yoludur.

## Mevcut kurulu servisleri tespit etme

Once bilgisayarda nelerin zaten kurulu oldugunu bulun. Var olan servisleri silmeyin veya portlarini degistirmeyin.

```bash
sw_vers
uname -m
which brew
brew --version
brew services list
which php
php -v
php -m
which composer
composer --version
which nginx
nginx -v
which mysql
mysql --version
which mariadb
mariadb --version
which redis-server
redis-server --version
which mkcert
mkcert -version
```

Port kullanimini kontrol edin:

```bash
lsof -nP -iTCP:80 -sTCP:LISTEN
lsof -nP -iTCP:443 -sTCP:LISTEN
lsof -nP -iTCP:3306 -sTCP:LISTEN
lsof -nP -iTCP:6379 -sTCP:LISTEN
```

Mevcut `/etc/hosts` kayitlarini degistirmeden once inceleyin:

```bash
grep -n "webblocks\\|fklavye\\|\\.test" /etc/hosts
```

Bir servis zaten kuruluysa surumunu, config yolunu, servis adini ve kullandigi portu not edin. Eksik olanlari tamamlayin; calisan servisleri ezmeyin.

## Sunucudan alınması gereken sürüm/env envanteri

Canli sunucuya bu dokuman kapsaminda baglanmayin. Operator tarafindan guvenli sekilde alinacak envanter su bilgileri icermelidir:

- PHP surumu ve PHP-FPM pool kullanimi
- yuklu PHP extension listesi
- Nginx surumu ve Laravel server block davranisi
- MySQL veya MariaDB surumu
- Redis surumu ve kullanilan database indexleri
- Composer surumu
- Laravel `APP_ENV`, `APP_DEBUG`, `APP_URL`, `ASSET_URL` yaklasimi
- database charset ve collation
- session, cache ve queue driver degerleri
- mail driver ve localde kullanilacak guvenli esdegeri
- dosya izin modeli, deploy user ve web server user ayrimi
- public document root ve TLS terminasyon yapisi

Yerelde canli secret degerleri kullanmayin. Env isimlerini ve driver/secenek yapisini eslestirin, secretlari local-only degerlerle doldurun.

## macOS Homebrew ile eksik servisleri kurma

Homebrew yoksa once Homebrew'u resmi dokumandan kurun. Var olan brew kurulumunu yeniden kurmayin.

Eksik paketleri kurmak icin:

```bash
brew update
brew install php
brew install composer
brew install nginx
brew install mysql
brew install redis
brew install mkcert
brew install nss
```

MariaDB tercih ediliyorsa MySQL yerine:

```bash
brew install mariadb
```

Servisleri baslatmadan once port cakismalarini kontrol edin. Baslatma ornekleri:

```bash
brew services start php
brew services start nginx
brew services start mysql
brew services start redis
```

MariaDB kullaniyorsaniz:

```bash
brew services start mariadb
```

## PHP sürümü ve PHP extension kontrol listesi

Canli sunucu ile ayni major/minor PHP surumunu hedefleyin. WebBlocks CMS su an PHP 8.3 veya ustunu gerektirir.

```bash
php -v
php --ini
php -m
```

Kontrol edilmesi gereken extension listesi:

- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `filter`
- `hash`
- `intl`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_mysql`
- `redis` veya Predis/phpredis stratejisine gore proje karari
- `session`
- `tokenizer`
- `xml`
- `zip`

Eksik extension varsa once canli envanterle karsilastirin. Homebrew PHP paketleri bircok extension'i varsayilan getirir; PECL extension gerekiyorsa kurulumdan once mevcut PHP surumunun dogru oldugunu dogrulayin.

PHP-FPM durumunu kontrol edin:

```bash
brew services list | grep php
php-fpm -v
```

Apple Silicon Homebrew icin yaygin PHP-FPM socket veya port ayarlari `/opt/homebrew/etc/php/*/php-fpm.d/` altindadir. Intel Homebrew icin `/usr/local/etc/php/*/php-fpm.d/` olabilir. Mevcut config'i bozmadan once dosya yolunu dogrulayin.

## Composer kontrolü

Composer native PHP ile ayni binary'yi kullanmalidir.

```bash
which php
which composer
composer --version
composer diagnose
composer install
composer dump-autoload
```

DDEV disinda calisirken Composer scriptleri `@php artisan ...` kullandigi icin aktif shell'deki `php` binary'si onemlidir.

## MySQL/MariaDB database/user hazırlığı

Var olan MySQL veya MariaDB kurulumunu ve root erisim modelini bozmayin.

```bash
mysql --version
mysql -uroot -p -e "SELECT VERSION();"
```

Ornek local database ve kullanici:

```sql
CREATE DATABASE webblocks_cms_native
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'webblocks_native'@'localhost'
  IDENTIFIED BY 'change-this-local-password';

GRANT ALL PRIVILEGES ON webblocks_cms_native.*
  TO 'webblocks_native'@'localhost';

FLUSH PRIVILEGES;
```

MariaDB kullaniyorsaniz ayni SQL genellikle calisir. Socket ile TCP farkini not edin. Laravel `.env.native-local` icin basit ve tasinabilir hedef TCP'dir:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=webblocks_cms_native
DB_USERNAME=webblocks_native
DB_PASSWORD=change-this-local-password
```

Var olan Homebrew MySQL datadir'i (`/usr/local/var/mysql`) eski MySQL metadata'si veya baska projelerin tablolarini iceriyorsa MariaDB'yi ayni datadir ile baslatmayin ve datadir'i silmeyin. Guvenli yerel alternatif, CMS icin ayri bir datadir ve port kullanmaktir:

```bash
/usr/local/opt/mariadb/bin/mariadb-install-db \
  --user="$(whoami)" \
  --basedir=/usr/local/opt/mariadb \
  --datadir=/usr/local/var/mariadb-webblocks-cms \
  --tmpdir=/tmp

/usr/local/opt/mariadb/bin/mariadbd-safe \
  --datadir=/usr/local/var/mariadb-webblocks-cms \
  --socket=/tmp/webblocks-cms-mariadb.sock \
  --port=3307 \
  --pid-file=/usr/local/var/mariadb-webblocks-cms/webblocks-cms.pid
```

Bu durumda `.env` degerleri TCP uzerinden ayri instance'a bakmalidir:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=webblocks_cms_native
DB_USERNAME=webblocks_cms_native
```

## Redis kurulumu ve kontrolü

Redis zaten kuruluysa surum ve servis durumunu kontrol edin:

```bash
redis-server --version
redis-cli ping
brew services list | grep redis
```

Eksikse:

```bash
brew install redis
brew services start redis
redis-cli ping
```

Beklenen yanit `PONG` olmalidir. Laravel env degerleri:

```dotenv
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Eger `phpredis` extension yoksa once extension stratejisini netlestirin; native ortamda canli sunucuya yakin olan tercih edilmelidir.

## Nginx + PHP-FPM local site yapısı

Nginx document root Laravel `public` klasoru olmalidir. Ornek proje yolu:

```text
/Users/osm/Sites/projects/project-web_blocks/project-webblocks-cms/webblocks-cms
```

Onerilen local dosya yapisi:

```text
/opt/homebrew/etc/nginx/servers/webblocks-cms.test.conf
/opt/homebrew/etc/nginx/certs/webblocks-cms.test.pem
/opt/homebrew/etc/nginx/certs/webblocks-cms.test-key.pem
```

Intel Homebrew kullaniliyorsa temel yol `/usr/local/etc/nginx` olabilir. Once `brew --prefix` ile kontrol edin:

```bash
brew --prefix
nginx -T | head
```

Intel Homebrew ornegi:

```text
/usr/local/etc/nginx/servers/webblocks-cms.test.conf
/usr/local/etc/nginx/certs/webblocks-cms.test.pem
/usr/local/etc/nginx/certs/webblocks-cms.test-key.pem
```

PHP-FPM upstream icin kendi kurulumunuzdaki socket veya portu dogrulayin. Homebrew PHP cogu kurulumda `127.0.0.1:9000` veya bir Unix socket kullanabilir. Nginx ornegindeki `fastcgi_pass` satirini yerel PHP-FPM config'inize gore ayarlayin.

Homebrew PHP-FPM listen degerini varsaymayin; kurulumunuza gore su dosyalarda kontrol edin:

```bash
/usr/local/opt/php/sbin/php-fpm -tt
grep -n "^listen" /usr/local/etc/php/*/php-fpm.d/www.conf
```

## Yerelde HTTPS zorunluluğu

Native local mode icin HTTP hedef degildir. HTTP port 80 sadece HTTPS'e yonlendirme icin kullanilir. Laravel, CMS, admin, public site ve site domain URL'leri HTTPS olmalidir.

Kullanilacak ornek domainler:

- `https://webblocks-cms.test`
- `https://fklavye.test`
- `https://webblocksui.test`
- `https://ui.webblocksui.test`
- `https://cms.webblocksui.test`

`.local` kullanmayin. `.local` macOS mDNS/Bonjour davranislariyla cakisabilir. Bu proje icin native local domain standardi `.test`tir.

`APP_URL` ve CMS icindeki site/domain kayitlari HTTPS olmalidir. HTTP URL'leri karisik icerik, cookie secure flag, redirect ve canonical URL testlerini yaniltabilir.

## mkcert ile güvenilir local CA ve TLS sertifikaları

mkcert local makinede guvenilir bir local CA olusturur ve tarayicilarin kabul edecegi yerel TLS sertifikalari uretir.

Kurulum:

```bash
brew install mkcert
brew install nss
mkcert -install
```

`nss`, Firefox veya NSS store kullanan araclar icin gerekebilir. Daha once kuruluysa tekrar kurmayin; `brew list nss` ile kontrol edebilirsiniz.

Sertifika dizini:

```bash
sudo mkdir -p /opt/homebrew/etc/nginx/certs
sudo chown "$(whoami)":admin /opt/homebrew/etc/nginx/certs
```

Intel Homebrew icin `/usr/local/etc/nginx/certs` kullanilabilir.

`.test` domainleri icin sertifika uretimi:

```bash
cd /opt/homebrew/etc/nginx/certs
mkcert \
  -cert-file webblocks-cms.test.pem \
  -key-file webblocks-cms.test-key.pem \
  webblocks-cms.test \
  fklavye.test \
  webblocksui.test \
  ui.webblocksui.test \
  cms.webblocksui.test
```

Nginx TLS ornegi:

```nginx
ssl_certificate     /opt/homebrew/etc/nginx/certs/webblocks-cms.test.pem;
ssl_certificate_key /opt/homebrew/etc/nginx/certs/webblocks-cms.test-key.pem;
```

HTTP'den HTTPS'e yonlendirme:

```nginx
server {
  listen 80;
  listen [::]:80;
  server_name webblocks-cms.test fklavye.test webblocksui.test ui.webblocksui.test cms.webblocksui.test;
  return 301 https://$host$request_uri;
}
```

`.env.native-local` icinde:

```dotenv
APP_URL=https://webblocks-cms.test
```

CMS site domainleri de `https://...` olarak dusunulmelidir; CMS domain tablolarinda host saklansa bile operator-facing URL, test linkleri ve dokumantasyon HTTPS kullanmalidir.

## /etc/hosts ile .test domain yönetimi

Mevcut `/etc/hosts` dosyasini once okuyun:

```bash
grep -n "\\.test\\|webblocks\\|fklavye" /etc/hosts
```

Eksik kayitlari ekleyin. Var olan satirlari silmeyin; ayni domain farkli IP'ye gidiyorsa once not alin ve nedenini anlayin.

```bash
sudo sh -c 'cat >> /etc/hosts <<EOF
127.0.0.1 webblocks-cms.test
127.0.0.1 fklavye.test
127.0.0.1 webblocksui.test
127.0.0.1 ui.webblocksui.test
127.0.0.1 cms.webblocksui.test
EOF'
```

Kontrol:

```bash
dscacheutil -q host -a name webblocks-cms.test
ping -c 1 webblocks-cms.test
```

DNS cache sorunu yasarsaniz macOS DNS cache temizleme komutlarini kullanmadan once mevcut ag baglantilarini ve VPN/DNS profillerini kontrol edin.

## Örnek HTTPS Nginx server block

Bu ornek 80 portunu sadece HTTPS'e yonlendirir. Laravel/CMS sadece 443 SSL server block icinde calisir.

```nginx
server {
  listen 80;
  listen [::]:80;
  server_name webblocks-cms.test fklavye.test webblocksui.test ui.webblocksui.test cms.webblocksui.test;

  return 301 https://$host$request_uri;
}

server {
  listen 443 ssl;
  listen [::]:443 ssl;
  http2 on;
  server_name webblocks-cms.test fklavye.test webblocksui.test ui.webblocksui.test cms.webblocksui.test;

  root /Users/osm/Sites/projects/project-web_blocks/project-webblocks-cms/webblocks-cms/public;
  index index.php index.html;

  ssl_certificate     /opt/homebrew/etc/nginx/certs/webblocks-cms.test.pem;
  ssl_certificate_key /opt/homebrew/etc/nginx/certs/webblocks-cms.test-key.pem;

  charset utf-8;
  client_max_body_size 64m;

  add_header X-Frame-Options "SAMEORIGIN";
  add_header X-Content-Type-Options "nosniff";

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location = /favicon.ico {
    access_log off;
    log_not_found off;
  }

  location = /robots.txt {
    access_log off;
    log_not_found off;
  }

  error_page 404 /index.php;

  location ~ \.php$ {
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
    fastcgi_hide_header X-Powered-By;
  }

  location ~ /\.(?!well-known).* {
    deny all;
  }
}
```

PHP-FPM socket kullaniyorsaniz `fastcgi_pass` ornegi:

```nginx
fastcgi_pass unix:/opt/homebrew/var/run/php-fpm.sock;
```

Config testi ve reload:

```bash
nginx -t
brew services restart nginx
brew services restart php
```

DDEV router veya Docker 80/443 portlarini tutuyorsa Nginx baslamayabilir ya da istekler eski DDEV sertifikasina gidebilir. Native local HTTPS smoke testten once port sahibini kontrol edin:

```bash
lsof -nP -iTCP:80 -sTCP:LISTEN
lsof -nP -iTCP:443 -sTCP:LISTEN
```

Native local denemesi icin DDEV router'i gecici durdurmak gerekiyorsa DDEV dosyalarini silmeden `ddev poweroff` kullanin; DDEV'e geri donmek icin `ddev start` yeterlidir.

## Örnek .env.native-local değerleri

`.env.native-local` ornek dosya olarak tutulabilir; aktif kullanmak icin `.env` olarak kopyalayin ve local secretlari kendiniz doldurun.

```dotenv
APP_NAME="WebBlocks CMS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=https://webblocks-cms.test

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=webblocks_cms_native
DB_USERNAME=webblocks_native
DB_PASSWORD=change-this-local-password

SESSION_DRIVER=database
CACHE_STORE=redis
QUEUE_CONNECTION=database

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS=webblocks-cms@test.invalid
MAIL_FROM_NAME="${APP_NAME}"

FILESYSTEM_DISK=local
WEBBLOCKS_UPDATES_ENABLED=false
CMS_BACKUP_EXECUTION=direct
```

Kurulum sirasinda:

```bash
cp .env.native-local .env
php artisan key:generate
php artisan config:clear
```

`.env.native-local` icindeki `APP_URL` kesinlikle HTTPS ve `.test` olmalidir:

```dotenv
APP_URL=https://webblocks-cms.test
```

## DDEV komutlarının native karşılıkları

| DDEV komutu | Native karsilik |
| --- | --- |
| `ddev composer install` | `composer install` |
| `ddev composer dump-autoload` | `composer dump-autoload` |
| `ddev composer format:test` | `composer format:test` |
| `ddev artisan key:generate` | `php artisan key:generate` |
| `ddev artisan migrate` | `php artisan migrate` |
| `ddev artisan db:seed` | `php artisan db:seed` |
| `ddev artisan storage:link` | `php artisan storage:link` |
| `ddev artisan test --filter=ExampleTest` | `php artisan test --filter=ExampleTest` |
| `ddev artisan cache:clear` | `php artisan cache:clear` |
| `ddev artisan config:clear` | `php artisan config:clear` |
| `ddev artisan webblocks:package-status` | `php artisan webblocks:package-status` |
| native local ortam kontrolu | `php artisan webblocks:doctor-native-local` |
| `ddev describe` | `nginx -T`, `brew services list`, `php -v`, `mysql --version`, `redis-cli ping` |
| `ddev logs` | Homebrew service logs under `/opt/homebrew/var/log` or `/usr/local/var/log` |

Database import/export icin native karsiliklar:

```bash
mysqldump -h127.0.0.1 -uwebblocks_native -p webblocks_cms_native > backup.sql
mysql -h127.0.0.1 -uwebblocks_native -p webblocks_cms_native < backup.sql
```

MariaDB kullaniliyorsa `mariadb-dump` ve `mariadb` komutlarini tercih edin.

## Native local doctor komutu

Native HTTPS `.test` hedefi icin okuma-only kontrol komutu:

```bash
php artisan webblocks:doctor-native-local
```

Bu komut sistemde kurulum yapmaz, `brew install` calistirmaz, servis baslatmaz, dosya yazmaz ve `/etc/hosts` degistirmez. Yalnizca mevcut PHP/Laravel config, binary erisimi, database/Redis erisimi, HTTPS `.test` URL standardi, hosts kaydi, mkcert sertifika dosyasi beklentisi ve yazilabilir runtime dizinlerini raporlar.

Output secret-safe olmalidir. DB password, `APP_KEY`, mail password, token veya secret degerleri yazdirilmaz.

Sonuctaki durumlar:

- `PASS`: native local hedefi icin kontrol basarili
- `WARN`: gecis icin dikkat edilmesi gereken ama tek basina komutu basarisiz saymayan durum
- `FAIL`: native local hedefini engelleyen durum

Komut fail-fast yapmaz; tum kontrolleri calistirir ve sonunda su ozetle biter:

```text
Summary
Passed: 18
Warnings: 0
Failed: 0
```

Kritik fail varsa komut non-zero exit code ile biter. Ornek kritik fail durumlari:

- zorunlu PHP extension eksik
- `APP_URL` `https://` ile baslamiyor
- `APP_URL` hostu `.test` ile bitmiyor
- database veya Redis erisilemiyor
- native HTTPS hedefi icin Nginx veya mkcert binary bulunamiyor
- dokumanda onerilen sertifika/key dosyalari bulunamiyor
- `storage` veya `bootstrap/cache` yazilabilir degil

Composer kisa komutu:

```bash
composer native:doctor
```

## Daily native local workflow

Gunluk native local kullanimda DDEV dosyalarini silmeyin ve DDEV akislarini degistirmeyin. Native Nginx 80/443 portlarini kullanacagi icin DDEV router calisiyorsa once port sahipligini kontrol edin ve gerekiyorsa DDEV'i gecici durdurun:

```bash
lsof -nP -iTCP:80 -iTCP:443 -sTCP:LISTEN
ddev poweroff
```

Native Nginx durumunu ve config'i kontrol edin:

```bash
nginx -t
lsof -nP -iTCP:80 -iTCP:443 -sTCP:LISTEN
```

Nginx Homebrew servisi olarak yonetiliyorsa yeniden yuklemek icin:

```bash
brew services restart nginx
```

PHP-FPM durumunu kontrol edin. Homebrew PHP-FPM socket veya portunu varsaymayin; mevcut config'ten okuyun:

```bash
php-fpm -v
ps -axo pid,ppid,comm,args | rg -i '[p]hp-fpm'
```

PHP-FPM Homebrew servisi olarak yonetiliyorsa yeniden baslatmak icin:

```bash
brew services restart php
```

Redis durumunu kontrol edin:

```bash
redis-cli ping
lsof -nP -iTCP:6379 -sTCP:LISTEN
```

MariaDB 3307 instance'ini mevcut port, datadir ve socket degerlerini bozmadan tespit edin:

```bash
lsof -nP -iTCP:3307 -sTCP:LISTEN
ps -axo pid,ppid,comm,args | rg -i '[m]ariadbd|[m]ysqld'
```

Bu proje icin dogrulanmis native MariaDB 3307 duzeni sudur:

```text
datadir: /usr/local/var/mariadb-webblocks-cms
socket: /tmp/webblocks-cms-mariadb.sock
port: 3307
pid file: /usr/local/var/mariadb-webblocks-cms/webblocks-cms.pid
```

Bu ayri datadir kullanan instance `mariadbd-safe` ile baslatilabilir. Once portun bos oldugunu ve datadir'in var oldugunu dogrulayin; var olan Homebrew MySQL/MariaDB datadir'lerini silmeyin veya yeniden kullanmayin:

```bash
/usr/local/opt/mariadb/bin/mariadbd-safe --datadir=/usr/local/var/mariadb-webblocks-cms --socket=/tmp/webblocks-cms-mariadb.sock --port=3307 --pid-file=/usr/local/var/mariadb-webblocks-cms/webblocks-cms.pid
```

Doctor ve smoke kontrollerini calistirin:

```bash
composer native:doctor
composer native:smoke
```

`composer native:smoke` su secret-safe kontrolleri kapsar:

- doctor sonucu 0 FAIL
- `APP_URL` HTTPS `.test` hedefi
- configured database connection reachable
- configured Redis connection reachable
- Nginx binary mevcut
- APP_URL icin HTTPS curl sonucu `200` veya `302`

Backup restore sonrasinda en az su kontrolleri calistirin:

```bash
composer dump-autoload
composer native:doctor
composer native:smoke
```

DDEV'e geri donerken native dosyalari silmeyin. DDEV icin:

```bash
ddev start
```

## CMS local smoke checklist

Native local kurulumdan sonra:

```bash
composer install
composer dump-autoload
php artisan config:clear
php artisan webblocks:doctor-native-local
php artisan webblocks:smoke-native-local
php artisan route:list --path=webadmin
php artisan test --filter=AdminDashboardRouteTest --stop-on-failure
```

Tarayici kontrolleri:

- `https://webblocks-cms.test` guvenilir sertifika ile aciliyor
- `http://webblocks-cms.test` otomatik `https://webblocks-cms.test` adresine yonleniyor
- `https://webblocks-cms.test/webadmin/login` paket auth ekrani veya host auth modeline uygun giris akisina gidiyor
- `/cms/css`, `/cms/js`, ve `/cms/brand` statik asset URL'leri HTTPS altinda calisiyor
- `https://fklavye.test`, `https://webblocksui.test`, `https://ui.webblocksui.test`, ve `https://cms.webblocksui.test` hostlari Nginx'e ulasiyor
- CMS Sites/Domains ekraninda yerel site hostlari `.test` standardina gore kaydediliyor
- mixed-content uyarisi yok
- Redis gerekiyorsa `redis-cli ping` `PONG` donuyor
- database-backed session/cache/queue tablolarinin migration durumu temiz

## Installer 504 troubleshooting

Fresh native installs can hit `504 Gateway Time-out` on `POST /install/core` if the first migration/seed run takes longer than Nginx's FastCGI response timeout. Diagnose before changing timeout values:

```bash
tail -n 80 storage/logs/laravel.log
tail -n 80 /usr/local/var/log/nginx/error.log
tail -n 80 /usr/local/var/log/php-fpm.log
php artisan migrate:status --no-interaction
```

If Nginx reports `upstream timed out ... request: "POST /install/core"` and migrations are marked `Ran`, the browser request likely timed out while the migration step kept running to completion. Re-open `https://webblocks-cms.test/install/core` first. If the core step still is not complete, run the installer core step through CLI once to finish the same non-destructive installer workflow without the browser timeout:

```bash
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach ($app->make(App\Support\Install\Installer::class)->installCore() as $step) { printf("%s: %s - %s\n", $step["label"], $step["status"], $step["message"]); }'
```

After it reports `Core seed: pass`, continue in the browser at `https://webblocks-cms.test/install/admin` to create the first super admin. Do not use destructive database reset commands to recover from this state.

## Native backup restore

Backup restore supports both DDEV and native local modes. In `CMS_BACKUP_EXECUTION=auto`, native `.test` URLs such as `https://webblocks-cms.test` use direct MySQL/MariaDB CLI execution instead of `ddev exec`, even when the repository still contains `.ddev` files. DDEV remains supported for `.ddev.site` URLs or when `CMS_BACKUP_EXECUTION=ddev` is explicitly configured.

For native MariaDB on a non-default port, restore reads the active Laravel database config from `.env`:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=webblocks_cms_native
DB_USERNAME=webblocks_cms_native
CMS_BACKUP_EXECUTION=auto
```

The native restore path requires a local `mysql` or `mariadb` client binary on `PATH`. The password, when configured, is written to a temporary MySQL defaults file for the restore process and must not be printed in logs or UI errors. If native restore fails, the error should show secret-safe connection context only: database, username, host, and port.

Secret-safe checks before retrying a native restore:

```bash
php artisan webblocks:doctor-native-local
php -r '$env=parse_ini_file(".env", false, INI_SCANNER_RAW); foreach (["DB_CONNECTION","DB_HOST","DB_PORT","DB_DATABASE","DB_USERNAME","CMS_BACKUP_EXECUTION"] as $k) { printf("%s=%s\n", $k, $env[$k] ?? "<missing>"); }'
mysql --version
```

Do not start DDEV just to restore a backup into the native `.test` environment. If a restore error says `No running container found for service 'web'`, clear Laravel config/cache and confirm `APP_URL` is the native `.test` URL, not a `.ddev.site` URL.

## Yerel site domainleri için önerilen yapı

Ana CMS maintenance hostu:

- `https://webblocks-cms.test`

Yerel public site hostlari:

- `https://fklavye.test`
- `https://webblocksui.test`
- `https://ui.webblocksui.test`
- `https://cms.webblocksui.test`

CMS icinde site domainleri host olarak saklaniyorsa host kisimlarini `.test` tutun. Operator-facing dokumanlarda ve test linklerinde tam URL'yi HTTPS olarak yazin.

Onerilen yaklasim:

- tek Nginx server block ayni Laravel public root'a birden fazla `.test` hostu yonlendirebilir
- CMS multisite domain esleme gelen hosta gore dogru siteyi secer
- local fallback davranisi gerekiyorsa sadece local env'de acik tutun
- `.local`, `.localhost`, canli domain veya staging domainlerini native local test standardi olarak kullanmayin

## Geri dönüş planı

Native local kurulum sorun cikarirsa DDEV akisi korunur.

Geri donmek icin:

```bash
ddev start
ddev composer install
ddev artisan config:clear
```

Tarayicida DDEV URL'lerini kullanmaya devam edin. Native servisleri durdurmak isterseniz once baska projelerin kullanmadigindan emin olun:

```bash
brew services list
brew services stop nginx
brew services stop php
brew services stop redis
```

MySQL veya MariaDB servislerini durdurmadan once baska local projelerin kullanip kullanmadigini kontrol edin. `/etc/hosts` icindeki `.test` kayitlarini silmeden once hangi projeler tarafindan kullanildigini not edin.

Bu geri donus plani DDEV dosyalarini silmez, `.ddev` yapisini degistirmez ve mevcut DDEV tabanli test/release komutlarini bozmaz.
