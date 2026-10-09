<?php

namespace WebBlocks\Cms\Mail;

use Illuminate\Mail\Mailable;

// Only trusted site copy, aggregate counts and an authenticated inbox URL enter
// this mailable. No visitor model, source URL, message body or token is accepted.
final class SiteMessageAlert extends Mailable
{
  public function __construct(
    public readonly string $siteLabel,
    public readonly string $inboxUrl,
    public readonly int $messageCount = 1,
    public readonly ?array $summary = null,
  ) {}

  public function build(): self
  {
    return $this->subject(__('webblocks-cms::notifications.'.($this->summary === null ? 'new_subject' : 'summary_subject'), ['site' => $this->siteLabel]))
      ->view('webblocks-cms::emails.site-message-alert')
      ->text('webblocks-cms::emails.site-message-alert-text');
  }

  protected function buildAttachments($message)
  {
    // Laravel adds attachments after Symfony callbacks. Prohibit them at this
    // final assembly step as well, before the transport receives the message.
    if ($this->attachments !== [] || $this->rawAttachments !== [] || $this->diskAttachments !== []) {
      throw new \RuntimeException('Alert attachments are prohibited.');
    }

    return $this;
  }
}
