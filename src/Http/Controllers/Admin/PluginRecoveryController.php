<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use WebBlocks\Cms\Actions\Plugins\RecoverPlugin;
use WebBlocks\Cms\Http\Requests\Plugins\PluginRecoveryRequest;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;

class PluginRecoveryController
{
  public function index(InstalledPluginRepository $repository): View
  {
    $plugins = array_map(fn (array $installed): array => [
      'handle' => $installed['manifest']['handle'],
      'label' => $installed['manifest']['label'] ?? $installed['manifest']['handle'],
      'version' => $installed['manifest']['version'],
      'enabled' => $installed['enabled'] || (! $repository->isDisabled($installed['manifest']['handle']) && (bool) config('webblocks-plugins.enabled.'.$installed['manifest']['handle'], false)),
      'failed' => $repository->runtimeFailed($installed['manifest']['handle'], $installed['manifest']['version']),
      'previous' => $repository->previous($installed['manifest']['handle']),
    ], $repository->installed());

    return view('webblocks-cms::admin.system.plugins.recovery', compact('plugins'));
  }

  public function update(PluginRecoveryRequest $request, string $plugin, RecoverPlugin $recover): RedirectResponse
  {
    try {
      $recover->handle($plugin, $request->validated('operation'));
    } catch (RuntimeException $exception) {
      return back()->withErrors(['plugin' => $exception->getMessage()]);
    }

    return to_route('admin.plugins.recovery.index')->with('status', __('webblocks-cms::admin.plugin_recovery.saved'));
  }
}
