@php
    $text = static fn (string $key, array $replace = []) => __('webblocks-cms::panel-languages.'.$key, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('extensions_browse'), 'heading' => $text('extensions_browse')])

@section('content')
    @component('webblocks-cms::admin.partials.page-header', ['title' => $text('extensions_browse'), 'description' => $text('extensions_for_cms', ['version' => $cmsVersion])])
        @slot('actions')
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.index') }}">
                <i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i> {{ $text('back') }}
            </a>
        @endslot
    @endcomponent
    @include('webblocks-cms::admin.system.panel-languages.feedback')

    @if ($catalogError)
        <div class="wb-alert wb-alert-danger" role="alert">{{ $catalogError }}</div>
    @else
        <section class="wb-card" aria-label="{{ $text('extensions_browse') }}">
            <div class="wb-card-body">
                @if ($releases === [])
                    <div class="wb-empty"><p class="wb-empty-title">{{ $text('extensions_empty') }}</p></div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('webblocks-cms::admin.extensions_ui.name') }}</th>
                                    <th scope="col">{{ __('webblocks-cms::admin.system_plugins_index.version') }}</th>
                                    <th scope="col">{{ $text('status') }}</th>
                                    <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($releases as $release)
                                    @php
                                        $state = $release['state'];
                                        $action = $text('extensions_'.$state);
                                        $statusClass = match ($state) {
                                            'installed' => 'wb-status-active',
                                            'update' => 'wb-status-pending',
                                            default => 'wb-status-info',
                                        };
                                    @endphp
                                    <tr>
                                        <th scope="row" class="wb-table-break"><span lang="{{ $release['code'] }}">{{ $release['name'] }}</span> <code>{{ $release['code'] }}</code></th>
                                        <td>{{ $release['version'] }}</td>
                                        <td><span class="wb-status-pill {{ $statusClass }}">{{ $text('state_'.$state) }}</span></td>
                                        <td class="wb-table-actions">
                                            @if ($state !== 'installed')
                                                <form method="POST" action="{{ route('admin.system.panel-languages.extensions.store') }}">
                                                    @csrf
                                                    <input type="hidden" name="code" value="{{ $release['code'] }}">
                                                    <input type="hidden" name="version" value="{{ $release['version'] }}">
                                                    <input type="hidden" name="format" value="native">
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
                @endif
            </div>
        </section>
    @endif
@endsection
