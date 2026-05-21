<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\ContactMessages\ContactMessageBulkDeleter;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\BulkDeleteContactMessagesRequest;

class ContactMessageController extends Controller
{
    public function __construct(
        private readonly AdminAuthorization $authorization,
        private readonly ContactMessageBulkDeleter $contactMessageBulkDeleter,
    ) {}

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

        $baseQuery = ContactMessage::query()
            ->tap(fn ($query) => $this->authorization->scopeContactMessagesForUser($query, $request->user()));

        $filteredQuery = ContactMessage::query()
            ->tap(fn ($query) => $this->authorization->scopeContactMessagesForUser($query, $request->user()))
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
            });

        $totalCount = (clone $baseQuery)->count();

        return view('webblocks-cms::admin.contact-messages.index', [
            'messages' => $filteredQuery
                ->with(['page', 'block.slotType', 'block.blockType'])
                ->latest()
                ->paginate(AdminPagination::perPage())
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'notification' => $notification,
            ],
            'totalCount' => $totalCount,
            'filteredCount' => (clone $filteredQuery)->count(),
        ]);
    }

    public function show(ContactMessage $contactMessage): View
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $contactMessage);
        $contactMessage->load(['page', 'block.blockType', 'block.slotType']);

        if ($contactMessage->status === 'new') {
            $contactMessage->update(['status' => 'read']);
            $contactMessage->refresh();
        }

        return view('webblocks-cms::admin.contact-messages.show', [
            'message' => $contactMessage,
            'statuses' => ContactMessage::statuses(),
        ]);
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $contactMessage);
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
        $this->authorization->abortUnlessSiteAccess(request()->user(), $contactMessage);
        $contactMessage->delete();

        return redirect()
            ->route('admin.contact-messages.index')
            ->with('status', 'Message deleted.');
    }

    public function bulkDestroy(BulkDeleteContactMessagesRequest $request): RedirectResponse
    {
        $result = $this->contactMessageBulkDeleter->deleteSelected($request->user(), $request->validated('contact_message_ids'));

        $redirect = redirect()
            ->route('admin.contact-messages.index')
            ->with($result->deletedCount() > 0 ? 'status' : 'bulk_status', $result->message());

        if ($result->hasFailures()) {
            $redirect->withErrors(['contact_messages' => implode(' ', $result->failureMessages())]);
        }

        return $redirect;
    }
}
