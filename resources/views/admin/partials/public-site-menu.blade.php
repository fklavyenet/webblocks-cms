@if (count($adminPublicSiteChoices) === 1)
    @php
        $adminPublicSite = $adminPublicSiteChoices[0];
        $openSiteLabel = $adminText('sites.open_home_new_tab', ['name' => $adminPublicSite['name']]);
    @endphp
    <a href="{{ $adminPublicSite['url'] }}" target="_blank" rel="noopener noreferrer"
        class="wb-btn wb-btn-ghost wb-theme-switcher-trigger" data-admin-public-site
        aria-label="{{ $openSiteLabel }}" title="{{ $openSiteLabel }}">
        <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
    </a>
@elseif ($adminPublicSiteChoices !== [])
    <div class="wb-dropdown wb-dropdown-end">
        <button type="button" class="wb-btn wb-btn-ghost wb-theme-switcher-trigger" data-wb-toggle="dropdown"
            data-wb-target="#admin-public-sites" aria-controls="admin-public-sites" aria-expanded="false"
            aria-label="{{ $adminText('topbar.view_site') }}" title="{{ $adminText('topbar.view_site') }}">
            <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
            <i class="wb-icon wb-icon-chevron-down wb-theme-switcher-chevron" aria-hidden="true"></i>
        </button>

        <div id="admin-public-sites" class="wb-dropdown-menu" popover="manual">
            <div class="wb-dropdown-label">{{ $adminText('topbar.view_site') }}</div>
            @foreach ($adminPublicSiteChoices as $publicSite)
                <a class="wb-dropdown-item" href="{{ $publicSite['url'] }}" target="_blank" rel="noopener noreferrer"
                    aria-label="{{ $adminText('sites.open_home_new_tab', ['name' => $publicSite['name']]) }}">
                    {{ $publicSite['name'] }}
                </a>
            @endforeach
        </div>
    </div>
@endif
