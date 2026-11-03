@php
    $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $j = fn ($key, $replace = []) => $translator->get('jobs.'.$key, $locale, $replace);
    $tab = request('tab', 'jobs');
    $label = fn ($definition) => trans($definition->label, [], $locale);
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $j('title')])
@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $j('title'), 'description' => $j('description')])
    @include('webblocks-cms::admin.partials.flash')
    @if (! $ready)<p class="wb-alert wb-alert-warning">{{ $j('schema') }}</p>@else
    <p>{{ $j('manual_help') }}</p>
    @if ($system && ! $automation['has_automatic'])<p class="wb-alert wb-alert-info">{{ $j('manual_profile') }}</p>@endif
    @if ($system && $automation['server_required'] && ! $automation['sources']['cli']['verified'])<p class="wb-alert wb-alert-warning">{{ $j('server_missing') }}</p>@endif
    <div class="wb-tabs" data-wb-tabs>
        <div class="wb-tabs-nav" role="tablist" aria-label="{{ $j('title') }}">
            @foreach ($system ? ['jobs', 'history', 'automation'] : ['jobs', 'history'] as $panel)
                <button class="wb-tabs-btn {{ $tab === $panel ? 'is-active' : '' }}" type="button" data-wb-tab="jobs-{{ $panel }}" role="tab" aria-selected="{{ $tab === $panel ? 'true' : 'false' }}">{{ $j($panel) }}</button>
            @endforeach
        </div>
        <div class="wb-tabs-panels">
            <section class="wb-tabs-panel {{ $tab === 'jobs' ? 'is-active' : '' }}" id="jobs-jobs" role="tabpanel" @if ($tab !== 'jobs') hidden @endif>
                <div class="wb-table-wrap"><table class="wb-table"><thead><tr><th scope="col">{{ $j('jobs') }}</th><th scope="col">{{ $j('automation') }}</th><th scope="col">{{ $j('next_check') }}</th><th scope="col">{{ $j('source') }}</th><th scope="col">{{ $j('actions') }}</th></tr></thead><tbody>
                    @foreach ($definitions as $definition)
                        @php($scheduled = app(\WebBlocks\Cms\Support\Jobs\JobRegistry::class)->automatic($definition))
                        <tr><th scope="row">{{ $label($definition) }}@if ($definition->help)<details><summary>{{ $j('scope_details') }}</summary><p>{{ trans($definition->help, [], $locale) }}</p></details>@endif</th><td>{{ $j($scheduled ? 'enabled' : 'not_enabled') }}
                            @if ($system && $definition->key !== 'probe')<button class="wb-btn wb-btn-ghost wb-btn-icon" type="button" data-wb-toggle="modal" data-wb-target="#job-schedule-{{ $loop->index }}" aria-label="{{ $j('toggle').' · '.$label($definition) }}" title="{{ $j('toggle') }}"><i class="wb-icon wb-icon-settings" aria-hidden="true"></i></button>@endif</td>
                            <td>{{ $scheduled && $definition->cron ? (new \Cron\CronExpression($definition->cron))->getNextRunDate(now(), 0, false, config('app.timezone'))->format('Y-m-d H:i T') : '—' }}</td><td>{{ $j($definition->web ? 'web' : 'server') }}</td><td>
                            @if ($definition->key === 'seo')
                                @foreach ($jobSites as $jobSite)<a class="wb-btn wb-btn-secondary" href="{{ route('admin.sites.seo-audits.index', $jobSite) }}">{{ $jobSite->name }}</a>@endforeach
                            @elseif (! $definition->web)
                                <p>{{ $j('web_unavailable') }}</p>
                            @elseif ($definition->effects)
                                <button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#job-request-{{ $loop->index }}">{{ $j('enqueue') }}</button>
                            @else
                                <form method="POST" action="{{ route('admin.jobs.store') }}">@csrf<input type="hidden" name="job_key" value="{{ $definition->key }}"><button class="wb-btn wb-btn-secondary" type="submit">{{ $j('enqueue') }}</button></form>
                            @endif
                            @if ($definition->destination)<a class="wb-link" href="{{ route($definition->destination) }}">{{ $j('open_feature') }}</a>@endif
                        </td></tr>
                    @endforeach
                </tbody></table></div>
                <p class="wb-text-sm">{{ $j('automatic_optional') }}</p>
                @include('webblocks-cms::admin.system.job-runs', ['jobRuns' => $activeRuns])
            </section>
            <section class="wb-tabs-panel {{ $tab === 'history' ? 'is-active' : '' }}" id="jobs-history" role="tabpanel" @if ($tab !== 'history') hidden @endif>
                @include('webblocks-cms::admin.system.job-runs', ['jobRuns' => $runs])
                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $runs->appends(['tab' => 'history'])])
            </section>
            @if ($system)
            <section class="wb-tabs-panel {{ $tab === 'automation' ? 'is-active' : '' }}" id="jobs-automation" role="tabpanel" @if ($tab !== 'automation') hidden @endif>
                <p>{{ $j('verification_help') }}</p>
                <div class="wb-table-wrap"><table class="wb-table"><thead><tr><th scope="col">{{ $j('source') }}</th><th scope="col">{{ $j('state') }}</th><th scope="col">{{ $j('last_progress') }}</th></tr></thead><tbody>
                    @foreach ($automation['sources'] as $source => $evidence)<tr><th scope="row">{{ $j($source) }}</th><td>{{ $j($source === 'http' && ! $automation['http_enabled'] ? 'not_enabled' : ($evidence['verified'] ? 'verified' : 'unverified')) }}</td><td>{{ $evidence['last'] ?? '—' }}</td></tr>@endforeach
                </tbody></table></div>
                <section class="wb-card"><div class="wb-card-header"><h2 class="wb-card-title">{{ $j('install') }}</h2></div><div class="wb-card-body">
                    <p>{{ $j('hosting_help') }}</p>
                    <h3>{{ $j('hosting') }}</h3>
                    <div class="wb-table-wrap"><table class="wb-table"><tbody>@foreach (['schedule', 'command', 'setup_command', 'status_command'] as $key)<tr><th scope="row">{{ $translator->get('scheduler.'.$key, $locale) }}</th><td><code>{{ $automation['instructions'][$key] ?? '—' }}</code></td></tr>@endforeach</tbody></table></div>
                </div></section>
                <section class="wb-card"><div class="wb-card-header"><h2 class="wb-card-title">{{ $j('http') }}</h2></div><div class="wb-card-body">
                    <p>{{ $j('http_help') }}</p><p><code>POST {{ route('webblocks.jobs.tick', [], true) }}</code></p>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#job-automation-rotate">{{ $j('rotate') }}</button>
                    @if ($automation['http_enabled'])<button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#job-automation-disable">{{ $j('disable') }}</button>@endif
                </div></section>
            </section>
            @endif
        </div>
    </div>
    @endif
