# Russian for WebBlocks CMS

The native language pack covers the CMS panel, public system interface, validation messages, CMS email templates, MCP messages and the CMS updater. User-authored content and third-party extension strings use their own translation catalogs.

## Установка русского языка

Откройте **Extensions → Языковые пакеты** и загрузите полный ZIP-пакет для
установленной версии CMS или выберите пакет через **Обзор языковых пакетов**.
После установки выберите **Русский** в профиле или назначьте его языком панели
по умолчанию.

Для посетителей включите русский язык на нужном сайте и добавьте переводы
страниц. При обновлении CMS устанавливайте пакет для новой версии.

## Install the language pack

Open **Extensions → Language packs** and upload the complete ZIP under
**Manual language pack install**, or install the matching release from
**Browse language packs**. Select **Русский** in your profile or as the panel
default.

The pack translates the public system interface when Russian is enabled for
the site. Create Russian page content where required. Install the matching
language pack after upgrading the CMS.

## Check and build

From the CMS source checkout with Composer dependencies installed, run `composer test:language-pack`. The check compares English source fingerprints, keys, placeholders and markup. Review the Russian wording whenever a source fingerprint changes. A version-only bump needs a rebuilt artifact and checksum.

Build into an existing output directory with `php scripts/check-language-pack.php --build OUTPUT_DIRECTORY`. This produces the Full CMS language pack ZIP and its SHA-256 file.

The initial Russian drafts used the offline Argos Translate English–Russian model. The pack distributes the translations and contains no model files.
