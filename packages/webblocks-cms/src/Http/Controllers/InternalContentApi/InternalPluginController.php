<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRefresher;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;

class InternalPluginController extends Controller
{
  public function __construct(
    private readonly PluginRegistry $registry,
    private readonly InstalledPluginRepository $plugins,
    private readonly PluginZipInstaller $installer,
    private readonly PluginMigrationRunner $migrations,
    private readonly PluginRuntimeRefresher $runtime,
  ) {}

  public function index(): JsonResponse
  {
    return $this->ok([
      'plugins' => collect($this->registry->summaries())
        ->map(fn (array $plugin): array => $this->publicPlugin($plugin))
        ->values()
        ->all(),
      '_links' => [
        'install' => '/webadmin/api/plugins/install',
      ],
    ]);
  }

  public function install(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'plugin_zip' => ['required', 'file', 'mimes:zip', 'max:20480'],
    ]);

    try {
      $result = $this->installer->install($validated['plugin_zip']->getRealPath());
      $this->runtime->refresh(registerRoutes: true);
    } catch (RuntimeException $exception) {
      return $this->apiError('invalid_plugin_package', $exception->getMessage());
    }

    $summary = $this->freshPluginSummary((string) $result['handle']);

    return response()->json([
      'ok' => true,
      'plugin' => $summary,
      'installed' => [
        'handle' => $result['handle'],
        'version' => $result['version'],
      ],
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  public function enable(string $plugin): JsonResponse
  {
    $definition = $this->requirePlugin($plugin);

    if ($definition instanceof JsonResponse) {
      return $definition;
    }

    $version = $definition->versionText();

    if ($version === null) {
      return $this->apiError('plugin_version_missing', 'The plugin does not declare an installable version.');
    }

    try {
      $this->plugins->enable($definition->handle(), $version);
      $this->runtime->refresh(registerRoutes: true);
    } catch (RuntimeException $exception) {
      return $this->apiError('plugin_enable_failed', $exception->getMessage());
    }

    return $this->ok([
      'plugin' => $this->freshPluginSummary($definition->handle()),
    ]);
  }

  public function setup(string $plugin): JsonResponse
  {
    $definition = $this->requirePlugin($plugin);

    if ($definition instanceof JsonResponse) {
      return $definition;
    }

    if (! $this->registry->isConfiguredEnabled($definition->handle())) {
      return $this->apiError('plugin_not_enabled', 'Enable the plugin before running setup.', 409);
    }

    $version = $definition->versionText();

    if ($version === null) {
      return $this->apiError('plugin_version_missing', 'The plugin does not declare an installable version.');
    }

    try {
      $result = $this->migrations->run($definition);
      $this->plugins->recordSetupResult($definition->handle(), $version, $result);
      $this->runtime->refresh(registerRoutes: true);
    } catch (RuntimeException $exception) {
      return $this->apiError('plugin_setup_failed', $exception->getMessage());
    }

    return $this->ok([
      'plugin' => $this->freshPluginSummary($definition->handle()),
      'setup' => $this->publicSetupResult($result),
    ]);
  }

  public function disable(string $plugin): JsonResponse
  {
    $definition = $this->requirePlugin($plugin);

    if ($definition instanceof JsonResponse) {
      return $definition;
    }

    try {
      $this->plugins->disable($definition->handle());
      $this->runtime->refresh(registerRoutes: true);
    } catch (RuntimeException $exception) {
      return $this->apiError('plugin_disable_failed', $exception->getMessage());
    }

    return $this->ok([
      'plugin' => $this->freshPluginSummary($definition->handle()),
    ]);
  }

  public function uninstall(string $plugin): JsonResponse
  {
    $definition = $this->requirePlugin($plugin);

    if ($definition instanceof JsonResponse) {
      return $definition;
    }

    if ($definition->sourceText() !== 'manual_upload') {
      return $this->apiError('plugin_not_manually_uploaded', 'Only manually uploaded plugins can be uninstalled through this API.', 409);
    }

    if ($this->registry->isConfiguredEnabled($definition->handle())) {
      return $this->apiError('plugin_must_be_disabled', 'Disable the plugin before uninstalling it.', 409);
    }

    $version = $definition->versionText();

    if ($version === null) {
      return $this->apiError('plugin_version_missing', 'The plugin does not declare an installable version.');
    }

    try {
      $this->plugins->uninstall($definition->handle(), $version);
      $this->runtime->refresh(registerRoutes: true);
    } catch (RuntimeException $exception) {
      return $this->apiError('plugin_uninstall_failed', $exception->getMessage());
    }

    return $this->ok([
      'uninstalled' => [
        'handle' => $definition->handle(),
        'version' => $version,
      ],
    ]);
  }

  private function requirePlugin(string $handle): PluginDefinition|JsonResponse
  {
    if (! PluginDefinition::isValidHandle($handle)) {
      return $this->apiError('invalid_plugin_handle', 'Plugin handles must be kebab-case.');
    }

    $definition = $this->registry->get($handle);

    if (! $definition) {
      return $this->apiError('plugin_not_found', 'The requested plugin is not installed or registered.', 404);
    }

    return $definition;
  }

  private function freshPluginSummary(string $handle): ?array
  {
    app()->forgetInstance(PluginRegistry::class);
    $registry = app(PluginRegistry::class);

    foreach ($registry->summaries() as $plugin) {
      if (($plugin['handle'] ?? null) === $handle) {
        return $this->publicPlugin($plugin);
      }
    }

    return null;
  }

  /**
   * @param  array<string, mixed>  $plugin
   * @return array<string, mixed>
   */
  private function publicPlugin(array $plugin): array
  {
    unset($plugin['install_path']);

    return $plugin;
  }

  /**
   * @param  array<string, mixed>  $result
   * @return array<string, mixed>
   */
  private function publicSetupResult(array $result): array
  {
    return [
      'ran' => (bool) ($result['ran'] ?? false),
      'paths_count' => count($result['paths'] ?? []),
      'message' => $result['message'] ?? null,
    ];
  }

  private function ok(array $data): JsonResponse
  {
    return response()->json([
      'ok' => true,
      ...$data,
      'warnings' => [],
      'errors' => [],
    ]);
  }

  private function apiError(string $code, string $message, int $status = 422): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'warnings' => [],
      'errors' => [
        [
          'path' => 'plugin',
          'message' => $message,
        ],
      ],
    ], $status);
  }
}
