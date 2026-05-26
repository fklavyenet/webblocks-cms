@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $settings->labelText(),
        'description' => $settings->descriptionText() ?? 'This plugin has registered a settings surface. Editable settings storage is reserved for a later plugin phase.',
    ])

    <p><a href="{{ route('admin.system.plugins.show', $plugin->handle()) }}">Plugin Detail</a></p>

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $plugin->labelText() }}</strong>
        </div>

        <div class="wb-card-body">
            <div class="wb-empty">
                <div class="wb-empty-title">Settings contract registered.</div>
                <div class="wb-empty-text">This read-only foundation confirms routing and settings ownership for the enabled plugin.</div>
            </div>
        </div>
    </div>
@endsection
