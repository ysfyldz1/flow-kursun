# Flow kurulum notları

Bu dosya yeni bir kurum için Flow kurulurken izlenecek adımlardır ve Ayarlar → Kurulum rehberi'nde de görünür.
Temel özellikler Microsoft 365 olmadan çalışır; 2. ve 3. bölümler kurum hazır olduğunda yapılır. Kurulumda yapılan her şey için
kurumun **kendi** Microsoft 365 kiracısı kullanılır; Flow'u kuran tarafın hesabına bağımlılık yoktur.

Kurulum: dosyalar sunucuya alınır (cPanel Git Version Control ile depo site klasörüne klonlanır, `vendor/` bir kez ayrı yüklenir:
`php bin/release.php vendor`), cPanel'de boş veritabanı ve kullanıcı açılır, site adresinde sihirbaz açılır. Tek kullanımlık kod
`storage/install.key` dosyasındadır. Güncelleme: cPanel'de **Update from Remote**, ardından Ayarlar → Sistem → **Güncellemeleri uygula**.

## 1. Sunucu

- PHP 8.3+, MySQL/MariaDB (utf8mb4), PHP eklentileri: pdo_mysql, curl, zip, mbstring, gd, fileinfo, openssl.
- **HTTPS zorunlu.** Microsoft, `http` yönlendirme adresini yalnız `localhost`'ta kabul eder; PWA kurulumu da HTTPS ister.
  Geliştirmede Laragon'da SSL açılır, `config.php` içinde `app.url` = `https://<alan-adı>` yapılır.
- `vendor/` git'te yoktur (dompdf vb.): sunucuda `composer install` çalıştırılır ya da `vendor/` ayrıca yüklenir.
- `storage/` yazılabilir olmalı. `storage/app.key` her kurulumda **yeniden üretilir** (sırlar bununla şifrelenir);
  başka bir kurulumdan kopyalanmaz.
- Veritabanı: `php bin/migrate.php`, ilk yönetici: `php bin/create-admin.php`.
- **Cron zorunlu, SAATTE BİR** (cPanel → Cron Jobs, dakika `0`, saat `*`, gerisi `*`): `/usr/local/bin/php /home/KULLANICI/public_html/SITE/bin/cron.php >/dev/null 2>&1`. Tam satır bu sunucunun yoluyla Ayarlar → Sistem'de yazar, oradan kopyalayın. Hangi işin ne zaman çalışacağına Flow karar verir (hatırlatmalar 07:00 sonrası, transfer temizliği 03:00 sonrası, günde bir kez); yeni iş eklense de cron satırı değişmez. Cron 3 saattir çalışmadıysa Ayarlar → Sistem kırmızı uyarı gösterir.
  Süresi dolan dosya transferlerinin dosyalarını depodan siler. Çalıştığı Ayarlar → Microsoft 365 → Dosya Transferi deposu
  bölümündeki "Son otomatik temizlik" satırından görülür. Dosya Transferi deposu belgelerden ayrı seçilir; ek Azure izni gerekmez
  (`Sites.ReadWrite.All` + `Files.ReadWrite.All` yeterlidir).

## 2. Microsoft 365 (Entra) uygulama kaydı

Kurumun kendi Entra yönetim merkezinde, kurumun yönetici hesabıyla:

1. **Uygulama kayıtları → Yeni kayıt**
   - Ad: `Flow`
   - Desteklenen hesap türleri: **Yalnızca bu kuruluş dizinindeki hesaplar (tek kiracı)**
   - Yönlendirme URI'si (Web): `https://<alan-adı>/auth/microsoft/callback`
     (aynı adres hem Microsoft ile giriş hem de kişinin hesabını bağlaması için kullanılır; ikinci bir adres gerekmez)
2. **Sertifikalar ve gizli diziler → Yeni istemci gizli dizisi.** Değer yalnız bir kez gösterilir; hemen Flow'a girilir.
   Süresi dolmadan yenilenmesi için takvime not düşülür.
