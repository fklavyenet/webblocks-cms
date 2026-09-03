<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\Updates\InstallationTelemetry;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\Updates\Client\PublisherClient;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Cumulative changelog contract ported from webblocks/publisher-client:
 * release['changelog_entries'] is a list of per-version entries strictly newer
 * than the installed version, newest first, degrading to a single entry when
 * the server payload has no intermediate-release list.
 */
class UpdateServerClientChangelogTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-updates.enabled', true);
  }

  #[Test]
  public function changelog_entries_are_cumulative_newer_than_installed_and_newest_first(): void
  {
    $this->fakeLatestResponse([
      'version' => '1.43.0',
      'artifact_url' => 'https://updates.example.test/webblocks-cms-1.43.0.zip',
      'changelog' => [
        ['version' => '1.40.0', 'release_details' => ['summary' => 'Installed release.']],
        ['version' => '1.41.0', 'release_details' => ['summary' => 'First follow-up.', 'highlights' => ['One-click updates.']]],
        ['version' => '1.43.0', 'release_details' => ['summary' => 'Target release.', 'fixes' => ['A fix.']]],
        ['version' => 'v1.42.0'],
        ['version' => '1.39.0', 'release_details' => ['summary' => 'Ancient release.']],
      ],
    ]);

    $entries = $this->client('1.40.0')->check()->release['changelog_entries'];

    $this->assertSame(['1.43.0', 'v1.42.0', '1.41.0'], array_column($entries, 'version'));
    $this->assertSame('Target release.', $entries[0]['summary']);
    $this->assertSame(['fixes', 'technical_notes'], array_column($entries[0]['groups'], 'key'));
    $this->assertSame(['A fix.'], $entries[0]['groups'][0]['items']);
    $this->assertSame(
      ['Installed update engine: Publisher Client '.PublisherClient::VERSION.' (embedded).'],
      $entries[0]['groups'][1]['items'],
    );

    // Version-only entry carries no accordion body.
    $this->assertNull($entries[1]['summary']);
    $this->assertSame([], $entries[1]['groups']);
    $this->assertSame([], $entries[1]['fallback_notes']);
  }

  #[Test]
  public function changelog_degrades_to_a_single_target_entry_without_an_intermediate_list(): void
  {
    $this->fakeLatestResponse([
      'version' => '1.41.0',
      'artifact_url' => 'https://updates.example.test/webblocks-cms-1.41.0.zip',
      'release_notes' => "One-click updates\nAutomatic restore on failure",
    ]);

    $entries = $this->client('1.40.0')->check()->release['changelog_entries'];

    $this->assertCount(1, $entries);
    $this->assertSame('1.41.0', $entries[0]['version']);
    $this->assertSame('One-click updates', $entries[0]['summary']);
    $this->assertSame(['Automatic restore on failure'], $entries[0]['fallback_notes']);
  }

  #[Test]
  public function the_degraded_entry_is_dropped_when_the_target_is_not_newer_than_installed(): void
  {
    $this->fakeLatestResponse([
      'version' => '1.40.0',
      'artifact_url' => 'https://updates.example.test/webblocks-cms-1.40.0.zip',
    ]);

    $result = $this->client('1.40.0')->check();

    $this->assertSame('up_to_date', $result->state);
    $this->assertSame([], $result->release['changelog_entries']);
  }

  #[Test]
  public function non_array_and_versionless_noise_in_the_list_is_tolerated(): void
  {
    $this->fakeLatestResponse([
      'version' => '1.41.0',
      'artifact_url' => 'https://updates.example.test/webblocks-cms-1.41.0.zip',
      'changelog' => [
        'not-an-entry',
        ['release_details' => ['summary' => 'No version, kept.']],
        ['version' => '1.41.0', 'release_details' => ['summary' => 'Target.']],
      ],
    ]);

    $entries = $this->client('1.40.0')->check()->release['changelog_entries'];

    $this->assertSame(['1.41.0', ''], array_column($entries, 'version'));
    $this->assertSame('No version, kept.', $entries[1]['summary']);
  }

  #[Test]
  public function a_single_bullet_release_does_not_repeat_its_summary_as_highlights_or_raw_notes(): void
  {
    $line = 'Simplify the admin topbar user menu to an avatar-only trigger.';

    $this->fakeLatestResponse([
      'version' => '1.41.1',
      'artifact_url' => 'https://updates.example.test/webblocks-cms-1.41.1.zip',
      // The payload builder fills summary AND highlights from the same single
      // changelog line, and ships release_notes as the raw duplicate.
      'release_notes' => "WebBlocks CMS 1.41.1\n\n- {$line}",
      'release_details' => [
        'title' => 'WebBlocks CMS 1.41.1',
        'summary' => $line,
        'highlights' => [$line],
        'technical_notes' => ['Source reference: v1.41.1', 'Artifact checksum: abc123'],
      ],
    ]);

    $entry = $this->client('1.41.0')->check()->release['changelog_entries'][0];

    // Shown once, in the trigger.
    $this->assertSame($line, $entry['summary']);
    // Highlights that merely repeat the summary are dropped; technical notes stay.
    $this->assertSame('technical_notes', $entry['groups'][0]['key']);
    $this->assertSame('Technical notes', $entry['groups'][0]['label']);
    $this->assertSame('Source reference: v1.41.1', $entry['groups'][0]['items'][0]);
    $this->assertSame('Artifact checksum: abc123', $entry['groups'][0]['items'][1]);
    $this->assertSame(
      'Installed update engine: Publisher Client '.PublisherClient::VERSION.' (embedded).',
      $entry['groups'][0]['items'][2],
    );
    // The raw release_notes duplicate is not rendered a second time.
    $this->assertSame([], $entry['fallback_notes']);
  }

  private function fakeLatestResponse(array $data): void
  {
    Http::fake(['*' => Http::response(['api_version' => '1', 'data' => $data])]);
  }

  private function client(string $installedVersion): UpdateServerClient
  {
    $store = Mockery::mock(InstalledVersionStore::class);
    $store->shouldReceive('currentVersion')->andReturn($installedVersion);

    // Final class — the real one degrades to an empty payload without a
    // settings table, which is exactly what this test needs.
    return new UpdateServerClient($store, new InstallationTelemetry);
  }
}
