@extends('webblocks-cms::layouts.public', [
    'title' => $title,
    'site' => $site,
    'publicLocaleCode' => $publicLocaleCode,
    'publicMeta' => [
        'title' => $title,
        'site_name' => $site?->publicDisplayName() ?? config('app.name'),
        'site_label' => $site?->display_name ?? $site?->name ?? config('app.name'),
        'meta_description' => $message,
        'og_title' => $title,
        'og_description' => $message,
    ],
])

@section('content')
    @php($money = app(\WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter::class))
    <main class="wb-content-shell wb-py-8">
        <div class="wb-stack wb-gap-4 wb-container">
            <section class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-4">
                    <div class="wb-stack wb-gap-2">
                        <p class="wb-text-sm wb-text-muted">Order {{ $order->order_number }}</p>
                        <h1>{{ $heading }}</h1>
                        <p>{{ $message }}</p>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>Status</strong>
                            <div>{{ ucfirst($order->status) }}</div>
                        </div>
                        <div>
                            <strong>Total</strong>
                            <div>{{ $money->format($order->total_amount, $order->currency, $publicLocaleCode) }}</div>
                        </div>
                    </div>

                    <a href="{{ url('/') }}" class="wb-btn wb-btn-secondary">Return to Site</a>
                </div>
            </section>
        </div>
    </main>
@endsection
