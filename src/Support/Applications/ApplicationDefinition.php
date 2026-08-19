<?php

namespace WebBlocks\Cms\Support\Applications;

final readonly class ApplicationDefinition
{
  public function __construct(
    public string $handle,
    public string $name,
    public ?string $description,
    public string $version,
    public int $schemaVersion,
    public string $renderMode,
    public array $mount,
    public array $assets,
    public array $supports,
    public array $settingsSchema,
    public ?string $entry,
    public string $provider,
    public string $checksum,
    public array $issues = [],
  ) {}

  public function isReady(): bool
  {
    return $this->issues === [];
  }

  public function withIssue(string $code, string $message): self
  {
    return new self(
      handle: $this->handle,
      name: $this->name,
      description: $this->description,
      version: $this->version,
      schemaVersion: $this->schemaVersion,
      renderMode: $this->renderMode,
      mount: $this->mount,
      assets: $this->assets,
      supports: $this->supports,
      settingsSchema: $this->settingsSchema,
      entry: $this->entry,
      provider: $this->provider,
      checksum: $this->checksum,
      issues: [...$this->issues, compact('code', 'message')],
    );
  }

  public function toArray(bool $includeSchema = true): array
  {
    $payload = [
      'handle' => $this->handle,
      'name' => $this->name,
      'description' => $this->description,
      'version' => $this->version,
      'schema_version' => $this->schemaVersion,
      'provider' => [
        'type' => 'manifest',
        'handle' => $this->provider,
      ],
      'render_mode' => $this->renderMode,
      'mount' => $this->mount,
      'assets' => $this->assets,
      'supports' => $this->supports,
      'entry' => $this->entry,
      'checksum' => $this->checksum,
      'readiness' => [
        'status' => $this->isReady() ? 'ready' : 'invalid',
        'issues' => $this->issues,
      ],
    ];

    if ($includeSchema) {
      $payload['settings_schema'] = $this->settingsSchema;
    }

    return $payload;
  }
}
