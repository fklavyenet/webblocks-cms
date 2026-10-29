<?php

namespace WebBlocks\Cms\Tests\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Support\WebBlocks;

final class BrowserFixtureServer
{
  private const SCENARIOS = [
    'consent-admin' => ['ConsentManagementTest', 'WEBBLOCKS_CONSENT_ADMIN_FIXTURE', 'authorized_panel_users_can_open_all_three_screens_and_preview'],
    'system-picker' => ['CookieSettingsBlockTest', 'WEBBLOCKS_SYSTEM_PICKER_FIXTURE', 'system_picker_uses_the_system_flag'],
    'consent' => ['ConsentBrowserTest', 'WEBBLOCKS_CONSENT_FIXTURE', 'consent_browser_fixture'],
    'retention' => ['SchedulerSetupTest', 'WEBBLOCKS_RETENTION_FIXTURE', 'retention_table_browser_fixture'],
    'scheduler' => ['SchedulerSetupTest', 'WEBBLOCKS_SCHEDULER_FIXTURE', 'retention_table_browser_fixture'],
    'parent' => ['SlotBlockEditorFragmentSafetyTest', 'WEBBLOCKS_PARENT_FIXTURE', 'full_and_fragment_parent_options'],
    'dirty-overlay' => ['SlotBlockEditorFragmentSafetyTest', 'WEBBLOCKS_PARENT_FIXTURE', 'full_and_fragment_parent_options'],
    'asset-picker' => ['AssetPickerViewTest', 'WEBBLOCKS_ASSET_PICKER_FIXTURE', 'picker_results_remain_inert'],
    'rich-text' => ['RichTextEditorViewTest', 'WEBBLOCKS_RICH_TEXT_FIXTURE', 'editor_controls_and_shared_dialogs'],
    'editor-preview' => ['EditorPreviewTest', 'WEBBLOCKS_EDITOR_PREVIEW_FIXTURE', 'editor_panel_browser_fixture'],
    'update-progress' => ['SystemUpdatesScreenTest', 'WEBBLOCKS_UPDATE_PROGRESS_FIXTURE', 'shared_progress_asset_and_server_admission'],
  ];

  public static function run(string $scenario): void
  {
    if (PHP_SAPI !== 'cli-server') {
      fwrite(STDERR, "Use PHP's local server: php -S 127.0.0.1:8089 tests/Browser/<fixture>.php\n");
      exit(1);
    }

    self::forRequest($scenario, Request::createFromGlobals())->send();
  }

  public static function forRequest(string $scenario, Request $request): Response
  {
    // PHP's built-in router can set SCRIPT_NAME to an existing requested file.
    // getPathInfo() then becomes "/"; route by the actual URI instead.
    $path = parse_url($request->getRequestUri(), PHP_URL_PATH) ?: '/';
    $response = in_array($request->getHost(), ['localhost', '127.0.0.1'], true)
      ? self::response($scenario, $request->getMethod(), $path)
      : new Response('Local fixtures only.', 403);
    $response->headers->set('Cache-Control', 'no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');

    return $response->prepare($request);
  }

