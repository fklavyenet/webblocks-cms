@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $pageDuplicateLocale = app(AdminLocaleResolver::class)->locale();
    $pageDuplicateTranslator = app(CmsTranslator::class);
    $pageDuplicateText = static fn (string $key, array $replace = []) => $pageDuplicateTranslator->admin('page_duplicate.'.$key, $pageDuplicateLocale, $replace);
    $pageTitle = $pageDuplicateText('title');
    $siteName = $page->site?->name ?? $pageDuplicateText('site');
    $backUrl = route('admin.pages.edit', $page);
    $defaultTitle = old('title', trim(($defaultTranslation?->name ?? $page->title ?? $pageDuplicateText('page')).' '.$pageDuplicateText('copy_suffix')));
    $defaultSlug = old('slug', trim(($defaultTranslation?->slug ?? $page->slug ?? 'page').'-copy', '-'));
    $sharedSlotCompatibility = $sharedSlotValidation?->sharedSlotCompatibility ?? collect();
    $sharedSlotIssues = $sharedSlotCompatibility->whereIn('status', ['missing_source', 'missing_target', 'incompatible_target'])->values();
    $sharedSlotCompatible = $sharedSlotCompatibility->whereIn('status', ['same_site', 'compatible_target'])->values();
    $canDisableIncompatibleSharedSlots = ($sharedSlotValidation?->disableEligibleSharedSlotIds->isNotEmpty() ?? false) && $selectedTargetSite && (int) $selectedTargetSite->id !== (int) $page->site_id;
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $pageDuplicateText('description'),
        'actions' => '<a href="'.$backUrl.'" class="wb-btn wb-btn-secondary">'.$pageDuplicateText('back_to_page').'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $page->title }}</strong>
                    <div class="wb-text-sm wb-text-muted">{{ $pageDuplicateText('source_site', ['site' => $siteName]) }}</div>
                </div>

                <div class="wb-stack wb-gap-2 wb-text-sm">
                    <div><strong>{{ $pageDuplicateText('workflow') }}</strong> {{ $page->workflowLabel() }}</div>
                    <div><strong>{{ $pageDuplicateText('translations') }}</strong> {{ $page->translations->pluck('locale.code')->filter()->implode(', ') ?: $pageDuplicateText('none') }}</div>
                    <div><strong>{{ $pageDuplicateText('revision_history') }}</strong> {{ $pageDuplicateText('revision_history_help') }}</div>
                    <div><strong>{{ $pageDuplicateText('navigation') }}</strong> {{ $pageDuplicateText('navigation_help') }}</div>
                </div>

                <div class="wb-alert wb-alert-warning">
                    <div>
                        <div class="wb-alert-title">{{ $pageDuplicateText('warnings') }}</div>
                        <div>{{ $pageDuplicateText('draft_warning') }}</div>
                        @if ($sharedSlotHandles->isNotEmpty())
                            <div class="wb-text-sm wb-mt-2">{{ $pageDuplicateText('shared_slots_warning', ['handles' => $sharedSlotHandles->implode(', ')]) }}</div>
                        @endif
                    </div>
                </div>

                @if ($sharedSlotCompatibility->isNotEmpty() && $selectedTargetSite)
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-3 wb-text-sm">
                            <div>
                                <strong>{{ $pageDuplicateText('shared_slot_compatibility') }}</strong>
                                <div class="wb-text-muted">{{ $pageDuplicateText('target_site_help', ['site' => $selectedTargetSite->name]) }}</div>
                            </div>

                            @if ($sharedSlotCompatible->isNotEmpty())
                                <div class="wb-alert wb-alert-info">
                                    <div>
                                        <div class="wb-alert-title">{{ $pageDuplicateText('compatible_shared_slots') }}</div>
                                        <div>{{ $pageDuplicateText('compatible_count', ['count' => $sharedSlotCompatible->count()]) }}</div>
                                    </div>
                                </div>
                            @endif

                            @if ($sharedSlotIssues->isNotEmpty())
                                <div class="wb-alert wb-alert-warning">
                                    <div>
                                        <div class="wb-alert-title">{{ $pageDuplicateText('requires_attention') }}</div>
                                        <div>{{ $pageDuplicateText('missing_compatible_slots', ['count' => $sharedSlotIssues->count()]) }}</div>
                                    </div>
                                </div>

                                <div class="wb-stack wb-gap-2">
                                    @foreach ($sharedSlotIssues as $sharedSlotIssue)
                                        <div class="wb-card wb-card-muted">
                                            <div class="wb-card-body wb-stack wb-gap-1">
                                                <div><strong>{{ $pageDuplicateText('slot') }}</strong> {{ $sharedSlotIssue['slot_name'] ?: $pageDuplicateText('unknown') }}</div>
                                                <div><strong>{{ $pageDuplicateText('shared_slot') }}</strong> {{ $sharedSlotIssue['shared_slot_handle'] ?? $pageDuplicateText('missing_source_shared_slot') }}</div>
                                                <div class="wb-text-muted">{{ $sharedSlotIssue['message'] }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="wb-alert wb-alert-info">
                                    <div>{{ $pageDuplicateText('all_shared_slots_compatible') }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.pages.duplicate.store', $page) }}" class="wb-stack wb-gap-4">
                    @csrf

                     <div class="wb-field wb-stack-2">
                         <label for="target_site_id">{{ $pageDuplicateText('target_site') }}</label>
                         <select id="target_site_id" name="target_site_id" class="wb-select" required>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected((int) old('target_site_id', $page->site_id) === (int) $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                        @error('target_site_id')
                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                         @enderror
                     </div>

                    @if ($canDisableIncompatibleSharedSlots)
                        <div class="wb-field wb-stack-2">
                            <label class="wb-check" for="disable_incompatible_shared_slots">
                                <input type="hidden" name="disable_incompatible_shared_slots" value="0">
                                <input id="disable_incompatible_shared_slots" type="checkbox" name="disable_incompatible_shared_slots" value="1" @checked(old('disable_incompatible_shared_slots'))>
                                <span>{{ $pageDuplicateText('disable_incompatible_shared_slots') }}</span>
                            </label>
                            <div class="wb-text-sm wb-text-muted">{{ $pageDuplicateText('disable_incompatible_help') }}</div>
                        </div>
                    @endif

                     <div class="wb-grid wb-grid-2">
                        <div class="wb-field wb-stack-2">
                            <label for="title">{{ $pageDuplicateText('new_page_title') }}</label>
                            <input id="title" name="title" class="wb-input" type="text" value="{{ $defaultTitle }}" required>
                            @error('title')
                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field wb-stack-2">
                            <label for="slug">{{ $pageDuplicateText('new_page_slug') }}</label>
                            <input id="slug" name="slug" class="wb-input" type="text" value="{{ $defaultSlug }}" required>
                            <div class="wb-text-sm wb-text-muted">{{ $pageDuplicateText('source_path', ['path' => $defaultTranslation?->path ?? $pageDuplicateText('missing')]) }}</div>
                            @error('slug')
                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @error('translations')
                        <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                    @enderror

                    @if ($secondaryTranslations->isNotEmpty())
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                                <strong>{{ $pageDuplicateText('additional_translations') }}</strong>
                                <span class="wb-text-sm wb-text-muted">{{ $pageDuplicateText('additional_translations_help') }}</span>
                            </div>
                            <div class="wb-card-body wb-stack wb-gap-4">
                                @foreach ($secondaryTranslations as $index => $translation)
                                    @php
                                        $oldTranslation = old('translations.'.$index, []);
                                        $suggestedName = trim(($translation->name ?? strtoupper($translation->locale?->code ?? $pageDuplicateText('locale'))).' '.$pageDuplicateText('copy_suffix'));
                                        $suggestedSlug = trim(($translation->slug ?? 'page').'-copy', '-');
                                    @endphp
                                    <input type="hidden" name="translations[{{ $index }}][locale_id]" value="{{ $translation->locale_id }}">
                                    <div class="wb-grid wb-grid-2">
                                        <div class="wb-field wb-stack-2">
                                            <label for="translation-name-{{ $translation->locale_id }}">{{ $pageDuplicateText('locale_title', ['locale' => strtoupper($translation->locale?->code ?? (string) $translation->locale_id)]) }}</label>
                                            <input id="translation-name-{{ $translation->locale_id }}" name="translations[{{ $index }}][name]" class="wb-input" type="text" value="{{ $oldTranslation['name'] ?? $suggestedName }}" required>
                                            @error('translations.'.$index.'.name')
                                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="wb-field wb-stack-2">
                                            <label for="translation-slug-{{ $translation->locale_id }}">{{ $pageDuplicateText('locale_slug', ['locale' => strtoupper($translation->locale?->code ?? (string) $translation->locale_id)]) }}</label>
                                            <input id="translation-slug-{{ $translation->locale_id }}" name="translations[{{ $index }}][slug]" class="wb-input" type="text" value="{{ $oldTranslation['slug'] ?? $suggestedSlug }}" required>
                                            <div class="wb-text-sm wb-text-muted">{{ $pageDuplicateText('source_path', ['path' => $translation->path]) }}</div>
                                            @error('translations.'.$index.'.slug')
                                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-webblocks-cms::admin.form-actions
                        :cancel-url="$backUrl"
                        :submit-label="$pageDuplicateText('duplicate_page')"
                    />
                </form>
            </div>
        </div>
    </div>
@endsection
