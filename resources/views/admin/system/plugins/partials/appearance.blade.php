@php
    $appearanceLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $appearanceText = static fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('plugin_appearance.'.$key, $appearanceLocale);
@endphp
<div class="wb-card" id="plugin-menu-appearance">
    <div class="wb-card-header"><strong>{{ $appearanceText('title') }}</strong></div>
    <form method="POST" action="{{ route('admin.system.plugins.appearance.update', $pluginAppearanceCard['handle']) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="origin" value="{{ $pluginAppearanceCard['origin'] }}">
        @if ($pluginAppearanceCard['site_id'])
            <input type="hidden" name="site_id" value="{{ $pluginAppearanceCard['site_id'] }}">
        @endif
        <div class="wb-card-body wb-stack wb-gap-3">
            <p class="wb-text-sm wb-text-muted">{{ $appearanceText('help') }}</p>
            <div class="wb-cluster wb-cluster-2">
                <i class="wb-icon {{ $pluginAppearanceCard['icon_class'] }}" aria-hidden="true"></i>
                <span>{{ $appearanceText('current_icon') }}</span>
            </div>
            @include('webblocks-cms::admin.blocks.partials.icon-picker-field', [
                'slugName' => 'sidebar_icon',
                'slug' => $errors->getBag('pluginAppearance')->any() ? old('sidebar_icon') : $pluginAppearanceCard['slug'],
                'label' => $appearanceText('sidebar_icon'),
                'context' => 'navigation',
                'toneName' => null,
                'sizeName' => null,
                'badgeToneName' => null,
            ])
            <p class="wb-text-sm wb-text-muted">{{ $appearanceText('reset_help') }}</p>
            @if ($errors->getBag('pluginAppearance')->any())
                <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->getBag('pluginAppearance')->first() }}</div>
            @endif
            @if (session('plugin_appearance_status'))
                <div class="wb-alert wb-alert-success" role="status">{{ session('plugin_appearance_status') }}</div>
            @endif
        </div>
        <div class="wb-card-footer"><button type="submit" class="wb-btn wb-btn-primary">{{ $appearanceText('save') }}</button></div>
    </form>
</div>
