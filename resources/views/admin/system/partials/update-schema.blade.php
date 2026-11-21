@php
  $chainSchemaState = $report['chain_schema'] ?? null;
  $schemaText = static fn (string $key) => __('webblocks-cms::update_schema.'.$key);
@endphp

@if (in_array($chainSchemaState, ['missing', 'unavailable'], true))
  <div class="wb-alert wb-alert-warning wb-stack wb-stack-2" role="status" data-wb-update-schema>
    <strong>{{ $schemaText('title') }}</strong>
    <p>{{ $schemaText($chainSchemaState) }}</p>
    @if ($canRepairChainSchema ?? false)
      <div><button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#update-schema-repair">{{ $schemaText('repair') }}</button></div>
    @else
      <p>{{ $schemaText('support') }}</p>
    @endif
  </div>
@endif

@if ($canRepairChainSchema ?? false)
  @push('overlays')
    <div id="update-schema-repair" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="update-schema-repair-title">
      <div class="wb-modal-dialog">
        <div class="wb-modal-header">
          <h2 class="wb-modal-title" id="update-schema-repair-title">{{ $schemaText('repair') }}</h2>
          <button type="button" class="wb-icon-btn wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('updates.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.system.updates.repair-schema') }}" data-wb-modal-submit-progress="backing_up">
          @csrf
          <div class="wb-modal-body"><p>{{ $schemaText('repair_help') }}</p></div>
          <div class="wb-modal-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ $schemaText('backup_and_repair') }}</button></div>
        </form>
      </div>
    </div>
  @endpush
@endif
