<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Translations\LocalizedLog;

final class SiteTransferLog
{
  private const GROUP = 'admin-feedback.transfer_log';

  public static function entry(string $key, array $replace = []): string
  {
    // Keep the stored text stable for existing histories and CLI consumers.
    return __(self::translationKey($key), $replace, 'en');
  }

  public static function display(?string $log, string $locale): string
  {
    $translator = app(CmsTranslator::class);
    $templates = LocalizedLog::templates(self::GROUP) + LocalizedLog::templates('admin-feedback.panel_validation', [
      'import_entry_empty', 'import_locale_invalid', 'transfer_storage_unavailable',
    ]);

    return LocalizedLog::display(
      $log,
      $locale,
      $templates,
      static fn (string $phase): string => $translator->getOrDefault('admin.site_transfers.import_phases.'.$phase, $phase, $locale),
    ) ?? '';
  }

  private static function translationKey(string $key): string
  {
    return 'webblocks-cms::'.self::GROUP.'.'.$key;
  }
}
