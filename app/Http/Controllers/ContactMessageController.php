<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactMessageRequest;
use App\Mail\ContactMessageNotification;
use App\Models\Block;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends Controller
{
    public function store(ContactMessageRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $block = Block::query()->with(['blockType', 'page'])->findOrFail($payload['block_id']);
        $block->page?->loadMissing('translations');

        abort_unless($block->typeSlug() === 'contact_form', 404);
        abort_unless($block->status === 'published', 404);
        abort_unless($block->page?->status === 'published', 404);

        if ($payload['page_id'] && $payload['page_id'] !== $block->page_id) {
            abort(404);
        }

        $minimumSubmitSeconds = (int) config('contact.minimum_submit_seconds', 3);
        $successMessage = (string) $block->setting('success_message', config('contact.success_message'));
        $redirectUrl = $this->redirectUrl($payload['source_url'] ?: $block->page?->publicUrl(), $block->id);

        // Honeypot and timing failures intentionally look successful so obvious bots do not learn the validation rules.
        if ($payload['website'] !== '' || (now()->timestamp - $payload['submitted_at']) < $minimumSubmitSeconds) {
            return redirect($redirectUrl)
                ->with('contact_form_success_block_id', $block->id)
                ->with('contact_form_success_message', $successMessage);
        }

        $notificationEnabled = (bool) $block->setting('send_email_notification', true);
        $notificationRecipient = trim((string) ($block->setting('recipient_email') ?: config('contact.recipient_email')));

        $contactMessage = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $block->page_id,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'subject' => $payload['subject'],
            'message' => $payload['message'],
            'status' => 'new',
            'source_url' => $payload['source_url'] ?: $block->page?->publicUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'notification_enabled' => $notificationEnabled,
            'notification_recipient' => $notificationRecipient !== '' ? $notificationRecipient : null,
        ]);

        if ($notificationEnabled) {
            if ($notificationRecipient === '') {
                $contactMessage->update([
                    'notification_error' => 'No contact recipient email is configured.',
                ]);
            } else {
                try {
                    Mail::to($notificationRecipient)->send(new ContactMessageNotification($contactMessage));

                    $contactMessage->update([
                        'notification_sent_at' => now(),
                        'notification_error' => null,
                    ]);
                } catch (\Throwable $throwable) {
                    $contactMessage->update([
                        'notification_error' => $throwable->getMessage(),
                    ]);
                }
            }
        }

        return redirect($redirectUrl)
            ->with('contact_form_success_block_id', $block->id)
            ->with('contact_form_success_message', $successMessage);
    }

    private function redirectUrl(?string $sourceUrl, int $blockId): string
    {
        $baseUrl = $sourceUrl && filter_var($sourceUrl, FILTER_VALIDATE_URL)
            ? $sourceUrl
            : url('/');

        return $baseUrl.'#contact-form-'.$blockId;
    }
}
