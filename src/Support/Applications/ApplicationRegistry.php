<?php

namespace WebBlocks\Cms\Support\Applications;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use SplFileInfo;

class ApplicationRegistry
{
  public function __construct(
    private readonly ApplicationManifestLoader $loader,
  ) {}

  /**
   * @return Collection<string, ApplicationDefinition>
   */
  public function all(): Collection
  {
    $definitions = collect();

    foreach ($this->roots() as $root) {
      if (! is_dir($root['path'])) {
        continue;
      }

      $manifests = collect(File::allFiles($root['path']))
        ->filter(fn (SplFileInfo $file): bool => $file->getFilename() === 'application.json')
        ->sortBy(fn (SplFileInfo $file): string => $file->getPathname());

      foreach ($manifests as $manifest) {
        $definition = $this->loader->load($manifest, $root['path'], $root['url']);

        if ($definitions->has($definition->handle)) {
          $definitions->put(
            $definition->handle,
            $definitions->get($definition->handle)->withIssue(
              'application_handle_duplicate',
              'Application handles must be unique across all configured roots.',
            ),
          );

          continue;
        }

        $definitions->put($definition->handle, $definition);
      }
    }

    return $definitions->sortKeys();
  }

  public function find(string $handle): ?ApplicationDefinition
  {
    return $this->all()->get(trim($handle));
  }

  public function ready(string $handle): ?ApplicationDefinition
  {
    $definition = $this->find($handle);

    return $definition?->isReady() ? $definition : null;
  }

  private function roots(): array
  {
    return collect(config('cms.embedded_applications.roots', []))
      ->filter(fn ($root): bool => is_array($root))
      ->map(function (array $root): ?array {
        $path = trim((string) ($root['path'] ?? ''));
        $url = '/'.trim((string) ($root['url'] ?? ''), '/');

        if ($path === '' || $url === '/') {
          return null;
        }

        return ['path' => rtrim($path, DIRECTORY_SEPARATOR), 'url' => $url];
      })
      ->filter()
      ->values()
      ->all();
  }
}
