<?php

namespace WebBlocks\Cms\Support\Applications;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\EmbeddedApplication;

class ApplicationRegistry
{
  /** @return Collection<string, ApplicationDefinition> */
  public function all(): Collection
  {
    if (! Schema::hasTable((new EmbeddedApplication)->getTable())) {
      return collect();
    }

    return EmbeddedApplication::query()->orderBy('name')->get()
      ->mapWithKeys(fn (EmbeddedApplication $application): array => [$application->handle => $this->definition($application)]);
  }

  public function find(string $handle): ?ApplicationDefinition
  {
    if (! Schema::hasTable((new EmbeddedApplication)->getTable())) {
      return null;
    }

    $application = EmbeddedApplication::query()->where('handle', trim($handle))->first();

    return $application ? $this->definition($application) : null;
  }

  public function ready(string $handle): ?ApplicationDefinition
  {
    if (! Schema::hasTable((new EmbeddedApplication)->getTable())) {
      return null;
    }

    $application = EmbeddedApplication::query()->where('handle', trim($handle))->where('is_enabled', true)->first();

    return $application ? $this->definition($application) : null;
  }

  private function definition(EmbeddedApplication $application): ApplicationDefinition
  {
    $mount = $application->render_mode === 'inline'
      ? array_filter(['element' => $application->mount_element ?: 'div', 'class' => $application->mount_classes])
      : [];
    $assets = [
      'css' => collect($application->css_assets ?? [])->map(fn (string $path): array => ['path' => $path])->values()->all(),
      'js' => collect($application->js_assets ?? [])->map(function (array|string $asset): array {
        if (is_string($asset)) {
          return ['path' => $asset, 'type' => 'classic', 'load_position' => 'body_end'];
        }

        return [
          'path' => (string) ($asset['path'] ?? ''),
          'type' => (string) ($asset['type'] ?? 'classic'),
          'load_position' => (string) ($asset['load_position'] ?? 'body_end'),
        ];
      })->values()->all(),
    ];
    $checksum = hash('sha256', json_encode([
      $application->handle, $application->version, $application->render_mode, $application->entry_url,
      $mount, $assets, $application->supports, $application->settings_schema, $application->is_enabled,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return new ApplicationDefinition(
      handle: $application->handle,
      name: $application->name,
      description: $application->description,
      version: $application->version,
      schemaVersion: 1,
      renderMode: $application->render_mode,
      mount: $mount,
      assets: $assets,
      supports: $application->supports ?? [],
      settingsSchema: $application->settings_schema ?? [],
      entry: $application->render_mode === 'iframe' ? $application->entry_url : null,
      provider: 'database:'.$application->handle,
      checksum: $checksum,
      issues: $application->is_enabled ? [] : [['code' => 'application_disabled', 'message' => 'Embedded Application is disabled.']],
    );
  }
}
