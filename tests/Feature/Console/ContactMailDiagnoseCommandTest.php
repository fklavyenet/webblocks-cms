<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;

class ContactMailDiagnoseCommandTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function contact_mail_diagnose_outputs_secret_free_resolved_mail_configuration(): void
  {
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', 'mail.example.test');
    config()->set('mail.mailers.smtp.port', 587);
    config()->set('mail.mailers.smtp.scheme', 'smtp');
    config()->set('mail.mailers.smtp.username', 'support@example.test');
    config()->set('mail.mailers.smtp.password', 'super-secret-password');
    config()->set('mail.from.address', 'support@example.test');
    config()->set('contact.recipient_email', 'contact@example.test');

    $this->artisan('contact:mail-diagnose')
      ->expectsOutputToContain('MAIL_MAILER: smtp')
      ->expectsOutputToContain('MAIL_HOST: mail.example.test')
      ->expectsOutputToContain('MAIL_PORT: 587')
      ->expectsOutputToContain('MAIL_SCHEME: smtp')
      ->expectsOutputToContain('MAIL_ENCRYPTION:')
      ->expectsOutputToContain('MAIL_USERNAME: support@example.test')
      ->expectsOutputToContain('MAIL_FROM_ADDRESS: support@example.test')
      ->expectsOutputToContain('CONTACT_RECIPIENT_EMAIL: contact@example.test')
      ->expectsOutputToContain('SMTP send test: skipped')
      ->doesntExpectOutputToContain('super-secret-password')
      ->assertExitCode(0);
  }

  #[Test]
  public function contact_mail_diagnose_can_inspect_contact_form_recipient_fallbacks(): void
  {
    $site = Site::create([
      'name' => 'Diagnostic Site',
      'handle' => 'diagnostic-site',
      'domain' => 'diagnostic.example.test',
      'contact_recipient_email' => 'site@example.test',
      'is_primary' => true,
      'status' => 'active',
    ]);
    $page = Page::create([
      'site_id' => $site->id,
      'title' => 'Contact',
      'slug' => 'contact',
      'status' => 'published',
    ]);
    $block = Block::create([
      'site_id' => $site->id,
      'page_id' => $page->id,
      'type' => 'contact_form',
      'source_type' => 'form',
      'slot' => 'main',
      'title' => 'Contact us',
      'settings' => json_encode([
        'recipient_email' => 'block@example.test',
        'send_email_notification' => true,
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
    ]);

    $this->artisan('contact:mail-diagnose', ['--block' => (string) $block->id])
      ->expectsOutputToContain('Contact Form block ID: #'.$block->id)
      ->expectsOutputToContain('Block recipient_email: block@example.test')
      ->expectsOutputToContain('Block send_email_notification: true')
      ->expectsOutputToContain('Site contact_recipient_email: site@example.test')
      ->assertExitCode(0);
  }
}
