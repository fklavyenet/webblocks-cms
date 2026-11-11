@extends('webblocks-cms::layouts.admin')

@php($text = static fn ($key) => __('webblocks-cms::panel-languages.'.$key))

@section('content')
<div class="wb-stack wb-gap-4">
    <h1>{{ $text('title') }}</h1>

    @if ($errors->any())
        <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="wb-card">
        <div class="wb-card-body wb-stack wb-gap-3">
            <p>{{ $text('help') }}</p>
            <div><a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.template') }}">{{ $text('template') }}</a></div>
            <form method="POST" action="{{ route('admin.system.panel-languages.store') }}" enctype="multipart/form-data" class="wb-stack wb-gap-3">
                @csrf
                <div class="wb-field">
                    <label for="panel-language-catalog">{{ $text('file') }}</label>
                    <input class="wb-input" type="file" id="panel-language-catalog" name="catalog" accept=".json,application/json" required>
                </div>
                <div><button type="submit" class="wb-btn wb-btn-primary">{{ $text('upload') }}</button></div>
            </form>
        </div>
    </div>

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.system.panel-languages.default') }}" class="wb-card-body wb-stack wb-gap-3">
            @csrf
            @method('PUT')
            <div class="wb-field">
                <label for="panel-language-default">{{ $text('default') }}</label>
                <select class="wb-select" id="panel-language-default" name="code" required>
                    @foreach ($options as $code => $label)
                        <option value="{{ $code }}" @selected($code === $defaultLocale)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><button type="submit" class="wb-btn wb-btn-primary">{{ $text('save_default') }}</button></div>
        </form>
    </div>

    @if ($catalogs->isEmpty())
        <p>{{ $text('empty') }}</p>
    @else
        <div class="wb-table-wrap">
            <table class="wb-table">
                <thead>
                    <tr>
                        <th scope="col">{{ $text('language') }}</th>
                        <th scope="col">{{ $text('source_version') }}</th>
                        <th scope="col">{{ $text('status') }}</th>
                        <th scope="col">{{ $text('actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($catalogs as $catalog)
                        @php($active = (int) $catalog->active_catalog_id === (int) $catalog->id)
                        <tr>
                            <th scope="row" lang="{{ $catalog->language_code }}">{{ $catalog->name }} ({{ $catalog->language_code }})</th>
                            <td>{{ $catalog->source_version }}</td>
                            <td>{{ $text($active ? 'active' : 'inactive') }}</td>
                            <td>
                                <div class="wb-cluster wb-gap-2">
                                    <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.show', $catalog->id) }}">{{ $text('preview') }}</a>
                                    @if ($active)
                                        <form method="POST" action="{{ route('admin.system.panel-languages.deactivate', $catalog->language_code) }}">
                                            @csrf
                                            <button type="submit" class="wb-btn wb-btn-secondary">{{ $text(in_array($catalog->language_code, $packaged, true) ? 'use_package' : 'deactivate') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $catalogs->links() }}
    @endif
</div>
@endsection
