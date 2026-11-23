<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WebBlocks\Cms\Actions\Plugins\RecoverPlugin;
use WebBlocks\Cms\Http\Requests\Plugins\PluginRecoveryRequest;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
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
      'failure_reference' => $repository->runtimeFailureReference($installed['manifest']['handle'], $installed['manifest']['version']),
      'previous' => $repository->previous($installed['manifest']['handle']),
    ], $repository->installed());

    return view('webblocks-cms::admin.system.plugins.recovery', compact('plugins'));
  }

  public function update(PluginRecoveryRequest $request, string $plugin, RecoverPlugin $recover): RedirectResponse
  {
    $operation = new ExtensionOperation('plugin', $request->validated('operation'), 'activation');
    $operation->identify($plugin);
    try {
      $installed = collect(app(InstalledPluginRepository::class)->installed())->firstWhere('manifest.handle', $plugin);
      $operation->identify($plugin, $installed['manifest']['version'] ?? null, $installed['manifest']['label'] ?? null);
      $recover->handle($plugin, $request->validated('operation'), $operation);
    } catch (HttpExceptionInterface $exception) {
      throw $exception;
    } catch (Throwable) {
      return back()->withErrors(['plugin' => $operation->fail()]);
    }

    return to_route('admin.plugins.recovery.index')->with('status', __('webblocks-cms::admin.plugin_recovery.saved'));
  }
}
