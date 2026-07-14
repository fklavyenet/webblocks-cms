@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $commerceText = fn (string $key, array $replace = [], ?string $fallback = null): string => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
        ->plugin('webblocks-commerce', 'admin.'.$key, $adminLocale, $replace, $fallback);
    $gatewayStatusClass = $checkoutReady ? 'wb-status-active' : 'wb-status-danger';
    $schemaStatusClass = $schemaReady ? 'wb-status-active' : 'wb-status-pending';
    $configured = fn (bool $ready): string => $ready ? $commerceText('settings.configured') : $commerceText('settings.missing');
    $statusClass = fn (bool $ready): string => $ready ? 'wb-status-active' : 'wb-status-danger';
    $sourceLabel = fn (string $source): string => match ($source) {
        'environment' => $commerceText('settings.managed_by_environment'),
        'stored' => $commerceText('settings.encrypted_storage'),
        'default' => $commerceText('settings.default_value'),
        default => $commerceText('settings.missing'),
    };
    $fieldPlaceholder = fn (array $state): string => $state['environment_managed']
        ? $commerceText('settings.managed_by_environment')
        : ($state['stored'] ? $commerceText('settings.leave_blank_to_keep') : $commerceText('settings.enter_new_value'));
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

    <div class="wb-settings-shell wb-stack wb-gap-4">
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
                        <span class="wb-status-pill wb-status-pending">{{ $sourceLabel($gateway_source) }}</span>
                        <span class="wb-text-sm wb-text-muted">{{ $checkoutMessage }}</span>
                    </div>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ $settingsUpdateUrl }}" class="wb-stack wb-gap-4">
            @csrf
            @method('PUT')

            <fieldset class="wb-stack wb-gap-4" @disabled(! $schemaReady)>
                <section class="wb-card">
                    <div class="wb-card-header">
                        <div class="wb-stack wb-stack-1">
                            <strong>{{ $commerceText('settings.provider_configuration') }}</strong>
                            <span class="wb-text-sm wb-text-muted">{{ $commerceText('settings.provider_help') }}</span>
                        </div>
                    </div>
                    <div class="wb-card-body wb-grid wb-grid-3 wb-gap-4">
                        <div class="wb-stack-2 wb-field">
                            <label for="commerce_gateway" class="wb-label">{{ $commerceText('settings.gateway') }}</label>
                            @if ($gateway_source === 'environment')
                                <input type="hidden" name="gateway" value="{{ $gateway }}">
                            @endif
                            <select id="commerce_gateway" name="gateway" class="wb-select" @disabled($gateway_source === 'environment')>
                                <option value="paypal" @selected(old('gateway', $gateway) === 'paypal')>PayPal</option>
                                <option value="sumup" @selected(old('gateway', $gateway) === 'sumup')>SumUp</option>
                            </select>
                            <span class="wb-text-sm wb-text-muted">{{ $sourceLabel($gateway_source) }} · <code>WEBBLOCKS_COMMERCE_GATEWAY</code></span>
                            @error('gateway')<div class="wb-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="wb-stack-2 wb-field">
                            <label for="commerce_default_currency" class="wb-label">{{ $commerceText('settings.default_currency') }}</label>
                            @if ($defaultCurrencySource === 'environment')
                                <input type="hidden" name="default_currency" value="{{ $defaultCurrency }}">
                            @endif
                            <select id="commerce_default_currency" name="default_currency" class="wb-select" @disabled($defaultCurrencySource === 'environment')>
                                @foreach ($currencyOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('default_currency', $defaultCurrency) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="wb-text-sm wb-text-muted">{{ $commerceText('settings.default_currency_help') }} · {{ $sourceLabel($defaultCurrencySource) }} · <code>WEBBLOCKS_COMMERCE_DEFAULT_CURRENCY</code></span>
                            @error('default_currency')<div class="wb-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="wb-stack-2 wb-field">
                            <label for="commerce_paypal_mode" class="wb-label">{{ $commerceText('settings.paypal_mode') }}</label>
                            @if ($paypal['mode_source'] === 'environment')
                                <input type="hidden" name="paypal_mode" value="{{ $paypal['mode'] }}">
                            @endif
                            <select id="commerce_paypal_mode" name="paypal_mode" class="wb-select" @disabled($paypal['mode_source'] === 'environment')>
                                <option value="sandbox" @selected(old('paypal_mode', $paypal['mode']) === 'sandbox')>{{ $commerceText('settings.sandbox') }}</option>
                                <option value="live" @selected(old('paypal_mode', $paypal['mode']) === 'live')>{{ $commerceText('settings.live') }}</option>
                            </select>
                            <span class="wb-text-sm wb-text-muted">{{ $sourceLabel($paypal['mode_source']) }} · <code>WEBBLOCKS_COMMERCE_PAYPAL_MODE</code></span>
                            @error('paypal_mode')<div class="wb-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="wb-stack-2 wb-field">
                            <label for="commerce_sumup_mode" class="wb-label">{{ $commerceText('settings.sumup_mode') }}</label>
                            @if ($sumup['mode_source'] === 'environment')
                                <input type="hidden" name="sumup_mode" value="{{ $sumup['mode'] }}">
                            @endif
                            <select id="commerce_sumup_mode" name="sumup_mode" class="wb-select" @disabled($sumup['mode_source'] === 'environment')>
                                <option value="sandbox" @selected(old('sumup_mode', $sumup['mode']) === 'sandbox')>{{ $commerceText('settings.sandbox') }}</option>
                                <option value="live" @selected(old('sumup_mode', $sumup['mode']) === 'live')>{{ $commerceText('settings.live') }}</option>
                            </select>
                            <span class="wb-text-sm wb-text-muted">{{ $sourceLabel($sumup['mode_source']) }} · <code>WEBBLOCKS_COMMERCE_SUMUP_MODE</code></span>
                            @error('sumup_mode')<div class="wb-field-error">{{ $message }}</div>@enderror
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
                    <div class="wb-card-body wb-grid wb-grid-2 wb-gap-4">
                        @foreach ([
                            ['field' => 'paypal_client_id', 'clear' => 'clear_paypal_client_id', 'label' => 'client_id', 'state' => $paypal['client_id'], 'env' => 'WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID'],
                            ['field' => 'paypal_client_secret', 'clear' => 'clear_paypal_client_secret', 'label' => 'client_secret', 'state' => $paypal['client_secret'], 'env' => 'WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_SECRET'],
                            ['field' => 'paypal_webhook_id', 'clear' => 'clear_paypal_webhook_id', 'label' => 'webhook_id', 'state' => $paypal['webhook_id'], 'env' => 'WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID'],
                        ] as $credential)
                            <div class="wb-stack-2 wb-field">
                                <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                                    <label for="commerce_{{ $credential['field'] }}" class="wb-label">{{ $commerceText('settings.'.$credential['label']) }}</label>
                                    <span class="wb-status-pill {{ $statusClass($credential['state']['configured']) }}">{{ $configured($credential['state']['configured']) }}</span>
                                </div>
                                <input
                                    id="commerce_{{ $credential['field'] }}"
                                    name="{{ $credential['field'] }}"
                                    class="wb-input"
                                    type="password"
                                    value=""
                                    autocomplete="new-password"
                                    spellcheck="false"
                                    placeholder="{{ $fieldPlaceholder($credential['state']) }}"
                                    @disabled($credential['state']['environment_managed'])
                                >
                                <span class="wb-text-sm wb-text-muted">{{ $sourceLabel($credential['state']['source']) }} · <code>{{ $credential['env'] }}</code></span>
                                @if ($credential['state']['stored'] && ! $credential['state']['environment_managed'])
                                    <label class="wb-check">
                                        <input type="checkbox" name="{{ $credential['clear'] }}" value="1" @checked(old($credential['clear']))>
                                        <span>{{ $commerceText('settings.clear_saved_value') }}</span>
                                    </label>
                                @endif
                                @error($credential['field'])<div class="wb-field-error">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                        <div class="wb-stack-2 wb-field">
                            <span class="wb-label">{{ $commerceText('settings.webhook_url') }}</span>
                            <code>{{ $paypal['webhook_url'] }}</code>
                            <span class="wb-text-sm wb-text-muted">{{ $commerceText('settings.webhook_url_help') }}</span>
                        </div>
                    </div>
                </section>

                <section class="wb-card">
                    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                        <div class="wb-stack wb-stack-1">
                            <strong>{{ $commerceText('settings.sumup_configuration') }}</strong>
                            <span class="wb-text-sm wb-text-muted">{{ $commerceText('settings.sumup_help') }}</span>
                        </div>
                        <span class="wb-status-pill {{ $statusClass($sumup['webhook_ready']) }}">{{ $sumup['webhook_ready'] ? $commerceText('settings.webhook_ready') : $commerceText('settings.needs_config') }}</span>
                    </div>
                    <div class="wb-card-body wb-grid wb-grid-2 wb-gap-4">
                        @foreach ([
                            ['field' => 'sumup_api_key', 'clear' => 'clear_sumup_api_key', 'label' => 'api_key', 'state' => $sumup['api_key'], 'env' => 'WEBBLOCKS_COMMERCE_SUMUP_API_KEY'],
                            ['field' => 'sumup_merchant_code', 'clear' => 'clear_sumup_merchant_code', 'label' => 'merchant_code', 'state' => $sumup['merchant_code'], 'env' => 'WEBBLOCKS_COMMERCE_SUMUP_MERCHANT_CODE'],
                        ] as $credential)
                            <div class="wb-stack-2 wb-field">
                                <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                                    <label for="commerce_{{ $credential['field'] }}" class="wb-label">{{ $commerceText('settings.'.$credential['label']) }}</label>
                                    <span class="wb-status-pill {{ $statusClass($credential['state']['configured']) }}">{{ $configured($credential['state']['configured']) }}</span>
                                </div>
                                <input
                                    id="commerce_{{ $credential['field'] }}"
                                    name="{{ $credential['field'] }}"
                                    class="wb-input"
                                    type="password"
                                    value=""
                                    autocomplete="new-password"
                                    spellcheck="false"
                                    placeholder="{{ $fieldPlaceholder($credential['state']) }}"
                                    @disabled($credential['state']['environment_managed'])
                                >
                                <span class="wb-text-sm wb-text-muted">{{ $sourceLabel($credential['state']['source']) }} · <code>{{ $credential['env'] }}</code></span>
                                @if ($credential['state']['stored'] && ! $credential['state']['environment_managed'])
                                    <label class="wb-check">
                                        <input type="checkbox" name="{{ $credential['clear'] }}" value="1" @checked(old($credential['clear']))>
                                        <span>{{ $commerceText('settings.clear_saved_value') }}</span>
                                    </label>
                                @endif
                                @error($credential['field'])<div class="wb-field-error">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                        <div class="wb-stack-2 wb-field">
                            <span class="wb-label">{{ $commerceText('settings.webhook_url') }}</span>
                            <code>{{ $sumup['webhook_url'] }}</code>
                            <span class="wb-text-sm wb-text-muted">{{ $commerceText('settings.webhook_url_help') }}</span>
                        </div>
                    </div>
                </section>

                <section class="wb-card">
                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                        <span class="wb-text-sm wb-text-muted">{{ $commerceText('settings.secret_storage_help') }}</span>
                        <button type="submit" class="wb-btn wb-btn-primary">{{ $commerceText('settings.save') }}</button>
                    </div>
                </section>
            </fieldset>
        </form>

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
    </div>
@endsection
