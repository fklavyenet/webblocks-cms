<?php

namespace WebBlocks\Cms\Tests\Unit;

use WebBlocks\Cms\Support\PublicSubmissions\PublicSubmissionProtection;
use WebBlocks\Cms\Support\PublicSubmissions\SubmissionDecision;
use WebBlocks\Cms\Support\PublicSubmissions\SubmissionProof;
use WebBlocks\Cms\Tests\TestCase;

class PublicSubmissionProtectionTest extends TestCase
{
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
}
