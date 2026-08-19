<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Actions\Applications\CreateEmbeddedApplication;
use WebBlocks\Cms\Actions\Applications\DeleteEmbeddedApplication;
use WebBlocks\Cms\Actions\Applications\UpdateEmbeddedApplication;
use WebBlocks\Cms\Http\Requests\Admin\EmbeddedApplicationRequest;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class EmbeddedApplicationController extends Controller
{
  public function index(): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.embedded-applications.index', [
      'applications' => EmbeddedApplication::query()->orderBy('name')->get(),
    ]);
  }

  public function create(): View
  {
    return $this->form(new EmbeddedApplication);
  }

  public function store(EmbeddedApplicationRequest $request, CreateEmbeddedApplication $action): RedirectResponse
  {
    $application = $action->handle($request->applicationData(), $request->user()?->getKey());

    return redirect()->route('admin.embedded-applications.edit', $application)->with('status', $this->message('created'));
  }

  public function edit(EmbeddedApplication $embeddedApplication): View
  {
    return $this->form($embeddedApplication);
  }

  public function update(EmbeddedApplicationRequest $request, EmbeddedApplication $embeddedApplication, UpdateEmbeddedApplication $action): RedirectResponse
  {
    $action->handle($embeddedApplication, $request->applicationData(), $request->user()?->getKey());

    return redirect()->route('admin.embedded-applications.edit', $embeddedApplication)->with('status', $this->message('updated'));
  }

  public function destroy(EmbeddedApplication $embeddedApplication, DeleteEmbeddedApplication $action): RedirectResponse
  {
    $action->handle($embeddedApplication);

    return redirect()->route('admin.embedded-applications.index')->with('status', $this->message('deleted'));
  }

  private function form(EmbeddedApplication $application): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.embedded-applications.form', [
      'application' => $application,
    ]);
  }

  private function message(string $key): string
  {
    return app(CmsTranslator::class)->admin('embedded_applications.'.$key, app(AdminLocaleResolver::class)->locale());
  }
}
