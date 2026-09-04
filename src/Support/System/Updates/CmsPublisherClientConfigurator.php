<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Contracts\Foundation\Application;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\ConfigVersionResolver;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

final class CmsPublisherClientConfigurator
{
  public function __construct(private readonly Application $app) {}

  public function configure(): void
  {
    $config = $this->app['config'];
    $runtimeRoot = dirname(__DIR__, 4);

    $config->set('publisher-client.product', 'webblocks-cms');
    $config->set('publisher-client.product_name', 'WebBlocks CMS');
    $config->set('publisher-client.channel', (string) config('webblocks-updates.channel', 'stable'));
    $config->set('publisher-client.server_url', (string) config('webblocks-updates.server_url'));
    $config->set('publisher-client.latest_path', (string) config('webblocks-updates.latest_path', '/api/updates/latest'));
    $config->set('publisher-client.apply.workspace_root', (string) config('webblocks-updates.installer.workspace_root', 'app/system-updates'));
    $config->set('publisher-client.apply.download_timeout_seconds', (int) config('webblocks-updates.installer.download_timeout_seconds', 120));
    $config->set('publisher-client.lock.name', (string) config('webblocks-updates.installer.lock_name', 'system-updates:run'));
    $config->set('publisher-client.lock.ttl_seconds', (int) config('webblocks-updates.installer.lock_ttl_seconds', 900));
    $config->set('publisher-client.apply.strategy', 'package');
    $config->set('publisher-client.apply.target_path', $runtimeRoot);
    $config->set('publisher-client.apply.enforce_active_runtime_target', true);
    $config->set('publisher-client.apply.composer_install', false);
    $config->set('publisher-client.apply.package_validation.allowed_roots', [
      'composer.json', 'src', 'routes', 'resources', 'database', 'config', 'public', 'docs', 'stubs',
    ]);
    $config->set('publisher-client.apply.package_validation.forbidden_content_patterns', []);
    $config->set('publisher-client.apply.package_validation.content_scan_excluded_paths', []);
    $config->set('publisher-client.apply.package_validation.required_paths', ['src']);
    $config->set('publisher-client.package.name', 'fklavyenet/webblocks-cms');
    $config->set('publisher-client.package.service_provider', WebBlocksCmsServiceProvider::class);
    $config->set('publisher-client.version.resolver', ConfigVersionResolver::class);
    $config->set('publisher-client.version.source', WebBlocks::VERSION);
    $config->set('publisher-client.version.const_file', 'src/Support/WebBlocks.php');
    $config->set('publisher-client.version.const_name', 'VERSION');
    $config->set('publisher-client.migrations.enabled', false);
    $config->set('publisher-client.commands.allowed', []);
    $config->set('publisher-client.commands.post_apply', []);
  }
}
