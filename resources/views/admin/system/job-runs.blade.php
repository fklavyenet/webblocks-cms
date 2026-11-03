<div class="wb-table-wrap"><table class="wb-table"><thead><tr><th scope="col">{{ $j('jobs') }}</th><th scope="col">{{ $j('state') }}</th><th scope="col">{{ $j('progress') }}</th><th scope="col">{{ $j('requested') }}</th><th scope="col">{{ $j('last_progress') }}</th><th scope="col">{{ $j('source') }}</th><th scope="col">{{ $j('run') }}</th></tr></thead><tbody>
@forelse ($jobRuns as $run)
    @php($definition = $definitions[$run->job_key] ?? null)
    <tr><th scope="row">#{{ $run->id }} · {{ $definition ? $label($definition) : $run->job_key }} @if ($run->site_id) · #{{ $run->site_id }} @endif</th><td>{{ $j('states.'.$run->state) }}@if ($run->requested_state)<p>{{ $j('stop_pending') }}</p>@endif
    @if ($run->reason)<p>{{ match ($run->reason) { 'partial' => trans('webblocks-cms::seo_workspace.incomplete', [], $locale), 'stale' => trans('webblocks-cms::seo.stale_report', [], $locale), default => $j('error') } }}</p>@endif</td><td>{{ $run->total === null ? (in_array($run->state, ['queued', 'running'], true) ? $j('preparing') : '—') : $run->processed.' / '.$run->total }}</td><td>{{ $run->created_at->format('Y-m-d H:i T') }}</td><td>{{ $run->progress_at?->format('Y-m-d H:i T') ?? '—' }}</td><td>{{ $j($run->source) }}</td><td>
    @if ($definition?->web && in_array($run->state, ['queued', 'running'], true))
        <div data-wb-job-runner data-error="{{ $j('error') }}"><button type="button" class="wb-btn wb-btn-primary" data-wb-job-start data-url="{{ route('admin.jobs.step', $run) }}">{{ $j('run') }}</button><p role="status" aria-live="polite" data-wb-job-status></p></div>
    @elseif (! $definition?->web && in_array($run->state, ['queued', 'running'], true)){{ $j('cli_only') }}@endif
    @if (in_array($run->state, ['queued', 'running', 'paused'], true))
        @foreach ($run->state === 'paused' ? ['resume', 'cancel'] : ['pause', 'cancel'] as $action)
            <form method="POST" action="{{ route('admin.jobs.transition', $run) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button type="submit" class="wb-btn wb-btn-secondary">{{ $action === 'cancel' ? $j('cancel') : trans('webblocks-cms::seo_workspace.'.$action, [], $locale) }}</button></form>
        @endforeach
    @endif
    </td></tr>
@empty<tr><td colspan="7">{{ $j('empty') }}</td></tr>@endforelse
</tbody></table></div>
