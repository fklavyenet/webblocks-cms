@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $contractModalLocale = app(AdminLocaleResolver::class)->locale();
  $contractModalTranslator = app(CmsTranslator::class);
  $contractModalText = static fn (string $key, array $replace = []) => $contractModalTranslator->admin('block_type_contract_modal.'.$key, $contractModalLocale, $replace);
  $contract = $contract ?? null;
  $modalId = 'blockTypeContractModal-'.$blockType->id;
  $modalTitleId = $modalId.'Title';
  $modalDescriptionId = $modalId.'Description';
  $isOpen = request('modal') === 'block-type-contract' && (int) request('contract_block_type') === $blockType->id;
  $contractStatusClass = match ($contract?->currentContractStatus) {
    'clear' => 'wb-status-active',
    'mostly clear', 'transitional' => 'wb-status-info',
    'needs review', 'legacy/fallback', 'not documented' => 'wb-status-pending',
    default => 'wb-status-info',
  };
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $isOpen) hidden @endif>
  <div class="wb-overlay-backdrop"></div>

  <div class="wb-modal wb-modal-xl {{ $isOpen ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}" data-admin-block-type-contract-modal>
    <div class="wb-modal-dialog">
      <div class="wb-modal-header">
        <div class="wb-stack wb-gap-1">
          <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $contractModalText('title', ['name' => $blockType->name]) }}</h2>
          <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $contractModalText('description') }}</span>
        </div>

        <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $contractModalText('close_modal_aria') }}">
          <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
        </a>
      </div>

      <div class="wb-modal-body wb-stack wb-gap-4">
        <div class="wb-alert wb-alert-info">
          <div>{{ $contractModalText('readonly_notice') }}</div>
        </div>

        @if ($contract && ! $contract->documented)
          <div class="wb-alert wb-alert-info">
            <div>{{ $contract->undocumentedMessage }}</div>
          </div>
        @endif

        <div class="wb-card wb-card-muted">
          <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <strong>{{ $contractModalText('catalog') }}</strong>

            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
              <span class="wb-status-pill {{ $blockType->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $blockType->status }}</span>
              <span class="wb-status-pill {{ $contractStatusClass }}">{{ $contract?->currentContractStatus ?? $contractModalText('not_documented') }}</span>
            </div>
          </div>

          <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
            <div class="wb-settings-row">
              <div class="wb-settings-row-label"><strong>{{ $contractModalText('slug') }}</strong></div>
              <div class="wb-settings-row-control"><span><code>{{ $contract?->slug ?? $blockType->slug }}</code></span></div>
            </div>
            <div class="wb-settings-row">
              <div class="wb-settings-row-label"><strong>{{ $contractModalText('category') }}</strong></div>
              <div class="wb-settings-row-control"><span>{{ $contract?->category ?: $contractModalText('none_documented') }}</span></div>
            </div>
            <div class="wb-settings-row">
              <div class="wb-settings-row-label"><strong>{{ $contractModalText('source_type') }}</strong></div>
              <div class="wb-settings-row-control"><span><code>{{ $contract?->sourceType ?: ($blockType->source_type ?: 'static') }}</code></span></div>
            </div>
            <div class="wb-settings-row">
              <div class="wb-settings-row-label"><strong>{{ $contractModalText('support_flags') }}</strong></div>
              <div class="wb-settings-row-control">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                  <span class="wb-status-pill wb-status-info">{{ ($contract?->isSystem ?? $blockType->is_system) ? $contractModalText('system') : $contractModalText('install_specific') }}</span>
                  <span class="wb-status-pill wb-status-info">{{ ($contract?->isContainer ?? $blockType->is_container) ? $contractModalText('container_capable') : $contractModalText('non_container') }}</span>
                  <span class="wb-status-pill wb-status-info">{{ ($contract?->adminFormSource ?? null) ? $contractModalText('admin_form_documented') : $contractModalText('admin_form_undocumented') }}</span>
                  <span class="wb-status-pill wb-status-info">{{ ($contract?->publicRendererSource ?? null) ? $contractModalText('renderer_documented') : $contractModalText('renderer_undocumented') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="wb-grid wb-grid-2">
          <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $contractModalText('admin_form') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
              <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $contractModalText('admin_form_source') }}</strong></div>
                <div class="wb-settings-row-control">
                  @if ($contract?->adminFormSource)
                    <span><code>{{ $contract->adminFormSource }}</code></span>
                  @else
                    <span class="wb-text-sm wb-text-muted">{{ $contractModalText('none_documented_sentence') }}</span>
                  @endif
                </div>
              </div>
              <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $contractModalText('visible_fields') }}</strong></div>
                <div class="wb-settings-row-control">
                  @include('webblocks-cms::admin.block-types.partials.contract-items', [
                    'items' => $contract?->adminFormFields ?? [],
                    'empty' => $contractModalText('none_documented_sentence'),
                    'code' => true,
                  ])
                </div>
              </div>
            </div>
          </div>

          <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $contractModalText('storage') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
              <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $contractModalText('storage_ownership') }}</strong></div>
                <div class="wb-settings-row-control">
                  @include('webblocks-cms::admin.block-types.partials.contract-items', [
                    'items' => $contract?->storageFields ?? [],
                    'empty' => $contractModalText('none_documented_sentence'),
                  ])
                </div>
              </div>
              <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $contractModalText('shared_settings_fields') }}</strong></div>
                <div class="wb-settings-row-control">
                  @include('webblocks-cms::admin.block-types.partials.contract-items', [
                    'items' => $contract?->sharedSettingsFields ?? [],
                    'empty' => $contractModalText('not_applicable_sentence'),
                    'code' => true,
                  ])
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="wb-grid wb-grid-2">
          <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $contractModalText('translation') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
              <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $contractModalText('translation_family') }}</strong></div>
                <div class="wb-settings-row-control"><span>{{ $contract?->translationFamily ? strtoupper($contract->translationFamily) : $contractModalText('not_applicable') }}</span></div>
              </div>
              <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $contractModalText('translatable_fields') }}</strong></div>
                <div class="wb-settings-row-control">
                  @include('webblocks-cms::admin.block-types.partials.contract-items', [
                    'items' => $contract?->translatableFields ?? [],
                    'empty' => $contractModalText('not_applicable_sentence'),
                    'code' => true,
                  ])
                </div>
              </div>
            </div>
          </div>

          <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $contractModalText('media_relationships') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
              @include('webblocks-cms::admin.block-types.partials.contract-items', [
                'items' => $contract?->mediaRelationshipFields ?? [],
                'empty' => $contractModalText('not_applicable_sentence'),
              ])
            </div>
          </div>
        </div>

        <div class="wb-grid wb-grid-2">
          <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $contractModalText('children_container_rules') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
              @include('webblocks-cms::admin.block-types.partials.contract-items', [
                'items' => $contract?->childContainerBehavior ?? [],
                'empty' => $contractModalText('not_applicable_sentence'),
              ])

              @if ($contract?->allowedChildTypeSlugs)
                <div class="wb-settings-row">
                  <div class="wb-settings-row-label"><strong>{{ $contractModalText('helper_child_whitelist') }}</strong></div>
                  <div class="wb-settings-row-control">
                    @include('webblocks-cms::admin.block-types.partials.contract-items', [
                      'items' => $contract->allowedChildTypeSlugs,
                      'empty' => $contractModalText('not_applicable_sentence'),
                      'code' => true,
                    ])
                  </div>
                </div>
              @endif
            </div>
          </div>

          <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $contractModalText('public_renderer') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
              <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $contractModalText('public_renderer_source') }}</strong></div>
                <div class="wb-settings-row-control">
                  @if ($contract?->publicRendererSource)
                    <span><code>{{ $contract->publicRendererSource }}</code></span>
                  @else
                    <span class="wb-text-sm wb-text-muted">{{ $contractModalText('none_documented_sentence') }}</span>
                  @endif
                </div>
              </div>
              <div class="wb-settings-row">
                <div class="wb-settings-row-label"><strong>{{ $contractModalText('renderer_root_contract') }}</strong></div>
                <div class="wb-settings-row-control"><span>{{ $contract?->rendererRootContract ?? $contractModalText('none_documented_sentence') }}</span></div>
              </div>
            </div>
          </div>
        </div>

        <div class="wb-card wb-card-muted">
          <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <strong>{{ $contractModalText('known_gaps') }}</strong>
            <span class="wb-status-pill {{ $contractStatusClass }}">{{ $contract?->currentContractStatus ?? $contractModalText('not_documented') }}</span>
          </div>
          <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
            @if ($contract && $contract->knownGaps !== [])
              <div class="wb-alert wb-alert-warning">
                <div>
                  @include('webblocks-cms::admin.block-types.partials.contract-items', [
                    'items' => $contract->knownGaps,
                    'empty' => $contractModalText('no_documented_gaps_sentence'),
                  ])
                </div>
              </div>
            @else
              <div class="wb-alert wb-alert-info">
                <div>{{ $contractModalText('no_documented_gaps_sentence') }}</div>
              </div>
            @endif
          </div>
        </div>
      </div>

      <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
        <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
          <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">{{ $contractModalText('close') }}</a>
        </div>
      </div>
    </div>
  </div>
</div>
