# Russian for WebBlocks CMS

The native language pack covers the CMS panel, public system interface, validation messages, CMS email templates, MCP messages and the CMS updater. User-authored content and third-party extension strings use their own translation catalogs.

## Установка русского языка

В CMS с поддержкой каталога откройте **Система → Языки панели управления → Открыть каталог Extensions**. Установите пакет **Вся CMS** для своей версии, затем выберите **Русский** в профиле или назначьте его языком панели по умолчанию.

Для установки файлов вручную:

1. Сверьте контрольную сумму SHA-256 и исходную версию CMS в файле `webblocks-language.json`. В установке CMS должна быть поддержка языковых пакетов хоста.
2. Объедините каталоги `lang/vendor/webblocks-cms/ru/` и `lang/vendor/publisher-client/ru/` из архива с соответствующими каталогами приложения Laravel. Сохраните локальные переопределения, если они есть. Если приложение хранит переводы вне `lang/`, используйте настроенный каталог.
3. Перезапустите постоянно работающие процессы приложения. Выберите **Русский** в профиле или установите его по умолчанию в разделе **Система → Языки панели управления**.
4. Для посетителей включите русский язык на нужном сайте и добавьте переводы страниц.

Обновляйте языковой пакет вместе с CMS. Для импорта через панель управления скачайте отдельный каталог `webblocks-cms-ru-panel-<version>.json` для вашей версии CMS.

## Install the native pack

On a supported CMS release, open **System → Panel languages → Browse Extensions** and install the **Full CMS** pack. Select **Русский** in your profile or as the panel default.

For manual host installation:

1. Verify the SHA-256 checksum and check the English source version in `webblocks-language.json`. The host CMS must support native language packs.
2. Merge the ZIP archive's `lang/vendor/webblocks-cms/ru/` and `lang/vendor/publisher-client/ru/` folders into the corresponding host Laravel language paths. Use the application's configured language directory if it differs from `lang/`. Preserve any installation-specific overrides.
3. Reload long-running application workers. Select **Русский** in your Webadmin profile or set it as the installation default under **System → Panel languages**.
4. To use the translated public system interface, enable Russian for the relevant site and provide Russian page content where required.

To upgrade, install the matching language pack with the CMS release. To remove it, select a different panel default and remove the two installed Russian directories, preserving any host overrides. A separately activated panel catalog for Russian can override native panel messages.

The separate `webblocks-cms-ru-panel-<version>.json` download is for the **System → Panel languages → Import** screen on the exact CMS version. Its scope is the panel import contract.

## Check and build

From the CMS source checkout with Composer dependencies installed, run `composer test:language-pack`. The check compares English source fingerprints, keys, placeholders and markup. Review the Russian wording whenever a source fingerprint changes. A version-only bump needs a rebuilt artifact and checksum.

Build into an existing output directory with `php scripts/check-language-pack.php --build OUTPUT_DIRECTORY`. This produces the native language ZIP and the panel import JSON with separate SHA-256 files.

The initial Russian drafts used the offline Argos Translate English–Russian model. The pack distributes the translations and contains no model files.
