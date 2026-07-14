@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $hasActiveFilters = $filters['search'] !== '' || $filters['status'] !== '' || $filters['gateway'] !== '';
    $money = app(\WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter::class);
    $moneyLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Commerce Orders',
        'description' => 'Review checkout attempts, payment status, and gateway references.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('webblocks.plugins.webblocks_commerce.orders.index'),
                'search' => [
                    'id' => 'commerce_orders_search',
                    'name' => 'search',
                    'label' => 'Search',
                    'value' => $filters['search'],
                    'placeholder' => 'Search orders...',
                ],
                'selects' => [
                    [
                        'id' => 'commerce_orders_status',
                        'name' => 'status',
                        'label' => 'Status',
                        'value' => $filters['status'],
                        'placeholder' => 'All statuses',
                        'options' => $statusOptions,
                    ],
                    [
                        'id' => 'commerce_orders_gateway',
                        'name' => 'gateway',
                        'label' => 'Gateway',
                        'value' => $filters['gateway'],
                        'placeholder' => 'All gateways',
                        'options' => $gatewayOptions,
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('webblocks.plugins.webblocks_commerce.orders.index'),
                'applyLabel' => 'Apply filters',
            ])
        </div>
    </div>

    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>Orders</strong>
                <span class="wb-status-pill wb-status-info">{{ $orders->total() }}</span>
            </div>
        </div>

        @if ($orders->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No commerce orders found.</div>
                    <div class="wb-empty-text">Start a checkout or change the active filters.</div>
                    @if ($hasActiveFilters)
                        <div class="wb-empty-action">
                            <a href="{{ route('webblocks.plugins.webblocks_commerce.orders.index') }}" class="wb-btn wb-btn-secondary">Reset</a>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Customer</th>
                                <th>Gateway</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                @php
                                    $statusClass = match ($order->status) {
                                        'paid' => 'wb-status-active',
                                        'failed', 'cancelled', 'expired' => 'wb-status-danger',
                                        'refunded' => 'wb-status-pending',
                                        default => 'wb-status-info',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="wb-stack wb-stack-1">
                                            <strong>{{ $order->order_number }}</strong>
                                            <span class="wb-text-sm wb-text-muted">{{ $order->site?->name ?? 'Install-wide' }}</span>
                                        </div>
                                    </td>
                                    <td><span class="wb-status-pill {{ $statusClass }}">{{ ucfirst($order->status) }}</span></td>
                                    <td class="wb-nowrap">{{ $money->format($order->total_amount, $order->currency, $moneyLocale) }}</td>
                                    <td class="wb-nowrap">{{ $order->customer_email ?: '-' }}</td>
                                    <td class="wb-nowrap">{{ $order->gateway }}</td>
                                    <td class="wb-nowrap">{{ $order->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            <a href="{{ route('webblocks.plugins.webblocks_commerce.orders.show', $order) }}" class="wb-action-btn wb-action-btn-view" title="View order" aria-label="View order">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $orders, 'ariaLabel' => 'Commerce orders pagination', 'compact' => true])
    </section>
@endsection
