@if (($unfinishedUpdates ?? collect())->isNotEmpty())
  <section aria-labelledby="update-attention-title" class="wb-stack wb-stack-3">
    <h3 id="update-attention-title">{{ __('webblocks-cms::update_recovery.title') }}</h3>
    <div class="wb-alert wb-alert-warning" role="status"><p>{{ __('webblocks-cms::update_recovery.help') }}</p></div>
    <div class="wb-table-wrap">
      <table class="wb-table">
        <thead><tr>
          <th scope="col">{{ __('webblocks-cms::update_recovery.record') }}</th>
          <th scope="col">{{ __('webblocks-cms::update_recovery.version') }}</th>
          <th scope="col">{{ $adminText('common.status') }}</th>
          <th scope="col">{{ __('webblocks-cms::update_recovery.evidence') }}</th>
          <th scope="col" class="wb-table-actions">{{ __('webblocks-cms::update_recovery.actions') }}</th>
        </tr></thead>
        <tbody>
          @foreach ($unfinishedUpdates as $item)
            <tr>
              <th scope="row">{{ __('webblocks-cms::update_recovery.update') }}</th>
              <td>{{ $item['from_version'] ?: '—' }} → {{ $item['target_version'] ?: '—' }}</td>
              <td>{{ $item['display_status'] === 'attention' ? __('webblocks-cms::update_recovery.attention') : ($item['record']->status === 'queued' ? __('webblocks-cms::update_recovery.queued') : $adminText('updates.statuses.'.$item['record']->status)) }}</td>
              <td>
                <strong>{{ __('webblocks-cms::update_recovery.states.'.$item['liveness']['state']) }}</strong>
                @foreach ($item['liveness']['reasons'] as $reason)
                  <p class="wb-text-sm">{{ __('webblocks-cms::update_recovery.reasons.'.$reason) }}</p>
                @endforeach
              </td>
              <td class="wb-table-actions">
                <div class="wb-action-group">
                  <button type="button" class="wb-action-btn" data-wb-toggle="modal" data-wb-target="#review-{{ $item['kind'] }}-{{ $item['record']->getKey() }}" data-wb-tooltip="{{ __('webblocks-cms::update_recovery.details') }}" aria-label="{{ __('webblocks-cms::update_recovery.details') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></button>
                  @if ($item['can_dismiss'] && ($canReconcileUpdates ?? false))
                    <form method="POST" action="{{ route('admin.system.updates.dismiss') }}">
                      @csrf
                      <input type="hidden" name="record_type" value="{{ $item['kind'] }}">
                      <input type="hidden" name="record_id" value="{{ $item['record']->getKey() }}">
                      <button type="submit" class="wb-action-btn wb-action-btn-delete" data-wb-tooltip="{{ __('webblocks-cms::update_recovery.dismiss') }}" aria-label="{{ __('webblocks-cms::update_recovery.dismiss') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                    </form>
                  @endif
                  @if (($item['can_resolve_pre_apply'] || $item['liveness']['can_reconcile']) && ($canReconcileUpdates ?? false))
                    <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-toggle="modal" data-wb-target="#reconcile-{{ $item['kind'] }}-{{ $item['record']->getKey() }}">{{ __('webblocks-cms::update_recovery.'.($item['can_resolve_pre_apply'] ? 'resolve' : 'reconcile')) }}</button>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
  @foreach ($unfinishedUpdates as $item)
    <div id="review-{{ $item['kind'] }}-{{ $item['record']->getKey() }}" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="review-title-{{ $item['kind'] }}-{{ $item['record']->getKey() }}">
      <div class="wb-modal-dialog">
        <div class="wb-modal-header">
          <h3 class="wb-modal-title" id="review-title-{{ $item['kind'] }}-{{ $item['record']->getKey() }}">{{ __('webblocks-cms::update_recovery.details') }}</h3>
          <button type="button" class="wb-icon-btn wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('updates.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body wb-stack wb-stack-3">
          <p>{{ __('webblocks-cms::update_recovery.saved_status') }}: {{ $item['record']->status }}</p>
          @foreach ($item['related'] as $related)
            <p class="wb-text-sm">{{ __('webblocks-cms::update_recovery.kinds.'.$related['kind']) }}: {{ $related['id'] }}</p>
          @endforeach
          @if ($item['chain_steps']->isNotEmpty())
            <div class="wb-table-wrap"><table class="wb-table">
              <thead><tr><th>{{ __('webblocks-cms::update_recovery.step') }}</th><th>{{ __('webblocks-cms::update_recovery.version') }}</th><th>{{ __('webblocks-cms::update_recovery.saved_status') }}</th></tr></thead>
              <tbody>@foreach ($item['chain_steps'] as $step)
                <tr><th scope="row">{{ $step->position }}</th><td>{{ $step->from_version }} → {{ $step->version }}</td><td>{{ $step->status === 'queued' ? __('webblocks-cms::update_recovery.queued') : $adminText('updates.statuses.'.$step->status) }}</td></tr>
              @endforeach</tbody>
            </table></div>
          @endif
          @foreach ($item['audits'] as $audit)
            <p>{{ $audit->checked_at }} · {{ __('webblocks-cms::update_recovery.operator', ['id' => $audit->actor_id]) }}</p>
            <p>{{ $audit->previous_status }} → {{ $audit->status }} · {{ $sanitizer->sanitize($audit->reason) }}</p>
          @endforeach
          <p>{{ __('webblocks-cms::update_recovery.'.($item['can_resolve_pre_apply'] ? 'resolve_help' : 'guidance')) }}</p>
        </div>
      </div>
    </div>
    @if (($item['can_resolve_pre_apply'] || $item['liveness']['can_reconcile']) && ($canReconcileUpdates ?? false))
      <div id="reconcile-{{ $item['kind'] }}-{{ $item['record']->getKey() }}" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="reconcile-title-{{ $item['kind'] }}-{{ $item['record']->getKey() }}">
        <div class="wb-modal-dialog">
          <div class="wb-modal-header">
            <h3 class="wb-modal-title" id="reconcile-title-{{ $item['kind'] }}-{{ $item['record']->getKey() }}">{{ __('webblocks-cms::update_recovery.'.($item['can_resolve_pre_apply'] ? 'resolve' : 'reconcile')) }}</h3>
            <button type="button" class="wb-icon-btn wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('updates.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
          </div>
          <form method="POST" action="{{ route($item['can_resolve_pre_apply'] ? 'admin.system.updates.resolve-pre-apply' : 'admin.system.updates.reconcile') }}">
            @csrf
            <input type="hidden" name="record_type" value="{{ $item['kind'] }}">
            <input type="hidden" name="record_id" value="{{ $item['record']->getKey() }}">
            <div class="wb-modal-body wb-stack wb-stack-3">
              <div class="wb-alert wb-alert-warning"><p>{{ __('webblocks-cms::update_recovery.'.($item['can_resolve_pre_apply'] ? 'resolve_help' : 'confirm_help')) }}</p></div>
              <label class="wb-label" for="reconcile-reason-{{ $item['kind'] }}-{{ $item['record']->getKey() }}">{{ __('webblocks-cms::update_recovery.reason') }}</label>
              <textarea class="wb-textarea" id="reconcile-reason-{{ $item['kind'] }}-{{ $item['record']->getKey() }}" name="reason" required minlength="10" maxlength="500">{{ old('record_type') === $item['kind'] && old('record_id') === (string) $item['record']->getKey() ? old('reason') : '' }}</textarea>
            </div>
            <div class="wb-modal-footer"><button type="submit" class="wb-btn wb-btn-primary">{{ __('webblocks-cms::update_recovery.'.($item['can_resolve_pre_apply'] ? 'resolve' : 'reconcile')) }}</button></div>
          </form>
        </div>
      </div>
    @endif
  @endforeach
@endif
