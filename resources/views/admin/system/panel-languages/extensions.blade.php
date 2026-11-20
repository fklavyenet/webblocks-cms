@php($text = static fn (string $key, array $replace = []) => __('webblocks-cms::panel-languages.'.$key, $replace))

@extends('webblocks-cms::layouts.admin', ['title' => $text('extensions_browse'), 'heading' => $text('extensions_browse')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $text('extensions_browse'),
        'description' => $text('extensions_for_cms', ['version' => $cmsVersion]),
        'actions' => '<a class="wb-btn wb-btn-secondary" href="'.e(route('admin.system.panel-languages.index')).'">'.e($text('back')).'</a>',
    ])
    @include('webblocks-cms::admin.system.panel-languages.feedback')

    @if ($catalogError)
        <div class="wb-alert wb-alert-danger" role="alert">{{ $catalogError }}</div>
    @endif

    @if ($releases !== [])
        <div class="wb-card">
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <thead><tr>
                        <th scope="col">{{ $text('language') }}</th>
                        <th scope="col">{{ $text('extensions_scope') }}</th>
                        <th scope="col">{{ $text('extensions_version') }}</th>
                        <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($releases as $release)
                            <tr>
                                <th scope="row" class="wb-table-break"><span lang="{{ $release['code'] }}">{{ $release['name'] }}</span> <code>{{ $release['code'] }}</code></th>
                                <td>{{ $text('extensions_scope_'.$release['format']) }}</td>
                                <td>{{ $release['version'] }}</td>
                                <td class="wb-table-actions">
                                    @if ($release['state'] === 'installed')
                                        <span class="wb-status-pill wb-status-active">{{ $text('extensions_installed') }}</span>
                                    @else
                                        @php($action = $text($release['format'] === 'panel' ? 'extensions_import' : 'extensions_'.$release['state']))
                                        <form method="POST" action="{{ route('admin.system.panel-languages.extensions.store') }}">
                                            @csrf
                                            <input type="hidden" name="code" value="{{ $release['code'] }}">
                                            <input type="hidden" name="version" value="{{ $release['version'] }}">
                                            <input type="hidden" name="format" value="{{ $release['format'] }}">
                                            <input type="hidden" name="checksum" value="{{ $release['sha256'] }}">
                                            <button type="submit" class="wb-btn wb-btn-secondary wb-btn-sm" aria-label="{{ $action }} · {{ $release['name'] }}">{{ $action }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif (! $catalogError)
        <div class="wb-card"><div class="wb-card-body">
            <p class="wb-empty-title">{{ $text('extensions_empty') }}</p>
        </div></div>
    @endif
@endsection
