<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use App\Models\Block;
use App\Models\ContactMessage;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\Contact\ContactMessageNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\ContactMessageRequest;

class ContactMessageController extends Controller
{
    public function __construct(private readonly ContactMessageNotifier $notifier) {}

    public function store(ContactMessageRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $block = Block::query()->with(['blockType', 'page'])->findOrFail($payload['block_id']);
        $block->page?->loadMissing('translations');
        $block = app(BlockTranslationResolver::class)->resolve($block, site: $block->page?->site);

        abort_unless($block->typeSlug() === 'contact_form', 404);
        abort_unless($block->status === 'published', 404);
        abort_unless($block->page?->status === 'published', 404);

        if ($payload['page_id'] && $payload['page_id'] !== $block->page_id) {
            abort(404);
        }

        $minimumSubmitSeconds = (int) config('contact.minimum_submit_seconds', 3);
        $redirectUrl = $this->redirectUrl($payload['source_url'] ?: $block->page?->publicUrl() ?: url('/'), $block->id);

        if ($payload['website'] !== '' || (now()->timestamp - $payload['submitted_at']) < $minimumSubmitSeconds) {
            return redirect($redirectUrl)
                ->with('contact_form_success_block_id', $block->id);
        }

        $notificationEnabled = (bool) $block->setting('send_email_notification', true);
        $notificationRecipient = trim((string) $block->setting('recipient_email'));

        $contactMessage = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $block->page_id,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'subject' => $payload['subject'],
            'message' => $payload['message'],
            'status' => 'new',
            'source_url' => $payload['source_url'] ?: $block->page?->publicUrl() ?: url('/'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'notification_enabled' => $notificationEnabled,
            'notification_recipient' => $notificationRecipient !== '' ? $notificationRecipient : null,
        ]);

        if ($notificationEnabled) {
            $result = $this->notifier->send($contactMessage);

            $contactMessage->update([
                'notification_recipient' => $result->recipient,
                'notification_sent_at' => $result->sent ? now() : null,
                'notification_error' => $result->error,
            ]);
        }

        return redirect($redirectUrl)
            ->with('contact_form_success_block_id', $block->id);
    }

    private function redirectUrl(?string $sourceUrl, int $blockId): string
    {
        $baseUrl = $sourceUrl && filter_var($sourceUrl, FILTER_VALIDATE_URL)
            ? $sourceUrl
            : url('/');

        return $baseUrl.'#contact-form-'.$blockId;
    }
}
