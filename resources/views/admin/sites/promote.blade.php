@extends('layouts.admin', ['title' => 'Site Promotion', 'heading' => 'Site Promotion'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Site Promotion',
        'description' => 'Promote site-owned content from a package into a target site while preserving install-level and live-runtime data.',
    ])

    @include('admin.partials.flash')

    @if ($errors->has('site_promotion'))
        <div class="wb-alert wb-alert-danger">{{ $errors->first('site_promotion') }}</div>
    @endif

    <div class="wb-stack wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header"><strong>Upload / Select Package</strong></div>
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.sites.promote.dry-run') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4">
                    @csrf

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-2">
                            <label for="archive">Upload promotion package</label>
                            <input id="archive" name="archive" class="wb-input" type="file" accept=".zip">
                        </div>

                        <div class="wb-stack wb-gap-2">
                            <label for="archive_path">Or select an uploaded package</label>
                            <select id="archive_path" name="archive_path" class="wb-select">
                                <option value="">Choose uploaded package</option>
                                @foreach ($storedPackages as $storedPackage)
                                    <option value="{{ $storedPackage }}" @selected(old('archive_path') === $storedPackage)>{{ $storedPackage }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Target &amp; Strategy</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-4">
                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-2">
                                    <label for="target_site_id">Target site</label>
                                    <select id="target_site_id" name="target_site_id" class="wb-select" required>
                                        <option value="">Choose target site</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}" @selected((int) old('target_site_id', $plan->targetSite['id'] ?? $preselectedTargetSiteId ?? 0) === $site->id)>
                                                {{ $site->name }} ({{ $site->handle }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="wb-stack wb-gap-2">
                                    <span>Strategy</span>
                                    <label class="wb-nowrap"><input type="radio" name="strategy" value="additive_update" @checked(old('strategy', $plan?->strategy() ?? 'additive_update') === 'additive_update')> <span>Additive update</span></label>
                                    <label class="wb-nowrap"><input type="radio" name="strategy" value="mirror" @checked(old('strategy', $plan?->strategy()) === 'mirror')> <span>Mirror</span></label>
                                    <div class="wb-alert wb-alert-warning wb-text-sm">Mirror archives absent target pages, deactivates absent Shared Slots, and removes absent site-owned rows where safe. Runtime and install data stay preserved.</div>
                                </div>
                            </div>

                            <label class="wb-checkbox" for="apply_assets">
                                <input id="apply_assets" type="checkbox" name="apply_assets" value="1" @checked(old('apply_assets', $plan?->applyAssets()))>
                                <span>Apply physical media and public `/site/...` files when present in the package</span>
                            </label>
                        </div>
                    </div>

                    <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                        <a href="{{ route('admin.sites.index') }}" class="wb-btn wb-btn-secondary">Back to Sites</a>
                        <button type="submit" class="wb-btn wb-btn-primary">Run Dry Run</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Dry Run Plan</strong></div>
            <div class="wb-card-body">
                @if (! $plan)
                    <div class="wb-text-sm wb-text-muted">Run a dry run to inspect package compatibility, preserved areas, and the planned content changes before apply.</div>
                @else
                    <div class="wb-stack wb-gap-4">
                        <div class="wb-grid wb-grid-2">
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Summary</strong></div>
                                <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                    <div><strong>Source:</strong> {{ $plan->sourceSite['name'] ?? '-' }} ({{ $plan->sourceSite['handle'] ?? '-' }})</div>
                                    <div><strong>Target:</strong> {{ $plan->targetSite['name'] ?? '-' }} ({{ $plan->targetSite['handle'] ?? '-' }})</div>
                                    <div><strong>Strategy:</strong> {{ str($plan->strategy())->replace('_', ' ')->title() }}</div>
                                    <div><strong>Apply assets:</strong> {{ $plan->applyAssets() ? 'Yes' : 'No' }}</div>
                                    <div><strong>Dry run token:</strong> <code>{{ $plan->token }}</code></div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Locales</strong></div>
                                <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                    <div><strong>Compatible:</strong> {{ implode(', ', $plan->localeSummary['compatible'] ?? []) ?: 'None' }}</div>
                                    <div><strong>Missing:</strong> {{ implode(', ', $plan->localeSummary['missing'] ?? []) ?: 'None' }}</div>
                                    <div><strong>Behavior:</strong> {{ ! empty($plan->localeSummary['will_create_missing']) ? 'Missing locales will be created during apply.' : 'No locale creation required.' }}</div>
                                </div>
                            </div>
                        </div>

                        @if ($plan->errors !== [])
                            <div class="wb-alert wb-alert-danger">
                                <div class="wb-alert-title">Blocking Validation Errors</div>
                                <ul class="wb-list-unstyled wb-stack wb-gap-1 wb-mt-2">
                                    @foreach ($plan->errors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($plan->warnings !== [])
                            <div class="wb-alert wb-alert-warning">
                                <div class="wb-alert-title">Warnings</div>
                                <ul class="wb-list-unstyled wb-stack wb-gap-1 wb-mt-2">
                                    @foreach ($plan->warnings as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="wb-grid wb-grid-2">
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Pages</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>Create: {{ count($plan->operations['pages']['create'] ?? []) }}</div>
                                    <div>Update: {{ count($plan->operations['pages']['update'] ?? []) }}</div>
                                    <div>Archive: {{ count($plan->operations['pages']['archive'] ?? []) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Shared Slots</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>Create: {{ count($plan->operations['shared_slots']['create'] ?? []) }}</div>
                                    <div>Update: {{ count($plan->operations['shared_slots']['update'] ?? []) }}</div>
                                    <div>Deactivate: {{ count($plan->operations['shared_slots']['deactivate'] ?? []) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Navigation</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>Create: {{ count($plan->operations['navigation']['create'] ?? []) }}</div>
                                    <div>Update: {{ count($plan->operations['navigation']['update'] ?? []) }}</div>
                                    <div>Remove: {{ count($plan->operations['navigation']['remove'] ?? []) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Site Variables</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>Create: {{ count($plan->operations['site_variables']['create'] ?? []) }}</div>
                                    <div>Update: {{ count($plan->operations['site_variables']['update'] ?? []) }}</div>
                                    <div>Remove: {{ count($plan->operations['site_variables']['remove'] ?? []) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Page Assets</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>Create: {{ count($plan->operations['page_assets']['create'] ?? []) }}</div>
                                    <div>Update: {{ count($plan->operations['page_assets']['update'] ?? []) }}</div>
                                    <div>Remove: {{ count($plan->operations['page_assets']['remove'] ?? []) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>Media / Public Files</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>Files add: {{ $plan->operations['media']['asset_files_to_add'] ?? 0 }}</div>
                                    <div>Files overwrite: {{ $plan->operations['media']['asset_files_to_overwrite'] ?? 0 }}</div>
                                    <div>Page asset files add: {{ $plan->operations['media']['page_asset_files_to_add'] ?? 0 }}</div>
                                    <div>Page asset files overwrite: {{ $plan->operations['media']['page_asset_files_to_overwrite'] ?? 0 }}</div>
                                    <div>Files skipped: {{ $plan->operations['media']['files_skipped'] ?? 0 }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header"><strong>Preserved Areas</strong></div>
                            <div class="wb-card-body">
                                <ul class="wb-list-unstyled wb-stack wb-gap-1 wb-text-sm">
                                    @foreach ($plan->preservedAreas as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Apply Promotion</strong></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-text-sm wb-text-muted">Apply is only available after a successful dry run plan for the same package and options. A safety backup is created before content changes, and the target site search index is rebuilt after apply.</div>

                <form method="POST" action="{{ route('admin.sites.promote.apply') }}">
                    @csrf
                    <input type="hidden" name="plan_token" value="{{ $plan?->token }}">
                    <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $plan?->canApply())>Apply Promotion</button>
                </form>
            </div>
        </div>
    </div>
@endsection
