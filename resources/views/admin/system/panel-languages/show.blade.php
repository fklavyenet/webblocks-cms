@php
    $text = static fn (string $key, array $replace = []) => __('webblocks-cms::panel-languages.'.$key, $replace);
    $active = (int) $catalog->active_catalog_id === (int) $catalog->id;
    $backLink = '<a class="wb-btn wb-btn-secondary" href="'.e(route('admin.system.panel-languages.index')).'">'.e($text('back')).'</a>';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('preview_title'), 'heading' => $text('preview_title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('preview_title'), 'actions' => $backLink])
    @include('webblocks-cms::admin.system.panel-languages.feedback')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <h2 class="wb-text-base wb-m-0"><span lang="{{ $catalog->language_code }}">{{ $catalog->name }}</span> <code>{{ $catalog->language_code }}</code></h2>
                <span class="wb-status-pill {{ $active ? 'wb-status-active' : 'wb-status-inactive' }}">{{ $text($active ? 'active' : 'inactive') }}</span>
            </div>
            <span class="wb-text-sm wb-text-muted">{{ $text('coverage', ['translated' => $translated, 'total' => $total]) }}</span>
        </div>

        <div class="wb-card-body wb-stack-3">
            @if ($sample === [])
                <div class="wb-empty wb-empty-sm"><div class="wb-empty-title">{{ $text('no_sample') }}</div></div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped" aria-label="{{ $text('sample') }}">
                        <thead>
                            <tr>
                                <th scope="col">{{ $text('source_text') }}</th>
                                <th scope="col">{{ $text('translated_text') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sample as $key => $value)
                                <tr>
                                    <th scope="row" class="wb-table-break">
                                        @include('webblocks-cms::admin.system.panel-languages.message', ['message' => $sampleSource[$key], 'language' => 'en'])
                                        <div class="wb-text-sm wb-text-muted"><code>{{ $key }}</code></div>
                                    </th>
                                    <td class="wb-table-break">
                                        @include('webblocks-cms::admin.system.panel-languages.message', ['message' => $value, 'language' => $catalog->language_code])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($missing->total() > 0)
                <details class="wb-collapse-panel" id="panel-language-missing" @if (request()->has('missing_page')) open @endif>
                    <summary class="wb-collapse-trigger">{{ $text('missing') }} ({{ $missing->total() }})</summary>
                    <div class="wb-collapse-body">
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped" aria-label="{{ $text('missing') }}">
                                <tbody>
                                    @foreach ($missing as $key)
                                        <tr><td class="wb-table-break"><code>{{ $key }}</code></td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $missing, 'ariaLabel' => $text('missing'), 'compact' => true])
                    </div>
                </details>
            @endif
        </div>

        @if ($translated > 0 && ! $active)
            <div class="wb-card-footer wb-cluster wb-cluster-end">
                <form method="POST" action="{{ route('admin.system.panel-languages.activate', $catalog->id) }}">
                    @csrf
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $text('activate') }}</button>
                </form>
            </div>
        @endif
    </div>
@endsection
