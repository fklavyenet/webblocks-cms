<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

/** Versioned provider-owned localization; never creates CMS translations. */
final class ContentSourceContract
{
  public const VERSION = '1.0';

  public static function languages(): array
  {
    return AdminLocaleResolver::SUPPORTED_LOCALES;
  }

  public static function definition(array $locales, string $description, string $origin, string $recordKey): array
  {
    $locales = array_values(array_unique($locales));
    if (array_diff(self::languages(), $locales) !== [] || trim($description) === '' || trim($origin) === '' || ! preg_match('/^[a-z][a-z0-9_.]*$/', $recordKey)) {
      throw new InvalidArgumentException('Content source standard 1.0 requires all CMS languages, a description, an origin and a stable record key field.');
    }

    return ['version' => self::VERSION, 'locales' => $locales, 'description' => $description, 'origin' => $origin, 'record_key' => $recordKey, 'translations' => 'provider'];
  }

  /** Check each actual resolution, not only the provider's language declaration. */
  public static function record(ContentSourceDefinition $source, array $record, ContentSourceContext $context): array
  {
    $contract = $source->contract();
    if ($contract === null) {
      return $record;
    }
    if (($record['_locale'] ?? null) !== $context->locale || ! in_array($context->locale, $contract['locales'], true)) {
      throw new InvalidArgumentException('The content source did not return the requested CMS language.');
    }
    $key = data_get($record, $contract['record_key']);
    if (! is_string($key) || trim($key) === '') {
      throw new InvalidArgumentException('The content source record has no stable identity.');
    }
    foreach ($source->fieldDefinitions() as $field => $definition) {
      if (! Arr::has($record, $field)) {
        throw new InvalidArgumentException('The content source omitted a declared field.');
      }
      $value = data_get($record, $field);
      if (is_string($value) && $definition['type'] === 'url' && $value !== ''
        && ! (str_starts_with($value, '/') && ! str_starts_with($value, '//'))
        && ! str_starts_with($value, '#')
        && (! filter_var($value, FILTER_VALIDATE_URL) || ! in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true))) {
        throw new InvalidArgumentException('The content source returned an unsafe URL.');
      }
      if ($value !== null && ($definition['type'] === 'media' ? ! is_int($value) || $value < 1 : ! is_string($value))) {
        throw new InvalidArgumentException('The content source returned an incompatible field type.');
      }
    }

    return $record;
  }

  public static function records(ContentSourceDefinition $source, iterable $records, ContentSourceContext $context): array
  {
    if ($source->contract() === null) {
      return collect($records)->filter(fn ($record) => is_array($record))->values()->all();
    }
    $result = [];
    $keys = [];
    foreach ($records as $record) {
      if (! is_array($record)) {
        throw new InvalidArgumentException('The content source returned an invalid record.');
      }
      $record = self::record($source, $record, $context);
      if ($contract = $source->contract()) {
        $key = data_get($record, $contract['record_key']);
        if (isset($keys[$key])) {
          throw new InvalidArgumentException('The content source returned duplicate record identities.');
        }
        $keys[$key] = true;
      }
      $result[] = $record;
    }

    return $result;
  }
}