@endsection
@push('overlays')
    @include('webblocks-cms::admin.partials.job-progress')
    @if ($system && $ready)
        @foreach ($definitions as $definition)
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'job-schedule-'.$loop->index, 'title' => $label($definition), 'description' => $j('toggle'), 'action' => route('admin.jobs.automation', 'schedule'), 'method' => 'POST', 'submitLabel' => $j('confirm')])
                <input type="hidden" name="confirmed" value="1"><input type="hidden" name="job_key" value="{{ $definition->key }}"><input type="hidden" name="automatic" value="{{ app(\WebBlocks\Cms\Support\Jobs\JobRegistry::class)->automatic($definition) ? '0' : '1' }}">
                <p>{{ $j(app(\WebBlocks\Cms\Support\Jobs\JobRegistry::class)->automatic($definition) ? 'not_enabled' : 'enabled') }}</p>
                @if ($definition->effects)<p>{{ $j('effects') }}</p>@endif
            @endcomponent
            @if ($definition->effects && $definition->web)
                @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'job-request-'.$loop->index, 'title' => $label($definition), 'description' => $j('effects'), 'action' => route('admin.jobs.store'), 'method' => 'POST', 'submitLabel' => $j('confirm')])
                    <input type="hidden" name="job_key" value="{{ $definition->key }}"><input type="hidden" name="confirmed" value="1">
                    <p>{{ $label($definition) }}</p><p>{{ $j('installation_scope') }}</p>
                    @if (! $definition->web)<p>{{ $j('cli_only') }}</p>@endif
                @endcomponent
            @endif
        @endforeach
        @foreach (['rotate', 'disable'] as $operation)
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'job-automation-'.$operation, 'title' => $j($operation), 'description' => $j('http_help'), 'action' => route('admin.jobs.automation', $operation), 'method' => 'POST', 'submitLabel' => $j('confirm')])
                <input type="hidden" name="confirmed" value="1">
            @endcomponent
        @endforeach
    @endif
@endpush
@push('scripts')<script src="{{ asset('cms/js/admin/jobs.js') }}?v={{ \WebBlocks\Cms\Support\WebBlocks::VERSION }}" defer></script>@endpush
