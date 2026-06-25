<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\ContactMessageRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
use WebBlocks\Cms\Support\Contact\ContactMessageNotifier;
use WebBlocks\Cms\Support\Contact\ContactMessageSpamScorer;

class ContactMessageController extends Controller
{
  public function __construct(
    private readonly ContactMessageNotifier $notifier,
    private readonly ContactMessageSpamScorer $spamScorer,
  ) {}

  public function store(ContactMessageRequest $request): RedirectResponse
  {
    $payload = $request->payload();
    $block = Block::query()->with(['blockType', 'page.site'])->findOrFail($payload['block_id']);
    $block->page?->loadMissing(['site', 'translations']);
    $block = app(BlockTranslationResolver::class)->resolve($block, site: $block->page?->site);

    abort_unless($block->typeSlug() === 'contact_form', 404);
    abort_unless($block->status === 'published', 404);
    abort_unless($block->page?->status === 'published', 404);

    if ($payload['page_id'] && $payload['page_id'] !== $block->page_id) {
      abort(404);
    }

    $minimumSubmitSeconds = (int) config('contact.minimum_submit_seconds', 3);
    $redirects = app(ContactFormRedirects::class);
    $fallbackUrl = $block->page?->publicUrl() ?: url('/');
    $sourceUrl = $redirects->baseUrl($payload['source_url'], $fallbackUrl);
    $successMessage = $block->success_message ?? config('contact.success_message');

    if ($payload['form_check_filled'] || (now()->timestamp - $payload['submitted_at']) < $minimumSubmitSeconds) {
      return redirect($sourceUrl)
        ->with('contact_form_success_block_id', $block->id)
        ->with('contact_form_success_message', $successMessage);
    }

    $notificationEnabled = (bool) $block->setting('send_email_notification', true);
    $notificationRecipient = $this->notificationRecipient($block, $block->page?->site);
    $spamSignal = $this->spamScorer->score($payload, $request->ip());

    $contactMessage = ContactMessage::create([
      'block_id' => $block->id,
      'page_id' => $block->page_id,
      'name' => $payload['name'],
      'email' => $payload['email'],
      'subject' => $payload['subject'],
      'message' => $payload['message'],
      'status' => $spamSignal['is_spam'] ? 'spam' : 'new',
      'source_url' => $sourceUrl,
      'ip_address' => $request->ip(),
      'user_agent' => $request->userAgent(),
      'referer' => $request->headers->get('referer'),
      'spam_score' => $spamSignal['score'],
      'spam_reasons' => $spamSignal['reasons'] !== [] ? $spamSignal['reasons'] : null,
      'notification_enabled' => $notificationEnabled,
      'notification_recipient' => $notificationRecipient['email'],
      'notification_recipient_source' => $notificationRecipient['source'],
      'notification_status' => 'pending',
    ]);

    $result = $this->notifier->send($contactMessage);

    $contactMessage->update([
      'notification_recipient' => $result->recipient,
      'notification_recipient_source' => $result->recipientSource,
      'notification_status' => $result->status,
      'notification_sent_at' => $result->sent ? now() : null,
      'notification_error' => $result->error,
      'notification_reason' => $result->reason,
    ]);

    return redirect($sourceUrl)
      ->with('contact_form_success_block_id', $block->id)
      ->with('contact_form_success_message', $successMessage);
  }

  private function notificationRecipient(Block $block, ?Site $site): array
  {
    $blockRecipient = trim((string) $block->setting('recipient_email'));

    if ($blockRecipient !== '') {
      return ['email' => $blockRecipient, 'source' => 'block'];
    }

    $siteRecipient = trim((string) $site?->contact_recipient_email);

    if ($siteRecipient !== '') {
      return ['email' => $siteRecipient, 'source' => 'site'];
    }

    return ['email' => null, 'source' => null];
  }
}
