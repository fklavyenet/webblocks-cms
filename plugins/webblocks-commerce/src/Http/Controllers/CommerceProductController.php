<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Requests\CommerceProductRequest;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProductTranslation;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceSchema;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CommerceProductController extends Controller
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly WebBlocksCommerceSchema $schema,
  ) {}

  public function index(Request $request): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

    $filters = [
      'search' => trim((string) $request->query('search', '')),
      'status' => trim((string) $request->query('status', '')),
    ];

    $query = CommerceProduct::query()
      ->with('site')
      ->latest('id');

    if ($filters['search'] !== '') {
      $search = $filters['search'];
      $query->where(function ($query) use ($search): void {
        $query
          ->where('title', 'like', '%'.$search.'%')
          ->orWhere('slug', 'like', '%'.$search.'%')
          ->orWhere('sku', 'like', '%'.$search.'%');
      });
    }

    if (in_array($filters['status'], $this->statusValues(), true)) {
      $query->where('status', $filters['status']);
    } else {
      $filters['status'] = '';
    }

    return view($this->view('index'), $this->viewData('Commerce Products', [
      'products' => $query->paginate(20)->withQueryString(),
      'filters' => $filters,
      'statusOptions' => $this->statusOptions(),
    ]));
  }

  public function create(): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

    return view($this->view('form'), $this->viewData('New Commerce Product', [
      'product' => new CommerceProduct([
        'status' => CommerceProduct::STATUS_DRAFT,
        'currency' => strtoupper((string) config('webblocks-commerce.default_currency', 'USD')),
        'tax_class' => CommerceProduct::TAX_CLASS_STANDARD,
      ]),
      'siteOptions' => $this->siteOptions(),
      'statusOptions' => $this->statusOptions(),
      'taxClassOptions' => $this->taxClassOptions(),
      'translationLocales' => $this->translatableLocales(),
      'existingTranslations' => collect(),
      'formAction' => route('webblocks.plugins.webblocks_commerce.products.store'),
      'method' => 'POST',
      'submitLabel' => 'Create Product',
    ]));
  }

  public function store(CommerceProductRequest $request): RedirectResponse
  {
    if (! $this->schema->isReady()) {
      return redirect()
        ->route('webblocks.plugins.webblocks_commerce.products.index')
        ->withErrors(['plugin' => $this->schema->message()]);
    }

    $product = CommerceProduct::query()->create($request->productPayload());
    $this->saveTranslations($product, $request);

    return redirect()
      ->route('webblocks.plugins.webblocks_commerce.products.show', $product)
      ->with('status', 'Commerce product created.');
  }

  public function show(string $product): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

    $product = $this->product($product);
    $product->load('site');

    return view($this->view('show'), $this->viewData($product->title, [
      'product' => $product,
      'buyUrl' => route('webblocks.commerce.products.buy', $product->slug),
    ]));
  }

  public function edit(string $product): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

    $product = $this->product($product);
    $product->load('translations');

    return view($this->view('form'), $this->viewData('Edit Commerce Product', [
      'product' => $product,
      'siteOptions' => $this->siteOptions(),
      'statusOptions' => $this->statusOptions(),
      'taxClassOptions' => $this->taxClassOptions(),
      'translationLocales' => $this->translatableLocales(),
      'existingTranslations' => $product->translations->keyBy('locale_id'),
      'formAction' => route('webblocks.plugins.webblocks_commerce.products.update', $product),
      'method' => 'PUT',
      'submitLabel' => 'Save Product',
    ]));
  }

  public function update(CommerceProductRequest $request, string $product): RedirectResponse
  {
    if (! $this->schema->isReady()) {
      return redirect()
        ->route('webblocks.plugins.webblocks_commerce.products.index')
        ->withErrors(['plugin' => $this->schema->message()]);
    }

    $product = $this->product($product);
    $product->update($request->productPayload());
    $this->saveTranslations($product, $request);

    return redirect()
      ->route('webblocks.plugins.webblocks_commerce.products.show', $product)
      ->with('status', 'Commerce product updated.');
  }

  public function archive(string $product): RedirectResponse
  {
    if (! $this->schema->isReady()) {
      return redirect()
        ->route('webblocks.plugins.webblocks_commerce.products.index')
        ->withErrors(['plugin' => $this->schema->message()]);
    }

    $product = $this->product($product);
    $product->update(['status' => CommerceProduct::STATUS_ARCHIVED]);

    return redirect()
      ->route('webblocks.plugins.webblocks_commerce.products.show', $product)
      ->with('status', 'Commerce product archived.');
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  private function viewData(string $title, array $data): array
  {
    return array_merge($data, [
      'title' => $title,
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle($title),
    ]);
  }

  private function view(string $name): string
  {
    return WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::plugins.webblocks-commerce.products.'.$name;
  }

  private function setupRequiredView(): View
  {
    return view($this->view('setup-required'), $this->viewData('Commerce Products', [
      'message' => $this->schema->message(),
      'pluginDetailUrl' => route('admin.system.plugins.show', 'webblocks-commerce'),
      'pluginSetupUrl' => route('admin.system.plugins.setup', 'webblocks-commerce'),
    ]));
  }

  private function product(string $product): CommerceProduct
  {
    return CommerceProduct::query()->findOrFail($product);
  }

  /**
   * @return array<string, string>
   */
  private function siteOptions(): array
  {
    return Site::query()
      ->primaryFirst()
      ->get()
      ->mapWithKeys(fn (Site $site): array => [
        (string) $site->id => $site->name.' ('.$site->handle.')',
      ])
      ->all();
  }

  /**
   * @return array<string, string>
   */
  private function statusOptions(): array
  {
    return [
      CommerceProduct::STATUS_DRAFT => 'Draft',
      CommerceProduct::STATUS_ACTIVE => 'Active',
      CommerceProduct::STATUS_ARCHIVED => 'Archived',
    ];
  }

  /**
   * @return array<int, string>
   */
  private function statusValues(): array
  {
    return array_keys($this->statusOptions());
  }

  /**
   * @return array<string, string>
   */
  private function taxClassOptions(): array
  {
    return [
      CommerceProduct::TAX_CLASS_STANDARD => 'Standard rate',
      CommerceProduct::TAX_CLASS_REDUCED => 'Reduced rate',
      CommerceProduct::TAX_CLASS_ZERO => 'Zero / exempt',
    ];
  }

  /**
   * Non-default enabled locales a product can be translated into. The base
   * product row is the default-locale/fallback content, so it is not listed.
   *
   * @return Collection<int, Locale>
   */
  private function translatableLocales(): Collection
  {
    return Locale::query()
      ->where('is_enabled', true)
      ->where('is_default', false)
      ->orderBy('code')
      ->get();
  }

  private function saveTranslations(CommerceProduct $product, Request $request): void
  {
    $input = $request->input('translations', []);

    if (! is_array($input)) {
      return;
    }

    $allowed = $this->translatableLocales()->pluck('id')->all();

    foreach ($input as $localeId => $fields) {
      $localeId = (int) $localeId;

      if (! in_array($localeId, $allowed, true) || ! is_array($fields)) {
        continue;
      }

      $title = trim((string) ($fields['title'] ?? '')) ?: null;
      $description = trim((string) ($fields['description'] ?? '')) ?: null;

      if ($title === null && $description === null) {
        CommerceProductTranslation::query()
          ->where('product_id', $product->getKey())
          ->where('locale_id', $localeId)
          ->delete();

        continue;
      }

      CommerceProductTranslation::query()->updateOrCreate(
        ['product_id' => $product->getKey(), 'locale_id' => $localeId],
        ['title' => $title, 'description' => $description],
      );
    }
  }
}
