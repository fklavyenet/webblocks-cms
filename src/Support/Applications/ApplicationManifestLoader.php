<?php

namespace WebBlocks\Cms\Support\Applications;

use JsonException;
use SplFileInfo;

class ApplicationManifestLoader
{
  private const HANDLE_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/';

  private const SETTING_TYPES = ['boolean', 'enum', 'integer', 'string'];

  public function load(SplFileInfo $manifest, string $rootPath, string $rootUrl): ApplicationDefinition
  {
    $issues = [];
    $contents = file_get_contents($manifest->getPathname());

    try {
      $payload = json_decode((string) $contents, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      $payload = [];
      $issues[] = $this->issue('application_manifest_invalid_json', 'Manifest must contain valid JSON.');
    }

    if (! is_array($payload)) {
      $payload = [];
      $issues[] = $this->issue('application_manifest_invalid_shape', 'Manifest root must be a JSON object.');
    }

    $handle = trim((string) ($payload['handle'] ?? ''));
    if (preg_match(self::HANDLE_PATTERN, $handle) !== 1) {
      $issues[] = $this->issue('application_handle_invalid', 'Handle must use lowercase letters, numbers, and single hyphens.');
      $handle = $handle !== '' ? $handle : 'invalid-'.substr(hash('sha256', $manifest->getPathname()), 0, 12);
    }

    $schemaVersion = (int) ($payload['schema_version'] ?? 0);
    if ($schemaVersion !== 1) {
      $issues[] = $this->issue('application_schema_incompatible', 'Only application manifest schema version 1 is supported.');
    }

    $renderMode = trim((string) ($payload['render_mode'] ?? ''));
    if (! in_array($renderMode, ['inline', 'iframe'], true)) {
      $issues[] = $this->issue('application_render_mode_invalid', 'Render mode must be inline or iframe.');
      $renderMode = 'inline';
    }

    $manifestDirectory = realpath($manifest->getPath()) ?: $manifest->getPath();
    $normalizedRoot = realpath($rootPath) ?: rtrim($rootPath, DIRECTORY_SEPARATOR);
    $baseUrl = $this->manifestBaseUrl($manifestDirectory, $normalizedRoot, $rootUrl);
    $assets = $this->normalizeAssets($payload['assets'] ?? [], $manifestDirectory, $baseUrl, $issues);
    $entry = $this->normalizeEntry($payload['entry'] ?? null, $renderMode, $manifestDirectory, $baseUrl, $issues);
    $mount = $this->normalizeMount($payload['mount'] ?? [], $renderMode, $issues);
    $settingsSchema = $this->normalizeSettingsSchema($payload['settings_schema'] ?? [], $issues);

    return new ApplicationDefinition(
      handle: $handle,
      name: trim((string) ($payload['name'] ?? '')) ?: str($handle)->headline()->toString(),
      description: trim((string) ($payload['description'] ?? '')) ?: null,
      version: trim((string) ($payload['version'] ?? '')) ?: '0.0.0',
      schemaVersion: $schemaVersion,
      renderMode: $renderMode,
      mount: $mount,
      assets: $assets,
      supports: $this->normalizeSupports($payload['supports'] ?? []),
      settingsSchema: $settingsSchema,
      entry: $entry,
      provider: 'manifest:'.$handle,
      checksum: hash('sha256', (string) $contents),
      issues: $issues,
    );
  }

  private function normalizeAssets(mixed $rawAssets, string $directory, string $baseUrl, array &$issues): array
  {
    $rawAssets = is_array($rawAssets) ? $rawAssets : [];
    $normalized = ['css' => [], 'js' => []];

    foreach (['css', 'js'] as $type) {
      $declarations = $rawAssets[$type] ?? [];

      if (! is_array($declarations)) {
        $issues[] = $this->issue('application_assets_invalid', strtoupper($type).' assets must be an array.');

        continue;
      }

      foreach (array_values($declarations) as $index => $declaration) {
        $declaration = is_string($declaration) ? ['path' => $declaration] : $declaration;

        if (! is_array($declaration)) {
          $issues[] = $this->issue('application_asset_path_invalid', "Asset {$type}.{$index} must declare a local path.");

          continue;
        }

        $path = $this->localFile($declaration['path'] ?? null, $directory, $type, $issues);

        if ($path === null) {
          continue;
        }

        $asset = ['path' => $baseUrl.'/'.$path];

        if ($type === 'js') {
          $scriptType = trim((string) ($declaration['type'] ?? 'classic'));
          $loadPosition = trim((string) ($declaration['load_position'] ?? 'body_end'));
          $asset['type'] = in_array($scriptType, ['classic', 'module'], true) ? $scriptType : 'classic';
          $asset['load_position'] = in_array($loadPosition, ['head', 'body_end'], true) ? $loadPosition : 'body_end';
        }

        $normalized[$type][] = $asset;
      }
    }

    return $normalized;
  }

  private function normalizeEntry(mixed $rawEntry, string $renderMode, string $directory, string $baseUrl, array &$issues): ?string
  {
    if ($renderMode !== 'iframe') {
      return null;
    }

    $entry = $this->localFile($rawEntry, $directory, 'html', $issues);

    if ($entry === null) {
      $issues[] = $this->issue('application_entry_missing', 'Iframe applications require a local HTML entry file.');

      return null;
    }

    return $baseUrl.'/'.$entry;
  }

  private function normalizeMount(mixed $rawMount, string $renderMode, array &$issues): array
  {
    if ($renderMode !== 'inline') {
      return [];
    }

    $rawMount = is_array($rawMount) ? $rawMount : [];
    $element = trim((string) ($rawMount['element'] ?? 'div'));
    $class = trim((string) ($rawMount['class'] ?? ''));

    if (! in_array($element, ['div', 'section', 'canvas'], true)) {
      $issues[] = $this->issue('application_mount_invalid', 'Inline mount element must be div, section, or canvas.');
      $element = 'div';
    }

    if ($class !== '' && preg_match('/^[A-Za-z0-9_-]+(?:\s+[A-Za-z0-9_-]+)*$/', $class) !== 1) {
      $issues[] = $this->issue('application_mount_invalid', 'Inline mount classes contain unsupported characters.');
      $class = '';
    }

    return array_filter(['element' => $element, 'class' => $class], fn ($value) => $value !== '');
  }

  private function normalizeSettingsSchema(mixed $rawSchema, array &$issues): array
  {
    if (! is_array($rawSchema)) {
      $issues[] = $this->issue('application_settings_schema_invalid', 'Settings schema must be an object.');

      return [];
    }

    $schema = [];

    foreach ($rawSchema as $key => $definition) {
      if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1 || ! is_array($definition)) {
        $issues[] = $this->issue('application_settings_schema_invalid', 'Setting names must be safe snake_case keys with object definitions.');

        continue;
      }

      $type = trim((string) ($definition['type'] ?? ''));
      if (! in_array($type, self::SETTING_TYPES, true)) {
        $issues[] = $this->issue('application_settings_schema_invalid', "Setting {$key} has an unsupported type.");

        continue;
      }

      $normalized = ['type' => $type];

      if ($type === 'enum') {
        $values = array_values(array_filter($definition['values'] ?? [], fn ($value) => is_string($value) || is_int($value)));
        if ($values === []) {
          $issues[] = $this->issue('application_settings_schema_invalid', "Enum setting {$key} requires values.");

          continue;
        }
        $normalized['values'] = $values;
      }

      foreach (['default', 'min', 'max', 'max_length'] as $option) {
        if (array_key_exists($option, $definition)) {
          $normalized[$option] = $definition[$option];
        }
      }

      $schema[$key] = $normalized;
    }

    return $schema;
  }

