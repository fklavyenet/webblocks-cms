<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\SupportProviderConnectRequest;
use WebBlocks\Cms\Support\Tickets\SupportActivationService;
use WebBlocks\Cms\Support\Tickets\SupportTicketService;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

/**
 * Lets a signed-in CMS admin report a problem or request a change, and follow
 * the thread, without leaving the admin or holding a second account. The
 * ticket itself lives at the installation's connected support provider.
 *
 * It is a top-level admin item rather than a System one: `access-system` is
 * held by the operator who maintains the installation, and the editor who
 * cannot get a page to publish is exactly the person with something to report.
 */
class SupportController extends Controller
{
  public function __construct(
    private readonly SupportTicketService $support,
    private readonly SupportActivationService $activation,
    private readonly CmsTranslator $translator,
  ) {}

  public function index(Request $request): View
  {
    $tickets = [];
    $error = null;

    if ($this->support->isConfigured()) {
      try {
        $tickets = $this->support->forUser($request->user());
      } catch (Throwable) {
        $error = $this->translator->admin('support.unavailable');
      }
    }

    return view('webblocks-cms::admin.support.index', [
      'tickets' => $tickets,
      'configured' => $this->support->isConfigured(),
      'error' => $error,
      'connection' => $this->support->connection(),
      'canManageConnection' => $request->user()?->can('access-system') === true,
    ]);
  }

  public function create(): View
  {
    return view('webblocks-cms::admin.support.create', [
      'types' => SupportTicketService::TYPES,
      'configured' => $this->support->isConfigured(),
    ]);
  }

  public function store(Request $request): RedirectResponse
  {
    abort_unless($this->support->isConfigured(), 404);

    $data = $request->validate([
      'type' => ['required', Rule::in(SupportTicketService::TYPES)],
      'title' => ['required', 'string', 'max:255'],
      'body' => ['required', 'string', 'max:20000'],
    ]);

    try {
      $ticket = $this->support->file($request->user(), $data['type'], $data['title'], $data['body']);
    } catch (Throwable) {
      // The message the admin typed is still in the form; sending them back
      // with it beats losing it to an error page.
      return back()->withInput()->withErrors(['title' => $this->translator->admin('support.unavailable')]);
    }

    return redirect()
      ->route('admin.support.show', ['ticket' => $ticket['id']])
      ->with('status', $this->translator->admin('support.created'));
  }

  public function connect(SupportProviderConnectRequest $request): RedirectResponse
  {
    try {
      $connection = $this->activation->start($request->validated('provider_url'));
    } catch (Throwable) {
      return back()->withInput()->withErrors(['provider_url' => $this->translator->admin('support.provider_connection_failed')]);
    }

    return redirect()->route('admin.support.index')->with('status', $this->translator->admin('support.activation_started', null, [
      'provider' => $connection->provider_name,
    ]));
  }

  public function refreshActivation(Request $request): RedirectResponse
  {
    abort_unless($request->user()?->can('access-system'), 403);

    try {
      $connection = $this->activation->refresh();
    } catch (Throwable) {
      return back()->withErrors(['provider_url' => $this->translator->admin('support.unavailable')]);
    }

    $key = $connection->isActive() ? 'support.connected' : 'support.activation_pending';

    return redirect()->route('admin.support.index')->with('status', $this->translator->admin($key));
  }

  public function disconnect(Request $request): RedirectResponse
  {
    abort_unless($request->user()?->can('access-system'), 403);

    try {
      $this->activation->disconnect();
    } catch (Throwable) {
      return back()->withErrors(['provider_url' => $this->translator->admin('support.unavailable')]);
    }

    return redirect()->route('admin.support.index')->with('status', $this->translator->admin('support.disconnected'));
  }

  public function show(Request $request, string $ticket): View
  {
    $found = $this->find($request, $ticket);

    return view('webblocks-cms::admin.support.show', [
      'ticket' => $found['ticket'],
      'comments' => $found['comments'],
    ]);
  }

  public function comment(Request $request, string $ticket): RedirectResponse
  {
    abort_unless($this->support->isConfigured(), 404);

    $data = $request->validate([
      'body' => ['required', 'string', 'max:20000'],
    ]);

    try {
      $replied = $this->support->reply($request->user(), $ticket, $data['body']);
    } catch (Throwable) {
      return back()->withInput()->withErrors(['body' => $this->translator->admin('support.unavailable')]);
    }

    abort_unless($replied, 404);

    return redirect()
      ->route('admin.support.show', ['ticket' => $ticket])
      ->with('status', $this->translator->admin('support.reply_sent'));
  }

  /**
   * @return array{ticket: array<string, mixed>, comments: array<int, array<string, mixed>>}
   */
  private function find(Request $request, string $ticket): array
  {
    abort_unless($this->support->isConfigured(), 404);

    try {
      $found = $this->support->findForUser($request->user(), $ticket);
    } catch (Throwable) {
      abort(503, $this->translator->admin('support.unavailable'));
    }

    // Another admin's ticket is a 404 here, not a 403: the reader learns
    // nothing about whether it exists.
    abort_if($found === null, 404);

    return $found;
  }
}
