@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $commerceText = fn (string $key, array $replace = [], ?string $fallback = null): string => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
        ->plugin('webblocks-commerce', 'admin.'.$key, $adminLocale, $replace, $fallback);
    $gatewayStatusClass = $checkoutReady ? 'wb-status-active' : 'wb-status-danger';
    $schemaStatusClass = $schemaReady ? 'wb-status-active' : 'wb-status-pending';
    $configured = fn (bool $ready): string => $ready ? $commerceText('settings.configured') : $commerceText('settings.missing');
    $statusClass = fn (bool $ready): string => $ready ? 'wb-status-active' : 'wb-status-danger';
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $commerceText('settings.title'),
        'description' => $commerceText('settings.description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $schemaReady)
        <div class="wb-alert wb-alert-warning">
            <div>
                <div class="wb-alert-title">{{ $commerceText('settings.plugin_setup_required') }}</div>
                <div>{{ $schemaMessage }}</div>
            </div>
        </div>
    @endif

    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-stack-1">
                <strong>{{ $commerceText('settings.checkout_readiness') }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $commerceText('settings.checkout_help') }}</span>
            </div>
            <span class="wb-status-pill {{ $gatewayStatusClass }}">{{ $checkoutReady ? $commerceText('settings.ready') : $commerceText('settings.not_ready') }}</span>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $commerceText('settings.schema') }}</strong></div>
                <div class="wb-settings-row-control">
                    <span class="wb-status-pill {{ $schemaStatusClass }}">{{ $schemaReady ? $commerceText('settings.ready') : $commerceText('settings.setup_required') }}</span>
                    <span class="wb-text-sm wb-text-muted">{{ $schemaMessage }}</span>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $commerceText('settings.gateway') }}</strong></div>
                <div class="wb-settings-row-control">
                    <code>{{ $gateway }}</code>
                    <span class="wb-text-sm wb-text-muted">{{ $checkoutMessage }}</span>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $commerceText('settings.paypal_mode') }}</strong></div>
                <div class="wb-settings-row-control"><code>{{ $paypal['mode'] }}</code></div>
            </div>
        </div>
    </section>

    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-stack-1">
                <strong>{{ $commerceText('settings.paypal_configuration') }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $commerceText('settings.paypal_help') }}</span>
            </div>
            <span class="wb-status-pill {{ $statusClass($paypal['webhook_ready']) }}">{{ $paypal['webhook_ready'] ? $commerceText('settings.webhook_ready') : $commerceText('settings.needs_config') }}</span>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $commerceText('settings.client_id') }}</strong></div>
                <div class="wb-settings-row-control">
                    <span class="wb-status-pill {{ $statusClass($paypal['client_id_configured']) }}">{{ $configured($paypal['client_id_configured']) }}</span>
                    <code>WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID</code>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $commerceText('settings.client_secret') }}</strong></div>
                <div class="wb-settings-row-control">
                    <span class="wb-status-pill {{ $statusClass($paypal['client_secret_configured']) }}">{{ $configured($paypal['client_secret_configured']) }}</span>
                    <code>WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_SECRET</code>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $commerceText('settings.webhook_id') }}</strong></div>
                <div class="wb-settings-row-control">
                    <span class="wb-status-pill {{ $statusClass($paypal['webhook_id_configured']) }}">{{ $configured($paypal['webhook_id_configured']) }}</span>
                    <code>WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID</code>
                </div>
            </div>
            <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $commerceText('settings.webhook_url') }}</strong></div>
                <div class="wb-settings-row-control">
                    <code>{{ $paypal['webhook_url'] }}</code>
                </div>
            </div>
        </div>
    </section>

    <section class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $commerceText('settings.setup_actions') }}</strong>
        </div>
        <div class="wb-card-body wb-cluster wb-cluster-2 wb-flex-wrap">
            <a href="{{ $pluginDetailUrl }}" class="wb-btn wb-btn-secondary">{{ $commerceText('settings.plugin_detail') }}</a>
            @if (auth()->user()?->isSuperAdmin() && ! $schemaReady)
                <form method="POST" action="{{ $pluginSetupUrl }}">
                    @csrf
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $commerceText('settings.run_plugin_migrations') }}</button>
                </form>
            @endif
        </div>
    </section>
@endsection
