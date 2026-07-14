@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $hasActiveFilters = $filters['search'] !== '' || $filters['status'] !== '';
    $money = app(\WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter::class);
    $moneyLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Commerce Products',
        'description' => 'Manage simple products for hosted checkout.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('webblocks.plugins.webblocks_commerce.products.index'),
                'search' => [
                    'id' => 'commerce_products_search',
                    'name' => 'search',
                    'label' => 'Search',
                    'value' => $filters['search'],
                    'placeholder' => 'Search products...',
                ],
                'selects' => [
                    [
                        'id' => 'commerce_products_status',
                        'name' => 'status',
                        'label' => 'Status',
                        'value' => $filters['status'],
                        'placeholder' => 'All statuses',
                        'options' => $statusOptions,
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('webblocks.plugins.webblocks_commerce.products.index'),
                'applyLabel' => 'Apply filters',
            ])
        </div>
    </div>

    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>Products</strong>
                <span class="wb-status-pill wb-status-info">{{ $products->total() }}</span>
            </div>

            @can('webblocks-commerce.manage-products')
                <a href="{{ route('webblocks.plugins.webblocks_commerce.products.create') }}" class="wb-btn wb-btn-primary">New Product</a>
            @endcan
        </div>

        @if ($products->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No commerce products found.</div>
                    <div class="wb-empty-text">Create a product or change the active filters.</div>
                    @if ($hasActiveFilters)
                        <div class="wb-empty-action">
                            <a href="{{ route('webblocks.plugins.webblocks_commerce.products.index') }}" class="wb-btn wb-btn-secondary">Reset</a>
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
                                <th>Product</th>
                                <th>Status</th>
                                <th>Price</th>
                                <th>Inventory</th>
                                <th>Site</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                @php
                                    $statusClass = match ($product->status) {
                                        'active' => 'wb-status-active',
                                        'archived' => 'wb-status-pending',
                                        default => 'wb-status-info',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="wb-stack wb-stack-1">
                                            <strong>{{ $product->title }}</strong>
                                            <span class="wb-text-sm wb-text-muted"><code>{{ $product->slug }}</code>{{ $product->sku ? ' | '.$product->sku : '' }}</span>
                                        </div>
                                    </td>
                                    <td><span class="wb-status-pill {{ $statusClass }}">{{ ucfirst($product->status) }}</span></td>
                                    <td class="wb-nowrap">{{ $money->format($product->price_amount, $product->currency, $moneyLocale) }}</td>
                                    <td class="wb-nowrap">{{ $product->inventory_quantity === null ? 'Not tracked' : $product->inventory_quantity }}</td>
                                    <td class="wb-nowrap">{{ $product->site?->name ?? 'Install-wide' }}</td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            <a href="{{ route('webblocks.plugins.webblocks_commerce.products.show', $product) }}" class="wb-action-btn wb-action-btn-view" title="View product" aria-label="View product">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>
                                            @can('webblocks-commerce.manage-products')
                                                <a href="{{ route('webblocks.plugins.webblocks_commerce.products.edit', $product) }}" class="wb-action-btn wb-action-btn-edit" title="Edit product" aria-label="Edit product">
                                                    <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $products, 'ariaLabel' => 'Commerce products pagination', 'compact' => true])
    </section>
@endsection
