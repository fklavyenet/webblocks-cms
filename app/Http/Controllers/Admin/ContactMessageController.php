<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $notification = $request->string('notification')->toString();

        if (! in_array($status, ContactMessage::statuses(), true)) {
            $status = '';
        }

        if (! in_array($notification, ['sent', 'pending', 'failed', 'disabled'], true)) {
            $notification = '';
        }

        return view('admin.contact-messages.index', [
            'messages' => ContactMessage::query()
                ->with(['page', 'block.slotType', 'block.blockType'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('subject', 'like', "%{$search}%")
                            ->orWhere('message', 'like', "%{$search}%");
                    });
                })
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($notification !== '', function ($query) use ($notification) {
                    match ($notification) {
                        'sent' => $query->where('notification_enabled', true)->whereNotNull('notification_sent_at'),
                        'pending' => $query->where('notification_enabled', true)->whereNull('notification_sent_at')->whereNull('notification_error'),
                        'failed' => $query->whereNotNull('notification_error'),
                        'disabled' => $query->where('notification_enabled', false),
                        default => null,
                    };
                })
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'notification' => $notification,
            ],
        ]);
    }

    public function show(ContactMessage $contactMessage): View
    {
        $contactMessage->load(['page', 'block.blockType', 'block.slotType']);

        if ($contactMessage->status === 'new') {
            $contactMessage->update(['status' => 'read']);
            $contactMessage->refresh();
        }

        return view('admin.contact-messages.show', [
            'message' => $contactMessage,
            'statuses' => ContactMessage::statuses(),
        ]);
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(ContactMessage::statuses())],
        ]);

        $contactMessage->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->back()
            ->with('status', 'Message status updated.');
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()
            ->route('admin.contact-messages.index')
            ->with('status', 'Message deleted.');
    }
}
