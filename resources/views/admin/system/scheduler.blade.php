@php
    $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $text = static fn (string $key) => $translator->get('scheduler.'.$key, $locale);
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])
@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title'), 'description' => $text('description')])
    <div class="wb-stack wb-gap-4">
        <section class="wb-card" aria-labelledby="scheduler-evidence-title">
            <div class="wb-card-header"><h2 class="wb-card-title" id="scheduler-evidence-title">{{ $text('evidence') }}</h2></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <p>{{ $text('scheduler_status') }}: <strong>{{ $text('health_'.$setup['health']['scheduler_status']) }}</strong></p>
                <p>{{ $text('last_seen') }}: {{ $setup['health']['last_seen_at'] ?? $text('no_record') }}</p>
                <p>{{ $text('runtime_help') }}</p>
                <a class="wb-link" href="{{ route('admin.system.scheduler.index') }}">{{ $text('refresh') }}</a>
            </div>
        </section>
        <section class="wb-card" aria-labelledby="scheduler-setup-title">
            <div class="wb-card-header"><h2 class="wb-card-title" id="scheduler-setup-title">{{ $text('setup') }}</h2></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <p>{{ $text('setup_help') }}</p>
                <p>{{ $text('account_help') }}</p>
                @if ($setup['command'])
                    <p>{{ $text('php_help') }}</p>
                    <div class="wb-table-wrap"><table class="wb-table"><tbody>
                        @foreach (['schedule', 'command', 'setup_command', 'install_command', 'status_command'] as $key)
                            <tr><th scope="row">{{ $text($key) }}</th><td><code class="wb-cms-scheduler-command">{{ $setup[$key] }}</code></td></tr>
                        @endforeach
                    </tbody></table></div>
                @else
                    <div class="wb-alert wb-alert-warning"><div>{{ $text('php_missing') }}</div></div>
                @endif
                <p>{{ $text('limits') }}</p>
                <p>{{ $text('policy_help') }}</p>
                <a class="wb-link" href="{{ route('admin.system.cleanup.index', ['tab' => 'settings']) }}">{{ $text('cleanup') }}</a>
                <a class="wb-link" href="{{ route('admin.system.health.index') }}#health-scheduling">{{ $text('health') }}</a>
            </div>
        </section>
    </div>
@endsection
