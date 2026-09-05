@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.sites.promote.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('title'),
        'description' => $adminText('description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if ($errors->has('site_promotion'))
        <div class="wb-alert wb-alert-danger">{{ $errors->first('site_promotion') }}</div>
    @endif

    <div class="wb-stack wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('upload_select_package') }}</strong></div>
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.sites.promote.dry-run') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4">
                    @csrf

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-2">
                            <label for="archive">{{ $adminText('upload_promotion_package') }}</label>
                            <input id="archive" name="archive" class="wb-file" type="file" accept=".zip">
                        </div>

                        <div class="wb-stack wb-gap-2">
                            <label for="archive_path">{{ $adminText('or_select_uploaded_package') }}</label>
                            <select id="archive_path" name="archive_path" class="wb-select">
                                <option value="">{{ $adminText('choose_uploaded_package') }}</option>
                                @foreach ($storedPackages as $storedPackage)
                                    <option value="{{ $storedPackage }}" @selected(old('archive_path') === $storedPackage)>{{ $storedPackage }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>{{ $adminText('target_strategy') }}</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-4">
                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-2">
                                    <label for="target_site_id">{{ $adminText('target_site') }}</label>
                                    <select id="target_site_id" name="target_site_id" class="wb-select" required>
                                        <option value="">{{ $adminText('choose_target_site') }}</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}" @selected((int) old('target_site_id', $preselectedTargetSiteId ?? ($plan->targetSite['id'] ?? 0)) === $site->id)>
                                                {{ $site->name }} ({{ $site->handle }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="wb-stack wb-gap-2">
                                    <span>{{ $adminText('strategy') }}</span>
                                    <label class="wb-nowrap"><input type="radio" name="strategy" value="additive_update" @checked(old('strategy', $plan?->strategy() ?? 'additive_update') === 'additive_update')> <span>{{ $adminText('additive_update') }}</span></label>
                                    <label class="wb-nowrap"><input type="radio" name="strategy" value="mirror" @checked(old('strategy', $plan?->strategy()) === 'mirror')> <span>{{ $adminText('mirror') }}</span></label>
                                    <div class="wb-alert wb-alert-warning wb-text-sm">{{ $adminText('mirror_help') }}</div>
                                </div>
                            </div>

                            <label class="wb-check" for="apply_assets">
                                <input id="apply_assets" type="checkbox" name="apply_assets" value="1" @checked(old('apply_assets', $plan?->applyAssets()))>
                                <span>{{ $adminText('apply_assets_help') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                        <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('run_dry_run') }}</button>
                        <a href="{{ route('admin.sites.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('dry_run_plan') }}</strong></div>
            <div class="wb-card-body">
                @if (! $plan)
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('dry_run_empty') }}</div>
                @else
                    <div class="wb-stack wb-gap-4">
                        <div class="wb-grid wb-grid-2">
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>{{ $adminText('summary') }}</strong></div>
                                <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                    <div><strong>{{ $adminText('source_label') }}</strong> {{ $plan->sourceSite['name'] ?? '-' }} ({{ $plan->sourceSite['handle'] ?? '-' }})</div>
                                    <div><strong>{{ $adminText('target_label') }}</strong> {{ $plan->targetSite['name'] ?? '-' }} ({{ $plan->targetSite['handle'] ?? '-' }})</div>
                                    <div><strong>{{ $adminText('strategy_label') }}</strong> {{ str($plan->strategy())->replace('_', ' ')->title() }}</div>
                                    <div><strong>{{ $adminText('apply_assets_label') }}</strong> {{ $plan->applyAssets() ? $adminText('yes') : $adminText('no') }}</div>
                                    <div><strong>{{ $adminText('dry_run_token_label') }}</strong> <code>{{ $plan->token }}</code></div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>{{ $adminText('locales') }}</strong></div>
                                <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                    <div><strong>{{ $adminText('compatible_label') }}</strong> {{ implode(', ', $plan->localeSummary['compatible'] ?? []) ?: $adminText('none') }}</div>
                                    <div><strong>{{ $adminText('missing_label') }}</strong> {{ implode(', ', $plan->localeSummary['missing'] ?? []) ?: $adminText('none') }}</div>
                                    <div><strong>{{ $adminText('behavior_label') }}</strong> {{ ! empty($plan->localeSummary['will_create_missing']) ? $adminText('missing_locales_created') : $adminText('no_locale_creation') }}</div>
                                </div>
                            </div>
                        </div>

                        @if ($plan->errors !== [])
                            <div class="wb-alert wb-alert-danger">
                                <div class="wb-alert-title">{{ $adminText('blocking_validation_errors') }}</div>
                                <ul class="wb-list-unstyled wb-stack wb-gap-1 wb-mt-2">
                                    @foreach ($plan->errors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($plan->warnings !== [])
                            <div class="wb-alert wb-alert-warning">
                                <div class="wb-alert-title">{{ $adminText('warnings') }}</div>
                                <ul class="wb-list-unstyled wb-stack wb-gap-1 wb-mt-2">
                                    @foreach ($plan->warnings as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="wb-grid wb-grid-2">
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>{{ $adminText('pages') }}</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>{{ $adminText('create_count', ['count' => count($plan->operations['pages']['create'] ?? [])]) }}</div>
                                    <div>{{ $adminText('update_count', ['count' => count($plan->operations['pages']['update'] ?? [])]) }}</div>
                                    <div>{{ $adminText('archive_count', ['count' => count($plan->operations['pages']['archive'] ?? [])]) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>{{ $adminText('shared_slots') }}</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>{{ $adminText('create_count', ['count' => count($plan->operations['shared_slots']['create'] ?? [])]) }}</div>
                                    <div>{{ $adminText('update_count', ['count' => count($plan->operations['shared_slots']['update'] ?? [])]) }}</div>
                                    <div>{{ $adminText('deactivate_count', ['count' => count($plan->operations['shared_slots']['deactivate'] ?? [])]) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>{{ $adminText('navigation') }}</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>{{ $adminText('create_count', ['count' => count($plan->operations['navigation']['create'] ?? [])]) }}</div>
                                    <div>{{ $adminText('update_count', ['count' => count($plan->operations['navigation']['update'] ?? [])]) }}</div>
                                    <div>{{ $adminText('remove_count', ['count' => count($plan->operations['navigation']['remove'] ?? [])]) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>{{ $adminText('site_variables') }}</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>{{ $adminText('create_count', ['count' => count($plan->operations['site_variables']['create'] ?? [])]) }}</div>
                                    <div>{{ $adminText('update_count', ['count' => count($plan->operations['site_variables']['update'] ?? [])]) }}</div>
                                    <div>{{ $adminText('remove_count', ['count' => count($plan->operations['site_variables']['remove'] ?? [])]) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>{{ $adminText('page_assets') }}</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>{{ $adminText('create_count', ['count' => count($plan->operations['page_assets']['create'] ?? [])]) }}</div>
                                    <div>{{ $adminText('update_count', ['count' => count($plan->operations['page_assets']['update'] ?? [])]) }}</div>
                                    <div>{{ $adminText('remove_count', ['count' => count($plan->operations['page_assets']['remove'] ?? [])]) }}</div>
                                </div>
                            </div>

                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-header"><strong>{{ $adminText('media_public_files') }}</strong></div>
                                <div class="wb-card-body wb-text-sm wb-stack wb-gap-2">
                                    <div>{{ $adminText('files_add_count', ['count' => $plan->operations['media']['asset_files_to_add'] ?? 0]) }}</div>
                                    <div>{{ $adminText('files_overwrite_count', ['count' => $plan->operations['media']['asset_files_to_overwrite'] ?? 0]) }}</div>
                                    <div>{{ $adminText('page_asset_files_add_count', ['count' => $plan->operations['media']['page_asset_files_to_add'] ?? 0]) }}</div>
                                    <div>{{ $adminText('page_asset_files_overwrite_count', ['count' => $plan->operations['media']['page_asset_files_to_overwrite'] ?? 0]) }}</div>
                                    <div>{{ $adminText('files_skipped_count', ['count' => $plan->operations['media']['files_skipped'] ?? 0]) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header"><strong>{{ $adminText('preserved_areas') }}</strong></div>
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
            <div class="wb-card-header"><strong>{{ $adminText('apply_promotion') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-text-sm wb-text-muted">{{ $adminText('apply_help') }}</div>

                <form method="POST" action="{{ route('admin.sites.promote.apply') }}">
                    @csrf
                    <input type="hidden" name="plan_token" value="{{ $plan?->token }}">
                    <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                        <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $plan?->canApply())>{{ $adminText('apply_promotion') }}</button>
                        <a href="{{ route('admin.sites.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
