<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\PublicSubmissions\PublicSubmissionProtection;
use WebBlocks\Cms\Support\PublicSubmissions\SubmissionDecision;
use WebBlocks\Cms\Support\PublicSubmissions\SubmissionFingerprint;
use WebBlocks\Cms\Support\PublicSubmissions\SubmissionProof;
use WebBlocks\Cms\Tests\TestCase;

class PublicSubmissionProtectionTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();

    foreach ([1, 7, 9, 11] as $siteId) {
      DB::table('wbcms_sites')->insert([
        'id' => $siteId,
        'name' => 'Site '.$siteId,
        'handle' => 'site-'.$siteId,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }
  }

  public function test_proof_is_bound_to_the_surface_and_form(): void
  {
    $proof = app(SubmissionProof::class);
    $stamp = $proof->issueStamp('contact', 10);
    $field = $proof->fieldName('contact', 10);

    $this->assertSame(0, $proof->elapsedSeconds($stamp, 'contact', 10));
    $this->assertNull($proof->elapsedSeconds($stamp, 'contact', 11));
    $this->assertFalse($proof->trapWasTriggered([
      '_form_check_name' => $proof->signedFieldName('contact', 10),
      $field => '',
    ], 'contact', 10));
    $this->assertTrue($proof->trapWasTriggered([], 'contact', 10));
  }

  public function test_commercial_outreach_is_quarantined_without_an_external_service(): void
  {
    $result = app(PublicSubmissionProtection::class)->inspect(
      1,
      'contact',
      10,
      ['message' => 'We noticed your website and offer digital marketing services.'],
      '192.0.2.10',
      30,
    );

    $this->assertSame(40, $result['score']);
    $this->assertSame(SubmissionDecision::QUARANTINE, $result['decision']);
    $this->assertContains('commercial_language', $result['reasons']);
  }

  public function test_repeated_content_escalates_across_different_forms_and_sources(): void
  {
    $protection = app(PublicSubmissionProtection::class);
    $payload = ['message' => 'A harmless-looking campaign message repeated verbatim.'];

    $first = $protection->inspect(7, 'contact', 1, $payload, '192.0.2.1', 30);
    $second = $protection->inspect(7, 'plugin-form', 'sales', $payload, '198.51.100.1', 30);

    $this->assertSame(SubmissionDecision::ALLOW, $first['decision']);
    $this->assertSame(SubmissionDecision::QUARANTINE, $second['decision']);
    $this->assertContains('repeated_content', $second['reasons']);
  }

  public function test_operator_spam_feedback_blocks_a_near_duplicate_locally(): void
  {
    $protection = app(PublicSubmissionProtection::class);
    $reported = ['message' => 'Please carefully consider this exclusive business proposal for your website'];
    $nearDuplicate = ['message' => 'Please carefully consider this exclusive business proposal for your website today'];

    $protection->recordOutcome(9, $reported, 'spam');
    $result = $protection->inspect(9, 'plugin-form', 'contact', $nearDuplicate, '203.0.113.9', 30);

    $this->assertSame(SubmissionDecision::QUARANTINE, $result['decision']);
    $this->assertContains('similar_spam_fingerprint', $result['reasons']);
  }

  public function test_similarity_fingerprint_ignores_small_copy_changes(): void
  {
    $fingerprint = app(SubmissionFingerprint::class);
    $left = $fingerprint->similar('one two three four five six seven eight');
    $right = $fingerprint->similar('one two three four five six seven eight today');

    $this->assertLessThanOrEqual(10, $fingerprint->distance($left, $right));
  }

  public function test_false_positive_feedback_offsets_repeat_pressure(): void
  {
    $protection = app(PublicSubmissionProtection::class);
    $payload = ['message' => 'A legitimate detailed request that a customer sends more than once.'];

    $protection->recordOutcome(11, $payload, 'ham');
    $protection->inspect(11, 'contact', 1, $payload, '192.0.2.11', 30);
    $result = $protection->inspect(11, 'contact', 2, $payload, '198.51.100.11', 30);

    $this->assertSame(SubmissionDecision::ALLOW, $result['decision']);
    $this->assertContains('known_legitimate_fingerprint', $result['reasons']);
    $this->assertNotContains('repeated_content', $result['reasons']);
  }

  public function test_sender_burst_is_shared_across_forms_and_changing_messages(): void
  {
    $protection = app(PublicSubmissionProtection::class);
    $result = [];

    foreach (range(1, 4) as $attempt) {
      $result = $protection->inspect(1, 'plugin-form', 'form-'.$attempt, [
        'email' => 'sender@example.test',
        'message' => 'Distinct request number '.$attempt,
      ], '192.0.2.'.$attempt, 30);
    }

    $this->assertSame(SubmissionDecision::QUARANTINE, $result['decision']);
    $this->assertContains('sender_burst', $result['reasons']);
  }

  public function test_network_burst_uses_ipv4_subnet_without_storing_the_address(): void
  {
    $protection = app(PublicSubmissionProtection::class);
    $result = [];

    foreach (range(1, 12) as $attempt) {
      $result = $protection->inspect(7, 'plugin-form', 'form-'.$attempt, [
        'message' => 'Unique network request '.$attempt,
      ], '198.51.100.'.$attempt, 30);
    }

    $this->assertSame(SubmissionDecision::QUARANTINE, $result['decision']);
    $this->assertContains('network_burst', $result['reasons']);
  }

  public function test_daily_summary_contains_only_aggregate_decisions(): void
  {
    $protection = app(PublicSubmissionProtection::class);
    $protection->inspect(9, 'contact', 1, ['message' => 'Ordinary request'], '203.0.113.1', 30);
    $protection->inspect(9, 'plugin-form', 2, ['message' => 'We noticed your website and offer digital marketing services.'], '203.0.114.1', 30);

    $this->assertSame([
      'days' => 30,
      'allowed' => 1,
      'quarantined' => 1,
      'spam' => 0,
      'total' => 2,
    ], $protection->summary([9]));
    $this->assertSame(
      ['id', 'site_id', 'date', 'surface', 'allowed', 'quarantined', 'spam', 'created_at', 'updated_at'],
      Schema::getColumnListing('wbcms_submission_daily_totals'),
    );
  }
}
