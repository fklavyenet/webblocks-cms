<?php

namespace WebBlocks\Cms\Mail;

use Illuminate\Mail\Mailable;

class ContactMessageBatch extends Mailable
{
  public function __construct(public readonly array $messages) {}

  public function build(): self
  {
    return $this->subject(__('webblocks-cms::notifications.batch_subject'))
      ->view('webblocks-cms::emails.contact-message-batch');
  }
}
