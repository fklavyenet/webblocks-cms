@php
    $variables = $site->siteVariables->sortBy(fn ($siteVariable) => sprintf('%010d-%010d', (int) $siteVariable->sort_order, (int) $siteVariable->id))->values();
    $exampleToken = '{{ site.support_email }}';
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <strong>{{ $adminText('variables') }}</strong>
            <span class="wb-text-sm wb-text-muted">{!! $adminText('variables_help', ['token' => '<code>'.e($exampleToken).'</code>']) !!}</span>
        </div>

        @if ($site->exists && $canManageSiteSettings)
            <a href="{{ route('admin.sites.edit', ['site' => $site, 'tab' => 'variables', 'modal' => 'create-variable']) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">{{ $adminText('add_variable') }}</a>
        @endif
    </div>

    <div class="wb-card-body wb-stack wb-gap-4">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('variables_replacement_help') }}</div>

        @if (! $site->exists)
            <div class="wb-empty-state">
                <div class="wb-empty-title">{{ $adminText('save_site_first') }}</div>
                <div class="wb-empty-text">{{ $adminText('variables_existing_help') }}</div>
            </div>
        @elseif (! $canManageSiteSettings && $variables->isNotEmpty())
            <div class="wb-alert wb-alert-info">
                <div>
                    <div class="wb-alert-title">{{ $adminText('read_only') }}</div>
                    <div>{{ $adminText('variables_read_only_help') }}</div>
                </div>
            </div>
        @endif

        @if ($site->exists)
            @if ($variables->isEmpty())
                <div class="wb-empty-state">
                    <div class="wb-empty-title">{{ $adminText('no_site_variables') }}</div>
                    <div class="wb-empty-text">{{ $adminText('no_site_variables_help') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>{{ $adminText('label') }}</th>
                                <th>{{ $adminText('token') }}</th>
                                <th>{{ $adminText('value') }}</th>
                                <th>{{ $adminText('status') }}</th>
                                <th>{{ $adminText('sort') }}</th>
                                <th>{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($variables as $siteVariable)
                                @php($token = '{{ site.'.$siteVariable->key.' }}')
                                <tr>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $siteVariable->displayLabel() }}</strong>
                                            @if ($siteVariable->label)
                                                <span class="wb-text-sm wb-text-muted"><code>{{ $siteVariable->key }}</code></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td><code>{{ $token }}</code></td>
                                    <td>
                                        <span title="{{ $siteVariable->value }}">{{ str($siteVariable->value ?? '')->limit(80) }}</span>
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $siteVariable->is_enabled ? 'wb-status-active' : 'wb-status-pending' }}">{{ $siteVariable->is_enabled ? $adminText('enabled') : $adminText('disabled') }}</span>
                                    </td>
                                    <td>{{ $siteVariable->sort_order }}</td>
                                    <td>
                                        @if ($canManageSiteSettings)
                                            <div class="wb-action-group">
                                                <a href="{{ route('admin.sites.edit', ['site' => $site, 'tab' => 'variables', 'modal' => 'edit-variable', 'site_variable' => $siteVariable->id]) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('edit_variable') }}" aria-label="{{ $adminText('edit_variable') }}" aria-haspopup="dialog">
                                                    <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                                </a>

                                                <a href="{{ route('admin.sites.edit', ['site' => $site, 'tab' => 'variables', 'modal' => 'delete-variable', 'site_variable' => $siteVariable->id]) }}" class="wb-action-btn wb-action-btn-delete" title="{{ $adminText('delete_variable') }}" aria-label="{{ $adminText('delete_variable') }}" aria-haspopup="dialog">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        @else
                                            <span class="wb-text-sm wb-text-muted">{{ $adminText('read_only') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
