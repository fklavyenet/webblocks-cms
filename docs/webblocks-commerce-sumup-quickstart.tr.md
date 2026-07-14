# SumUp'ı WebBlocks Commerce'a Bağlama

Bu kılavuz, gerçek para çekmeden ilk SumUp sandbox ödemesini çalıştırmanızı sağlar. Ödeme formu
geliştirmeniz gerekmez; müşteriler kart bilgilerini SumUp'ın barındırdığı güvenli ödeme sayfasına
girer.

Dil: [English](webblocks-commerce-sumup-quickstart.md) ·
[Deutsch](webblocks-commerce-sumup-quickstart.de.md) · **Türkçe**

## Gerekenler

- kurulmuş ve etkinleştirilmiş WebBlocks Commerce eklentisi
- SumUp Dashboard erişimi
- Commerce Settings yönetme yetkisi veya güvenli ortam değişkeni override'larını yönetebilecek hosting yöneticisi
- mağaza için dışarıdan erişilebilen bir HTTPS adresi
- ilk sandbox kurulumu için yaklaşık on dakika

Sandbox ödemeleri simülasyondur; gerçek para hareketi oluşturmaz.

## Başlamadan önce

Ödeme bilgilerini korumalı **Commerce Settings** formundan kaydedebilirsiniz. Değerler şifrelenmiş
olarak saklanır, alanlar yalnızca yazma amaçlıdır ve kaydedilmiş gizli bilgiler tekrar gösterilmez.
API anahtarını CMS sayfasına, bloğa, ürüne veya herkese açık bir ayar alanına girmeyin.

Ortam değişkenleri isteğe bağlı hosting override'ları olarak desteklenmeye devam eder. API
anahtarını normal e-posta, sohbet, ekran görüntüsü veya destek talebiyle göndermeyin; korumalı
formu, hosting'in secret manager alanını ya da kurumunuzun onaylı güvenli kanalını kullanın.

## 1. Adım — SumUp sandbox satıcı hesabı oluşturun

