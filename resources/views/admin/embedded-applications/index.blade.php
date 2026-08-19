@php
    $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $text = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('embedded_applications.'.$key, $locale);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title'), 'description' => $text('description'), 'count' => $applications->count()])
    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-flex-wrap">
            <strong>{{ $text('title') }}</strong>
            <a href="{{ route('admin.embedded-applications.create') }}" class="wb-btn wb-btn-primary">{{ $text('create') }}</a>
        </div>
        <div class="wb-table-wrap">
            <table class="wb-table">
                <thead><tr><th>{{ $text('name') }}</th><th>{{ $text('handle') }}</th><th>{{ $text('mode') }}</th><th>{{ $text('status') }}</th><th>{{ $text('actions') }}</th></tr></thead>
                <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td><strong>{{ $application->name }}</strong><div class="wb-text-sm wb-text-muted">{{ $application->version }}</div></td>
                        <td><code>{{ $application->handle }}</code></td>
                        <td>{{ $application->render_mode }}</td>
                        <td><span class="wb-status-pill {{ $application->is_enabled ? 'wb-status-success' : 'wb-status-muted' }}">{{ $application->is_enabled ? $text('enabled') : $text('disabled') }}</span></td>
                        <td><a class="wb-btn wb-btn-secondary" href="{{ route('admin.embedded-applications.edit', $application) }}">{{ $text('edit') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ $text('empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
