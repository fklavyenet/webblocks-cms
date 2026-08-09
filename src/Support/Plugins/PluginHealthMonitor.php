<?php

namespace WebBlocks\Cms\Support\Plugins;

use Throwable;

class PluginHealthMonitor
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  public function healthFor(PluginDefinition $plugin): PluginHealthResult
  {
    if (! $this->plugins->isCompatible($plugin->handle())) {
      return PluginHealthResult::incompatible($this->plugins->incompatibilityMessage($plugin->handle()) ?? 'Plugin is not compatible with this CMS version.');
    }

    if (! $this->plugins->isConfiguredEnabled($plugin->handle())) {
      return PluginHealthResult::unavailable();
    }

    /*
     * A warning, deliberately, and not `incompatible`: incompatible is a state the
     * CMS enforces by refusing to run the plugin, and an unmet requirement is not
     * enforced at all. The plugin is enabled, its routes are live, and it is expected
     * to degrade on its own — so the health line exists to tell an operator why a
     * feature went quiet, not to claim something has been stopped.
     *
     * Checked before the plugin's own reporter, because "the thing I depend on is
     * missing" explains more than whatever that reporter is about to say about the
     * consequences.
     */
    $unmet = $this->plugins->unmetRequirements($plugin->handle());

    if ($unmet !== []) {
      return PluginHealthResult::warning(implode(' ', $unmet));
    }

    $reporter = $plugin->healthReporter();

    if ($reporter === null) {
      return PluginHealthResult::unknown();
    }

    try {
      $result = $this->resolveReporter($reporter)($plugin);
    } catch (Throwable $exception) {
      return PluginHealthResult::warning($exception->getMessage());
    }

    if ($result instanceof PluginHealthResult) {
      return $result;
    }

    if (is_array($result)) {
      return new PluginHealthResult(
        (string) ($result['status'] ?? PluginHealthResult::UNKNOWN),
        (string) ($result['message'] ?? '')
      );
    }

    if (is_string($result) && trim($result) !== '') {
      return PluginHealthResult::unknown(trim($result));
    }

    return PluginHealthResult::unknown();
  }

  /**
   * @return array{status: string, message: string}
   */
  public function healthArrayFor(PluginDefinition $plugin): array
  {
    return $this->healthFor($plugin)->toArray();
  }

  private function resolveReporter(callable|string $reporter): callable
  {
    if (is_callable($reporter)) {
      return $reporter;
    }

    $resolved = app($reporter);

    if (is_callable($resolved)) {
      return $resolved;
    }

    if (method_exists($resolved, 'health')) {
      return [$resolved, 'health'];
    }

    throw new PluginException("Plugin health reporter [{$reporter}] must be invokable or define a health method.");
  }
}
