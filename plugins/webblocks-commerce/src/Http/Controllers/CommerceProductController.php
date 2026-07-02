<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Requests\CommerceProductRequest;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
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
      ]),
      'siteOptions' => $this->siteOptions(),
      'statusOptions' => $this->statusOptions(),
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

    return view($this->view('form'), $this->viewData('Edit Commerce Product', [
      'product' => $product,
      'siteOptions' => $this->siteOptions(),
      'statusOptions' => $this->statusOptions(),
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
}
