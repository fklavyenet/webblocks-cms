@extends('webblocks-cms::layouts.public', [
    'title' => $title,
    'site' => $site,
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
                            <div>{{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</div>
                        </div>
                    </div>

                    <a href="{{ url('/') }}" class="wb-btn wb-btn-secondary">Return to Site</a>
                </div>
            </section>
        </div>
    </main>
@endsection
