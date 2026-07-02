@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $gatewayStatusClass = $checkoutReady ? 'wb-status-active' : 'wb-status-danger';
    $schemaStatusClass = $schemaReady ? 'wb-status-active' : 'wb-status-pending';
    $configured = fn (bool $ready): string => $ready ? 'Configured' : 'Missing';
    $statusClass = fn (bool $ready): string => $ready ? 'wb-status-active' : 'wb-status-danger';
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Commerce Settings',
        'description' => 'Review checkout gateway readiness without exposing payment secrets.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $schemaReady)
        <div class="wb-alert wb-alert-warning">
            <div>
                <div class="wb-alert-title">Plugin Setup Required</div>
                <div>{{ $schemaMessage }}</div>
            </div>
        </div>
    @endif

    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-stack-1">
                <strong>Checkout Readiness</strong>
                <span class="wb-text-sm wb-text-muted">Secrets are read from environment config and are never displayed.</span>
            </div>
            <span class="wb-status-pill {{ $gatewayStatusClass }}">{{ $checkoutReady ? 'Ready' : 'Not ready' }}</span>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>Schema</strong></div>
                <div class="wb-settings-row-control">
                    <span class="wb-status-pill {{ $schemaStatusClass }}">{{ $schemaReady ? 'Ready' : 'Setup required' }}</span>
                    <span class="wb-text-sm wb-text-muted">{{ $schemaMessage }}</span>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>Gateway</strong></div>
                <div class="wb-settings-row-control">
                    <code>{{ $gateway }}</code>
                    <span class="wb-text-sm wb-text-muted">{{ $checkoutMessage }}</span>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>PayPal Mode</strong></div>
                <div class="wb-settings-row-control"><code>{{ $paypal['mode'] }}</code></div>
            </div>
        </div>
    </section>

    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-stack-1">
                <strong>PayPal Configuration</strong>
                <span class="wb-text-sm wb-text-muted">Set these values in the install environment before accepting payments.</span>
            </div>
            <span class="wb-status-pill {{ $statusClass($paypal['webhook_ready']) }}">{{ $paypal['webhook_ready'] ? 'Webhook ready' : 'Needs config' }}</span>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>Client ID</strong></div>
                <div class="wb-settings-row-control">
                    <span class="wb-status-pill {{ $statusClass($paypal['client_id_configured']) }}">{{ $configured($paypal['client_id_configured']) }}</span>
                    <code>WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID</code>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>Client Secret</strong></div>
                <div class="wb-settings-row-control">
                    <span class="wb-status-pill {{ $statusClass($paypal['client_secret_configured']) }}">{{ $configured($paypal['client_secret_configured']) }}</span>
                    <code>WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_SECRET</code>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>Webhook ID</strong></div>
                <div class="wb-settings-row-control">
                    <span class="wb-status-pill {{ $statusClass($paypal['webhook_id_configured']) }}">{{ $configured($paypal['webhook_id_configured']) }}</span>
                    <code>WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID</code>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>Webhook URL</strong></div>
                <div class="wb-settings-row-control">
                    <code>{{ $paypal['webhook_url'] }}</code>
                </div>
            </div>
        </div>
    </section>

    <section class="wb-card">
        <div class="wb-card-header">
            <strong>Setup Actions</strong>
        </div>
        <div class="wb-card-body wb-cluster wb-cluster-2 wb-flex-wrap">
            <a href="{{ $pluginDetailUrl }}" class="wb-btn wb-btn-secondary">Plugin Detail</a>
            @if (auth()->user()?->isSuperAdmin() && ! $schemaReady)
                <form method="POST" action="{{ $pluginSetupUrl }}">
                    @csrf
                    <button type="submit" class="wb-btn wb-btn-primary">Run Plugin Migrations</button>
                </form>
            @endif
        </div>
    </section>
@endsection