3. **API izinleri** (Microsoft Graph). Hepsinden sonra **"<Kurum> için yönetici onayı ver"**:

   | İzin | Tür | Ne için |
   |---|---|---|
   | `openid`, `profile`, `email`, `User.Read` | Temsilci | Microsoft ile giriş |
   | `offline_access` | Temsilci | Kişinin bağlantısı oturum kapansa da sürsün (yenileme belirteci) |
   | `Mail.Read` | Temsilci | Kişinin **kendi** e-postasını projeye kaydetmesi |
   | `Files.ReadWrite` | Temsilci | Kişinin **kendi** OneDrive'ından projeye dosya eklemesi |
   | `Files.Read.All` | Temsilci | OneDrive'da "Dosyalarım'a kısayol ekle" ile eklenen SharePoint klasörlerini okumak (yalnız kişinin zaten erişebildiği dosyalar) |
   | `Mail.Send` | Uygulama | Sistem e-postaları (ortak posta kutusundan) |
   | `Calendars.ReadWrite` | Uygulama | Duruşma/süre/toplantıları katılımcıların Outlook takvimine yazmak |
   | `Sites.ReadWrite.All`, `Files.ReadWrite.All` | Uygulama | Proje belgeleri (SharePoint sitesi ya da merkezi OneDrive) |

   **Neden e-posta okuma temsilci (delegated)?** Uygulama izniyle `Mail.Read` verilirse Flow kurumdaki **herkesin**
   posta kutusunu okuyabilir hale gelir. Temsilci izinde her kişi yalnız kendi posta kutusunu ve OneDrive'ını bağlar.
   Bu bilinçli bir karardır; uygulama izniyle `Mail.Read` **eklenmez**.

## 3. Flow ayarları (Ayarlar → Microsoft 365)

Sıra önemlidir:

1. Tenant ID, Client ID, Client Secret girilir → **Bağlantıyı test et**.
2. **Microsoft ile giriş** açılır. Kullanıcılar Flow'da önceden tanımlı olmalıdır (e-postayla eşleşir; otomatik hesap açılmaz).
3. **E-posta** (Ayarlar → E-posta): sürücü `graph`, gönderen ortak posta kutusu (ör. `it@kurum.av.tr`) → deneme e-postası.
4. **Belgeler**: SharePoint sitesi adresi ya da merkezi OneDrive kullanıcısı + kök klasör (ör. `Clients`).
5. **Outlook takvimi** anahtarı: yalnız `Calendars.ReadWrite` için yönetici onayı verildikten **sonra** açılır.
6. **"Microsoft ile girişte e-posta ve OneDrive bağlantısını da kur"** anahtarı: yalnız `Mail.Read`, `Files.ReadWrite`,
   `Files.Read.All` ve `offline_access` için yönetici onayı verildikten **sonra** açılır. Önce açılırsa Microsoft ile giriş yapan herkes
   izin ekranına takılır. Açıkken Microsoft ile giren kişinin hesabı kendiliğinden bağlanır; Flow şifresiyle girenler
   Profilim → "Microsoft 365 hesabımı bağla" ile bir kez bağlar.

Yeni bir kuruma geçerken eski bağlantıdan iz kalmaması için: **Ayarlar → Microsoft 365 → Bağlantıyı sıfırla**
(ayarlar, belirteçler, kullanıcı eşleşmeleri ve belge klasörü bağlantıları silinir).

## 4. Canlıya çıkmadan önce

- **`Mail.Send`'i ortak posta kutusuyla sınırla** (Exchange RBAC for Applications). Uygulama izni olarak `Mail.Send`
  tüm posta kutuları adına gönderebilir; yalnız gönderen kutuya daraltılmalıdır:
  1. Entra → Kurumsal uygulamalar → Flow → **Nesne kimliği**ni al.
  2. `Connect-ExchangeOnline`
  3. `New-ServicePrincipal -AppId <Client ID> -ObjectId <Nesne kimliği> -DisplayName Flow`
  4. `New-ManagementScope -Name "Flow gönderen" -RecipientRestrictionFilter "PrimarySmtpAddress -eq 'it@kurum.av.tr'"`
  5. `New-ManagementRoleAssignment -App <Client ID> -Role "Application Mail.Send" -CustomResourceScope "Flow gönderen"`
  6. `Test-ServicePrincipalAuthorization -Identity <Client ID> -Resource it@kurum.av.tr` (InScope: True olmalı)
  7. **En son** Entra'daki uygulama izinlerinden `Mail.Send`'i kaldır (RBAC ataması yerine geçer).
- İstemci gizli dizisinin bitiş tarihi not edilir.
- `config.php`: `app.debug` = `false`, `app.url` canlı adres.
- Telefonda PWA kurulumu HTTPS üzerinden denenir.

## Not: kullanıcı izinlerini değiştirirken

Flow'un kullanıcı adına istediği izinler (`Microsoft::CONNECT_SCOPES`) değiştirilecekse **önce** Azure'da izin eklenip
yönetici onayı verilir, **sonra** kod güncellenir. Ters sırada bağlı kullanıcıların belirteç yenilemesi başarısız olur ve
bağlantıları kopar. Güncellemeden sonra kullanıcılar Profilim → Yeniden bağla der (ya da Microsoft ile yeniden girer).
