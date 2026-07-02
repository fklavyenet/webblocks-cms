<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceSchema;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CommerceOrderController extends Controller
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
      'gateway' => trim((string) $request->query('gateway', '')),
    ];

    $query = CommerceOrder::query()
      ->with(['site'])
      ->latest('id');

    if ($filters['search'] !== '') {
      $search = $filters['search'];
      $query->where(function ($query) use ($search): void {
        $query
          ->where('order_number', 'like', '%'.$search.'%')
          ->orWhere('customer_email', 'like', '%'.$search.'%')
          ->orWhere('gateway_checkout_id', 'like', '%'.$search.'%')
          ->orWhere('gateway_payment_id', 'like', '%'.$search.'%');
      });
    }

    if (in_array($filters['status'], $this->statusValues(), true)) {
      $query->where('status', $filters['status']);
    } else {
      $filters['status'] = '';
    }

    if ($filters['gateway'] !== '') {
      $query->where('gateway', $filters['gateway']);
    }

    return view($this->view('index'), $this->viewData('Commerce Orders', [
      'orders' => $query->paginate(20)->withQueryString(),
      'filters' => $filters,
      'statusOptions' => $this->statusOptions(),
      'gatewayOptions' => $this->gatewayOptions(),
    ]));
  }

  public function show(string $order): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

    $order = CommerceOrder::query()
      ->with(['site', 'items.product', 'payments'])
      ->findOrFail($order);

    return view($this->view('show'), $this->viewData($order->order_number, [
      'order' => $order,
    ]));
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
    return WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::plugins.webblocks-commerce.orders.'.$name;
  }

  private function setupRequiredView(): View
  {
    return view($this->view('setup-required'), $this->viewData('Commerce Orders', [
      'message' => $this->schema->message(),
      'pluginDetailUrl' => route('admin.system.plugins.show', 'webblocks-commerce'),
      'pluginSetupUrl' => route('admin.system.plugins.setup', 'webblocks-commerce'),
    ]));
  }

  /**
   * @return array<string, string>
   */
  private function statusOptions(): array
  {
    return [
      CommerceOrder::STATUS_PENDING => 'Pending',
      CommerceOrder::STATUS_PAID => 'Paid',
      CommerceOrder::STATUS_FAILED => 'Failed',
      CommerceOrder::STATUS_CANCELLED => 'Cancelled',
      CommerceOrder::STATUS_EXPIRED => 'Expired',
      CommerceOrder::STATUS_REFUNDED => 'Refunded',
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
  private function gatewayOptions(): array
  {
    return CommerceOrder::query()
      ->select('gateway')
      ->whereNotNull('gateway')
      ->distinct()
      ->orderBy('gateway')
      ->pluck('gateway', 'gateway')
      ->all();
  }
}