  private function normalizeSupports(mixed $rawSupports): array
  {
    $rawSupports = is_array($rawSupports) ? $rawSupports : [];

    return [
      'locale' => (bool) ($rawSupports['locale'] ?? false),
      'theme' => (bool) ($rawSupports['theme'] ?? false),
      'multiple_instances' => (bool) ($rawSupports['multiple_instances'] ?? false),
      'authentication_context' => (bool) ($rawSupports['authentication_context'] ?? false),
      'fullscreen' => (bool) ($rawSupports['fullscreen'] ?? false),
    ];
  }

  private function localFile(mixed $rawPath, string $directory, string $expectedType, array &$issues): ?string
  {
    $path = trim((string) $rawPath);
    $extensions = ['css' => ['css'], 'js' => ['js', 'mjs'], 'html' => ['html', 'htm']];

    if ($path === '' || str_contains($path, '\\') || str_contains($path, '..') || str_starts_with($path, '/') || parse_url($path, PHP_URL_SCHEME) !== null || str_contains($path, '?') || str_contains($path, '#')) {
      $issues[] = $this->issue('application_asset_path_invalid', 'Application files must use relative local paths without traversal, query strings, or fragments.');

      return null;
    }

    if (! in_array(strtolower((string) pathinfo($path, PATHINFO_EXTENSION)), $extensions[$expectedType] ?? [], true)) {
      $issues[] = $this->issue('application_asset_path_invalid', "Application file {$path} has the wrong extension.");

      return null;
    }

    $resolved = realpath($directory.DIRECTORY_SEPARATOR.$path);
    $normalizedDirectory = rtrim(realpath($directory) ?: $directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

    if ($resolved === false || ! is_file($resolved) || ! str_starts_with($resolved, $normalizedDirectory)) {
      $issues[] = $this->issue('application_asset_missing', "Application file {$path} is missing or outside its manifest directory.");

      return null;
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', $path);
  }

  private function manifestBaseUrl(string $directory, string $rootPath, string $rootUrl): string
  {
    $relative = ltrim(substr($directory, strlen(rtrim($rootPath, DIRECTORY_SEPARATOR))), DIRECTORY_SEPARATOR);

    return rtrim($rootUrl, '/').($relative !== '' ? '/'.str_replace(DIRECTORY_SEPARATOR, '/', $relative) : '');
  }

  private function issue(string $code, string $message): array
  {
    return compact('code', 'message');
  }
}
