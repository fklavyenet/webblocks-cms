@if (! empty($adminQuickSettings))
    <div class="wb-dropdown wb-dropdown-end">
        <button class="wb-btn wb-btn-ghost wb-btn-icon" type="button" data-wb-toggle="dropdown" data-wb-target="#admin-quick-settings" aria-controls="admin-quick-settings" aria-expanded="false" aria-label="{{ $adminText('topbar.quick_settings') }}" title="{{ $adminText('topbar.quick_settings') }}">
            <i class="wb-icon wb-icon-settings" aria-hidden="true"></i>
        </button>
        <div class="wb-dropdown-menu" id="admin-quick-settings">
            <div class="wb-dropdown-label">{{ $adminText('topbar.quick_settings') }}</div>
            @foreach ($adminQuickSettings as $quickSetting)
                <div class="wb-cms-quick-setting" data-plugin-quick-setting="{{ $quickSetting->key() }}">
                    @if ($quickSetting->viewName() !== null)
                        @include($quickSetting->viewName(), $quickSetting->viewData())
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