1. [SumUp Dashboard](https://me.sumup.com/) üzerinden giriş yapın.
2. **Developer Settings** bölümünü açın.
3. **Sandboxes** sekmesini açın.
4. Yoksa bir sandbox satıcı hesabı oluşturun.
5. Hesap değiştiriciden sandbox satıcı hesabını seçin.

Seçili hesabın sandbox olduğu açıkça görünmelidir. İlk testte canlı satıcı hesabını kullanmayın.
Sandbox seçeneği yoksa SumUp'ın resmî
[online ödeme test kılavuzundaki](https://developer.sumup.com/online-payments/testing) geliştirici
hesabı bağlantısını kullanın.

## 2. Adım — Sandbox Merchant ID'yi kopyalayın

Sandbox hesabı seçiliyken Dashboard'un sol üstünde hesap adı ve **Merchant ID** görünür. WebBlocks
Commerce bu değeri merchant code olarak adlandırır. Genellikle `MXXXXXXX` benzeri bir değerdir.

Bu değeri 4. adım için kopyalayın. Canlı hesabınızın Merchant ID'sini kullanmayın.

## 3. Adım — Test API anahtarı oluşturun

Sandbox hesabı seçili kalsın ve şu adımları uygulayın:

1. Profilinizi genişletip **Settings** bölümünü açın.
2. **For Developers → Toolkit** yolunu izleyin.
3. **API Keys** bölümünü açın.
4. **Create** düğmesini seçip `WebBlocks Commerce sandbox` gibi anlaşılır bir ad verin.
5. SumUp gösterdiğinde gizli anahtarı kopyalayın veya indirin.

**SumUp Public Key** kullanmayın. WebBlocks Commerce gizli, sunucu tarafı API anahtarına ihtiyaç
duyar. Test anahtarı normalde `sk_test_` ile başlar. SumUp anahtarın tamamını daha sonra yeniden
göstermediğinden hemen güvenli bir secret manager içine kaydedin.

## 4. Adım — WebBlocks Commerce'ı yapılandırın

1. CMS yönetimine giriş yapın.
2. **Commerce → Commerce Settings** bölümünü açın.
3. Gateway olarak `SumUp`, mod olarak `Sandbox` seçin.
4. Gizli API anahtarını ve Merchant ID'yi girip kaydedin.

Bilgi alanları yalnızca yazma amaçlıdır. Alanı boş bırakmak kayıtlı değeri korur; değeri gerçekten
kaldırmak istediğinizde açık silme seçeneğini kullanın.

Hosting tarafından yönetilen kurulumlar bunun yerine şu isteğe bağlı override'ları tanımlayabilir:

Hosting'in ortam değişkenleri veya secrets alanına şunları ekleyin:

```env
WEBBLOCKS_COMMERCE_GATEWAY=sumup
WEBBLOCKS_COMMERCE_SUMUP_MODE=sandbox
WEBBLOCKS_COMMERCE_SUMUP_API_KEY=sk_test-anahtarinizi-buraya-yazin
WEBBLOCKS_COMMERCE_SUMUP_MERCHANT_CODE=sandbox-merchant-id-buraya-yazin
```

Ortam değerleri önceliklidir ve ilgili form alanlarını salt okunur yapar. Kurulum Laravel `.env`
dosyası kullanıyorsa değerleri bu dosyaya ekleyip yapılandırma önbelleğini temizleyin:

```bash
php artisan config:clear
```

Dağıtım süreciniz normalde config cache kullanıyorsa kendi standart dağıtım prosedürünüzle
önbelleği yeniden oluşturun. Hosting gerektiriyorsa uzun süre çalışan PHP worker'larını yeniden
başlatın.

`sandbox` modu yönetim ekranında hedef ortamı gösterir. SumUp tek API adresi kullandığı için API
anahtarıyla Merchant ID'nin gerçekten aynı sandbox hesabına ait olması gerekir.

## 5. Adım — Commerce hazırlık durumunu kontrol edin

1. CMS yönetimine giriş yapın.
2. **Commerce → Commerce Settings** bölümünü veya
   `/webadmin/plugins/webblocks-commerce/settings` adresini açın.
3. Şunları doğrulayın:
   - gateway: `sumup`
   - varsayılan para birimi: `EUR` (veya SumUp tarafından desteklenen başka bir para birimi)
   - SumUp modu: `sandbox`
   - API anahtarı: yapılandırıldı
   - merchant code: yapılandırıldı
   - checkout: hazır
   - eklenti şeması: hazır

Ayar ekranı güvenlik nedeniyle yalnızca “yapılandırıldı” veya “eksik” bilgisini gösterir; API
anahtarını kayıttan sonra da göstermez. Şema hazır değilse **System → Plugins → WebBlocks Commerce** üzerinden
eklenti kurulumunu veya migration işlemlerini çalıştırın.

## 6. Adım — Test ürünü oluşturun

1. **Commerce → Products** bölümünü açın.
2. Bir ürün oluşturun veya düzenleyin.
3. Başlık, slug, fiyat, para birimi ve vergi sınıfını belirleyin.
4. Sandbox hesabınız başka bir para birimi kullanmıyorsa seçim alanından ilk test için `EUR` seçin.
5. Ürün durumunu **Active** yapın.
6. Ürünü kaydedin.

Taslak veya arşivlenmiş ürünler satın alınamaz. Stok takibi açıksa en az bir ürün bulunmalıdır.

## 7. Adım — Native Commerce bloğunu ekleyin

1. İlgili CMS sayfasını sayfa oluşturucuda açın.
2. Normal bir slota **Commerce Buy Button** bloğu ekleyin.
3. Aktif ürünü seçin.
4. Düğme metni, hizalama ve fiyat gösterimini ayarlayın.
5. Önizleyip normal CMS yayın akışıyla yayınlayın.

Trusted HTML bloğu kullanmayın ve içerik içine SumUp checkout URL'si yapıştırmayın. Checkout URL'si
her sipariş için ayrı oluşturulur ve yaklaşık 30 dakika sonra geçersiz olur.

## 8. Adım — Başarılı sandbox ödemesi yapın

1. Herkese açık ürün sayfasını veya Commerce düğmesinin olduğu sayfayı açın.
2. Ürünü sepete ekleyin.
3. Adet, KDV, para birimi ve toplam tutarı kontrol edin.
4. **Güvenli ödemeye devam et** düğmesini seçin.
5. SumUp ödeme sayfasında bu resmî test kartını kullanın:

```text
Kart numarası: 4200 0000 0000 0091
Son kullanma: gelecekte bir tarih, örneğin 12/30
CVV: herhangi üç rakam, örneğin 123
Kart sahibi: herhangi bir ad
```

6. Ödemeyi tamamlayıp mağazaya geri dönün.
7. CMS yönetiminde **Commerce → Orders** bölümünü açın.
8. Siparişin `paid`, ödeme girişiminin `succeeded` durumuna geçtiğini doğrulayın.

Tarayıcının başarı sayfasına dönmesi ödeme kanıtı değildir. WebBlocks Commerce ancak SumUp
bildirimini aldıktan, checkout'u SumUp API'den yeniden okuduktan ve satıcı, referans, tutar, para
birimi, son durum ve başarılı işlemi eşleştirdikten sonra siparişi ödenmiş sayar.

## SumUp panelinde ayrıca webhook tanımlamayın

Bu entegrasyonda SumUp Dashboard'a manuel webhook URL'si girmeniz gerekmez. WebBlocks Commerce her
checkout'u oluştururken şu adresi otomatik `return_url` olarak gönderir:

```text
https://magazaniz.example/commerce/webhooks/sumup
```

Mağaza dışarıdan HTTPS üzerinden erişilebilir olmalıdır. Güvenlik duvarı, bakım sayfası, HTTP
parolası veya proxy kuralı SumUp'ın POST isteğini engellememelidir.

## İsteğe bağlı başarısız ödeme testi

SumUp sandbox belirli toplam tutarlarda reddedilen ödeme simülasyonu yapar. Bunun için nihai
checkout toplamı tam `11.00 EUR` olan geçici ve ayrı bir test ürünü oluşturun. Siparişin paid
olmadığını ve hata işlendiğinde ayrılan stoğun geri bırakıldığını kontrol edin. Gerçek ürünün
fiyatını bu test için değiştirmeyin.

## Canlı ödemeye geçiş

Yalnızca sandbox akışının tamamı başarılı olduktan sonra:

1. SumUp Dashboard'da gerçek satıcı hesabınızı seçin.
2. SumUp'ın istediği işletme doğrulaması ve ödeme hesabı adımlarını tamamlayın.
3. Canlı Merchant ID'yi kopyalayın.
4. Ayrı bir canlı API anahtarı oluşturun; normalde `sk_live_` ile başlar.
5. **Commerce Settings** içindeki sandbox değerlerini canlı bilgilerle değiştirin, modu `Live` yapıp kaydedin. Hosting override'ı kullanıyorsanız ortam değerlerini değiştirin.
6. Ortam override'ı kullanıyorsanız standart dağıtım prosedürünüzle uygulama yapılandırmasını yenileyin.
7. **Commerce Settings** ekranını yeniden kontrol edin.
8. Kabul edilebilir düşük tutarlı gerçek bir ödeme yapıp siparişi ve ödemeyi iki sistemde de
   doğrulayın.

Test anahtarıyla canlı Merchant ID'yi karıştırmayın ve sandbox anahtarını üretimde kullanmayın.

## Sorun giderme

- **API anahtarı eksik:** Anahtarı yalnızca yazma alanına yeniden girip kaydedin. Ortam override'ı görünüyorsa değişken adını ve config cache yenilemesini kontrol edin.
- **Hazır görünüyor ama checkout açılmıyor:** Anahtar ve Merchant ID aynı sandbox hesabına ait
  olmalı; Public Key kullanılmamalı.
- **Sipariş pending kalıyor:** `/commerce/webhooks/sumup` adresinin dışarıdan HTTPS ve POST ile
  erişilebilir olduğunu, güvenlik duvarı veya bakım modunun isteği engellemediğini kontrol edin.
- **Checkout süresi dolmuş:** Sepetten yeni checkout başlatın; eski URL'yi tekrar kullanmayın.

## Güvenlik kuralları

- API anahtarını CMS içeriğine, tarayıcı koduna, repoya, ekran görüntüsüne veya loglara yazmayın.
- Gerçek API anahtarını sohbete yapıştırmayın.
- Sandbox ve canlı bilgilerini kesin olarak ayırın.
- Açığa çıkmış olabilecek anahtarı hemen iptal edip yenisini oluşturun.
- Ürün teslimi için tarayıcı başarı ekranını değil CMS sipariş durumunu esas alın.

Mimari ve ileri seviye sorun giderme için
[WebBlocks Commerce Operator Guide](webblocks-commerce-operator-guide.md) belgesine bakın.

Resmî SumUp kaynakları:

- [Online ödeme testi](https://developer.sumup.com/online-payments/testing)
- [API anahtarı oluşturma ve koruma](https://developer.sumup.com/tools/authorization/api-keys)
- [Hosted Checkout](https://developer.sumup.com/online-payments/checkouts/hosted-checkout)
- [Checkout webhook'ları](https://developer.sumup.com/online-payments/webhooks)