  public static function response(string $scenario, string $method, string $path): Response
  {
    if (! isset(self::SCENARIOS[$scenario])) {
      return new Response('Unknown fixture.', 404);
    }
    if (! in_array($method, ['GET', 'HEAD'], true)) {
      return new Response('Read-only fixture.', 405, ['Allow' => 'GET, HEAD']);
    }
    $consentScreen = $scenario === 'consent-admin' && in_array($path, ['/general', '/general-edit', '/texts', '/texts-edit'], true) ? ltrim($path, '/') : null;
    if ($path === '/' || $consentScreen !== null) {
      try {
        return new Response(self::document($scenario, $consentScreen), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
      } catch (RuntimeException) {
        return new Response('Fixture generation failed. Run its PHP test for details.', 500);
      }
    }

    if ($scenario === 'consent' && $path === '/synthetic-embed') {
      return new Response('<!doctype html><title>Synthetic embed</title><p>Local synthetic content</p>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
    if ($scenario === 'consent' && $path === '/synthetic-script.js') {
      return new Response('window.syntheticConsentScriptLoads = (window.syntheticConsentScriptLoads || 0) + 1;', 200, ['Content-Type' => 'application/javascript']);
    }
    $root = dirname(__DIR__, 2);
    $version = WebBlocks::UI_VERSION;
    $assets = [
      "/cms/webblocks-ui/{$version}/webblocks-ui.css",
      "/cms/webblocks-ui/{$version}/webblocks-icons.css",
      "/cms/webblocks-ui/{$version}/webblocks-ui.js",
      '/cms/css/admin.css',
      '/cms/css/public.css',
    ];
    $script = match ($scenario) {
      'consent-admin', 'system-picker' => '/cms/js/admin/core.js',
      'consent' => '/cms/js/privacy-consent-sync.js',
      'dirty-overlay' => '/cms/js/admin/core.js',
      'asset-picker' => '/cms/js/admin/asset-picker.js',
      'rich-text' => '/cms/js/admin/rich-text-editor.js',
      'editor-preview' => '/cms/js/admin/editor-preview.js',
      default => null,
    };
    if ($script !== null) {
      $assets[] = $script;
    }
    if ($scenario === 'consent-admin') {
      $assets[] = '/cms/js/admin/consent.js';
    }
    if ($scenario === 'editor-preview') {
      $assets[] = '/cms/js/admin/editor-preview-frame.js';
    }
    $file = match (true) {
      $path === '/progress.js' && $scenario === 'update-progress' => $root.'/src/Support/Updates/Client/Resources/js/update-progress.js',
      $path === '/fixture.js' && $scenario === 'consent-admin' => $root.'/tests/Browser/consent-admin-fixture.js',
      $path === '/fixture.js' && $scenario === 'consent' => $root.'/tests/Browser/consent-fixture.js',
      $path === '/fixture.js' && $scenario === 'update-progress' => $root.'/tests/Browser/update-progress-fixture.js',
      in_array($path, $assets, true) => $root.'/public'.$path,
      $path === '/fixture.js' && $scenario === 'editor-preview' => $root.'/tests/Browser/editor-preview-fixture.js',
      $path === '/fixture.js' && $scenario === 'dirty-overlay' => $root.'/tests/Browser/dirty-overlay-fixture.js',
      in_array($path, ['/fixture.css', '/fixture.js'], true) => $root.'/tests/Browser'.$path,
      default => null,
    };
    if ($file !== null && is_file($file)) {
      return new BinaryFileResponse($file, 200, [
        'Content-Type' => str_ends_with($file, '.css') ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8',
      ]);
    }
    if ($scenario === 'asset-picker' && preg_match('~^/storage/media/image-([1-9][0-9]{0,2})\.jpg$~', $path, $match) && (int) $match[1] <= 250) {
      return new Response('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="lightblue"/></svg>', 200, ['Content-Type' => 'image/svg+xml']);
    }

    return new Response('Not found.', 404);
  }

  private static function document(string $scenario, ?string $consentScreen = null): string
  {
    [$test, $environmentKey, $filter] = self::SCENARIOS[$scenario];
    $fixture = tempnam(sys_get_temp_dir(), 'wb-browser-');
    if ($fixture === false) {
      throw new RuntimeException('Cannot create fixture file.');
    }
    try {
      $environment = [$environmentKey => $fixture];
      if ($consentScreen !== null) {
        $environment['WEBBLOCKS_CONSENT_ADMIN_SCREEN'] = $consentScreen;
      }
      $process = new Process([PHP_BINARY, 'vendor/bin/phpunit', 'tests/Feature/'.$test.'.php', '--filter', $filter], dirname(__DIR__, 2), $environment);
      $process->setTimeout(120)->mustRun();
      $html = file_get_contents($fixture);
      if ($html === false || $html === '') {
        throw new RuntimeException('The PHP test did not export a fixture.');
      }
    } finally {
      unlink($fixture);
    }

    if ($scenario === 'consent-admin') {
      $html = preg_replace('~<head>.*?</head>|<script\b[^>]*>.*?</script>~s', '', $html);
    }
    if (in_array($scenario, ['parent', 'dirty-overlay'], true)) {
      $html = '<!doctype html><html lang="en" data-wb-theme="light"><body><button type="button" class="wb-btn wb-btn-primary" data-wb-toggle="modal" data-wb-target="#slot-block-editor-modal">Open block</button>'.$html.'</body></html>';
    }
    $version = WebBlocks::UI_VERSION;
    $head = '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CMS browser fixture</title>';
    foreach (["/cms/webblocks-ui/{$version}/webblocks-ui.css", "/cms/webblocks-ui/{$version}/webblocks-icons.css", '/cms/css/admin.css', '/fixture.css'] as $css) {
      $head .= '<link rel="stylesheet" href="'.$css.'">';
    }
    $head .= '<script src="/cms/webblocks-ui/'.$version.'/webblocks-ui.js" defer></script>';
    if (! in_array($scenario, ['parent', 'update-progress', 'retention', 'scheduler', 'consent'], true)) {
      $script = match ($scenario) {
        'consent-admin', 'system-picker' => 'core',
        'dirty-overlay' => 'core',
        'asset-picker' => 'asset-picker',
        'editor-preview' => 'editor-preview',
        default => 'rich-text-editor',
      };
      $head .= '<script src="/cms/js/admin/'.$script.'.js" defer></script>';
    }
    if ($scenario === 'consent-admin') {
      $head .= '<script src="/cms/js/admin/consent.js" defer></script>';
    }
    if ($scenario === 'consent') {
      $head .= '<link rel="stylesheet" href="/cms/css/public.css">';
    }
    $head .= '<script src="/fixture.js" defer></script></head>';
    if ($scenario === 'update-progress') {
      $head = str_replace('</head>', '<script src="/progress.js" defer></script></head>', $head);
    }
    $html = str_replace('<body', $head.'<body data-fixture-scenario="'.$scenario.'"', $html);
    $html = str_replace('http://localhost/storage/', '/storage/', $html);

    return str_replace('</body>', '<output id="test-status" aria-live="off"></output></body>', $html);
  }
}
