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
    if (! $this->plugins->isEnabled($plugin->handle())) {
      return PluginHealthResult::unavailable();
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
