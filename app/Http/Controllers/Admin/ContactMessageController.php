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
    public function index(): View
    {
        return view('admin.contact-messages.index', [
            'messages' => ContactMessage::query()
                ->with(['page', 'block.slotType', 'block.blockType'])
                ->latest()
                ->paginate(20)
                ->withQueryString(),
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
