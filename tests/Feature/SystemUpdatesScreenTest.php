<?php

namespace WebBlocks\Cms\Tests\Feature;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Renders the v3 fleet-standard System Updates screen in both states and pins
 * its key elements: single card, avatar header, one-click update button with
 * backup note + Backups-screen advisory, per-version changelog accordion,
 * permanent history accordion with eye-modal logs, and the interstitial
 * progress modal — with the retired two-phase surface absent.
 */
class SystemUpdatesScreenTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_update_available_state_renders_the_v3_card(): void
  {
    $html = $this->renderScreen($this->updateAvailableReport(), runs: collect([
      $this->makeRun(1, '1.39.0', '1.40.0', SystemUpdateRun::STATUS_SUCCESS, output: "Applied 1.40.0\nDatabase migrated."),
    ]));

    // Single-card header with avatar + icon.
    $this->assertStringContainsString('wb-avatar wb-avatar-lg wb-avatar-info', $html);
    $this->assertStringContainsString('wb-icon-arrow-up-circle', $html);
    $this->assertStringContainsString('1.40.0 → 1.41.0', $html);

    // One-click update button (no confirm step) + microcopy + backup advisory link.
    $this->assertStringContainsString('Update to 1.41.0', $html);
    $this->assertStringContainsString('data-wb-update-form', $html);
    $this->assertStringContainsString('Backed up first · restored automatically on failure', $html);
    $this->assertStringContainsString('open the Backups screen', $html);
    $this->assertStringContainsString(route('admin.system.backups.index'), $html);

    // Cumulative per-version changelog accordion, version-only rows as wb-list-item.
    $this->assertStringContainsString('What&#039;s new since 1.40.0', $html);
    $this->assertStringContainsString('wb-accordion-trigger', $html);
    $this->assertStringContainsString('One-click self-updates.', $html);
    $this->assertStringContainsString('wb-list-item', $html);

    // Progress modal wired to the indicator poll.
    $this->assertStringContainsString('data-webblocks-update-progress-modal', $html);
    $this->assertStringContainsString(route('admin.system.updates.indicator'), $html);
    $this->assertStringContainsString('Updating to 1.41.0', $html);

    // History accordion + eye-modal log.
    $this->assertStringContainsString('Update history', $html);
    $this->assertStringContainsString('wb-icon-eye', $html);
    $this->assertStringContainsString('wb-run-log-1', $html);
    $this->assertStringContainsString('Applied 1.40.0', $html);

    $this->assertRetiredSurfaceAbsent($html);
  }

  #[Test]
  public function the_up_to_date_state_renders_the_quiet_card_with_current_notes(): void
  {
    $html = $this->renderScreen($this->upToDateReport(), runs: collect());

    // Success avatar + single "Installed version · Last checked" line.
    $this->assertStringContainsString('wb-avatar wb-avatar-lg wb-avatar-success', $html);
    $this->assertStringContainsString('Up to date', $html);
    $this->assertStringContainsString('Installed version', $html);
    $this->assertStringContainsString('Last checked', $html);
    $this->assertStringContainsString('2026-07-23 10:00', $html);

    // Folded "What's new in X" accordion for the current release.
    $this->assertStringContainsString('What&#039;s new in 1.40.0', $html);

    // No update action in this state.
    $this->assertStringNotContainsString('data-wb-update-form', $html);
    $this->assertStringNotContainsString('data-webblocks-update-progress-modal', $html);

    // No recorded runs → the history accordion is not rendered at all.
    $this->assertStringNotContainsString('Update history', $html);
    $this->assertStringNotContainsString('wb-update-history', $html);

    $this->assertRetiredSurfaceAbsent($html);
  }

  #[Test]
  public function failing_preflight_checks_render_as_a_warning_callout_without_the_update_button(): void
  {
    $report = $this->updateAvailableReport();
    $report['can_update'] = false;
    $report['checks'][] = [
      'label' => 'Free disk space',
      'status' => 'fail',
      'message' => 'Only 100 MB free; at least 500 MB is required.',
      'badge_class' => 'wb-status-danger',
    ];

    $html = $this->renderScreen($report, runs: collect());

    $this->assertStringContainsString('data-webblocks-updates-preflight', $html);
    $this->assertStringContainsString('Preflight checks need attention', $html);
    $this->assertStringContainsString('Only 100 MB free; at least 500 MB is required.', $html);
    $this->assertStringNotContainsString('data-wb-update-form', $html);
  }

  private function renderScreen(array $report, $runs): string
  {
    return view('webblocks-cms::admin.system.updates', [
      'report' => $report,
      'runs' => $runs,
      'preflight' => $report['checks'] ?? [],
      'checkedAt' => CarbonImmutable::parse('2026-07-23 10:00'),
    ])->render();
  }

  private function makeRun(int $id, string $from, string $to, string $status, ?string $output = null): SystemUpdateRun
  {
    $run = new SystemUpdateRun([
      'from_version' => $from,
      'to_version' => $to,
      'status' => $status,
      'output' => $output,
      'started_at' => '2026-07-20 09:00:00',
      'finished_at' => '2026-07-20 09:02:00',
    ]);
    $run->id = $id;

    return $run;
  }

  private function updateAvailableReport(): array
  {
    return [
      'checked_at' => CarbonImmutable::parse('2026-07-23 10:00'),
      'installed_version' => '1.40.0',
      'version' => [
        'state' => 'update_available',
        'label' => 'Update available',
        'message' => 'A newer published release is available.',
        'badge_class' => 'wb-status-info',
        'installed_version' => '1.40.0',
        'latest_version' => '1.41.0',
        'update_available' => true,
        'compatibility' => ['status' => 'compatible', 'reasons' => []],
        'error_message' => null,
        'release' => [
          'version' => '1.41.0',
          'download_url' => 'https://updates.example.test/webblocks-cms-1.41.0.zip',
          'changelog_entries' => [
            [
              'version' => '1.41.0',
              'name' => 'WebBlocks CMS 1.41.0',
              'summary' => 'One-click self-updates.',
              'groups' => [['key' => 'highlights', 'label' => 'Highlights', 'items' => ['Single update flow.']]],
              'fallback_notes' => [],
              'released_at' => '2026-07-22',
            ],
            [
              'version' => '1.40.5',
              'name' => 'WebBlocks CMS 1.40.5',
              'summary' => null,
              'groups' => [],
              'fallback_notes' => [],
              'released_at' => '2026-07-10',
            ],
          ],
        ],
      ],
      'checks' => [
        ['label' => 'Database connection', 'status' => 'pass', 'message' => 'OK', 'badge_class' => 'wb-status-active'],
      ],
      'can_update' => true,
    ];
  }

  private function upToDateReport(): array
  {
    return [
      'checked_at' => CarbonImmutable::parse('2026-07-23 10:00'),
      'installed_version' => '1.40.0',
      'version' => [
        'state' => 'up_to_date',
        'label' => 'Already up to date',
        'message' => 'This install is already on the latest published release.',
        'badge_class' => 'wb-status-active',
        'installed_version' => '1.40.0',
        'latest_version' => '1.40.0',
        'update_available' => false,
        'compatibility' => ['status' => 'compatible', 'reasons' => []],
        'error_message' => null,
        'release' => [
          'version' => '1.40.0',
          'release_details' => [
            'title' => 'WebBlocks CMS 1.40.0',
            'summary' => 'Admin polish and drift cleanup.',
            'groups' => [['key' => 'highlights', 'label' => 'Highlights', 'items' => ['Admin CSS cleanup.']]],
            'fallback_notes' => [],
            'has_notes' => true,
          ],
        ],
      ],
      'checks' => [
        ['label' => 'Database connection', 'status' => 'pass', 'message' => 'OK', 'badge_class' => 'wb-status-active'],
      ],
      'can_update' => false,
    ];
  }

  private function assertRetiredSurfaceAbsent(string $html): void
  {
    // Two-phase / blocker-era surface must be gone.
    $this->assertStringNotContainsString('Continue update', $html);
    $this->assertStringNotContainsString('support-report', $html);
    $this->assertStringNotContainsString('Download support report', $html);
    $this->assertStringNotContainsString('data-webblocks-updates-hero', $html);
    $this->assertStringNotContainsString('Package Safety Details', $html);
    $this->assertStringNotContainsString('Technical details and history', $html);
  }
}
