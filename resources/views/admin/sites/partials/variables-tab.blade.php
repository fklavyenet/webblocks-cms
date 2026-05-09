@php
    $variables = $site->siteVariables->sortBy(fn ($siteVariable) => sprintf('%010d-%010d', (int) $siteVariable->sort_order, (int) $siteVariable->id))->values();
    $exampleToken = '{{ site.support_email }}';
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <strong>Variables</strong>
            <span class="wb-text-sm wb-text-muted">Reusable public tokens like <code>{{ $exampleToken }}</code>. Admin forms keep raw token text unchanged.</span>
        </div>

        @if ($site->exists && $canManageSiteSettings)
            <a href="{{ route('admin.sites.edit', ['site' => $site, 'tab' => 'variables', 'modal' => 'create-variable']) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">Add Variable</a>
        @endif
    </div>

    <div class="wb-card-body wb-stack wb-gap-4">
        <div class="wb-text-sm wb-text-muted">Only enabled variables are replaced during public rendering and search indexing. Unknown or invalid tokens stay exactly as stored.</div>

        @if (! $site->exists)
            <div class="wb-empty-state">
                <div class="wb-empty-title">Save the site first.</div>
                <div class="wb-empty-text">Variables are attached to an existing site record.</div>
            </div>
        @elseif (! $canManageSiteSettings && $variables->isNotEmpty())
            <div class="wb-alert wb-alert-info">
                <div>
                    <div class="wb-alert-title">Read only</div>
                    <div>Variables are visible here, but only site admins and super admins can create, update, or delete them.</div>
                </div>
            </div>
        @endif

        @if ($site->exists)
            @if ($variables->isEmpty())
                <div class="wb-empty-state">
                    <div class="wb-empty-title">No site variables yet.</div>
                    <div class="wb-empty-text">Add reusable public text values for contact details, labels, or repeated brand copy.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Label</th>
                                <th>Token</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Sort</th>
                                <th>Actions</th>
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
                                        <span class="wb-status-pill {{ $siteVariable->is_enabled ? 'wb-status-active' : 'wb-status-pending' }}">{{ $siteVariable->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                                    </td>
                                    <td>{{ $siteVariable->sort_order }}</td>
                                    <td>
                                        @if ($canManageSiteSettings)
                                            <div class="wb-action-group">
                                                <a href="{{ route('admin.sites.edit', ['site' => $site, 'tab' => 'variables', 'modal' => 'edit-variable', 'site_variable' => $siteVariable->id]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit variable" aria-label="Edit variable" aria-haspopup="dialog">
                                                    <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                                </a>

                                                <a href="{{ route('admin.sites.edit', ['site' => $site, 'tab' => 'variables', 'modal' => 'delete-variable', 'site_variable' => $siteVariable->id]) }}" class="wb-action-btn wb-action-btn-delete" title="Delete variable" aria-label="Delete variable" aria-haspopup="dialog">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        @else
                                            <span class="wb-text-sm wb-text-muted">Read only</span>
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
