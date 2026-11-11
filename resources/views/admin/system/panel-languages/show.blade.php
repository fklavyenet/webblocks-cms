@extends('webblocks-cms::layouts.admin')

@php($text = static fn ($key, $replace = []) => __('webblocks-cms::panel-languages.'.$key, $replace))

@section('content')
<div class="wb-stack wb-gap-4">
    <h1>{{ $text('preview') }} <span lang="{{ $catalog->language_code }}">{{ $catalog->name }} ({{ $catalog->language_code }})</span></h1>

    @if ($errors->any())
        <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="wb-card">
        <div class="wb-card-body wb-stack wb-gap-3">
            <p>{{ $text('coverage', ['translated' => $translated, 'total' => $total]) }}</p>
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <tbody>
                        @foreach ($sample as $key => $value)
                            <tr><th scope="row">{{ $key }}</th><td lang="{{ $catalog->language_code }}">{{ $value }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($missing !== [])
                <details class="wb-collapse-panel">
                    <summary class="wb-collapse-trigger">{{ $text('missing') }} ({{ count($missing) }})</summary>
                    <div class="wb-collapse-body">
                        <ul>
                            @foreach ($missing as $key)
                                <li>{{ $key }}</li>
                            @endforeach
                        </ul>
                    </div>
                </details>
            @endif
        </div>
        <div class="wb-card-footer wb-cluster wb-gap-2">
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.index') }}">{{ $text('back') }}</a>
            @if ($translated > 0 && (int) $catalog->active_catalog_id !== (int) $catalog->id)
                <form method="POST" action="{{ route('admin.system.panel-languages.activate', $catalog->id) }}">
                    @csrf
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $text('activate') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
