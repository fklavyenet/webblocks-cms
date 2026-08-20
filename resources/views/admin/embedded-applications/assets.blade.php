@php
    $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $text = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('embedded_applications.'.$key, $locale);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('application_files'), 'heading' => $text('application_files')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('application_files'), 'description' => $text('application_files_description')])
    @include('webblocks-cms::admin.partials.flash')
    @if ($errors->any())<div class="wb-alert wb-alert-danger wb-mb-4"><div>{{ $errors->first() }}</div></div>@endif

    <div class="wb-card">
        <div class="wb-card-header wb-flex wb-items-end wb-justify-between wb-gap-3 wb-flex-wrap">
            <form method="GET" action="{{ route('admin.embedded-applications.assets.index', $application) }}" class="wb-stack wb-gap-1">
                <label for="application_asset_site">{{ $text('site') }}</label>
                <div class="wb-flex wb-gap-2"><select class="wb-select" id="application_asset_site" name="site">@foreach($sites as $option)<option value="{{ $option->id }}" @selected($site?->is($option))>{{ $option->name }}</option>@endforeach</select><button class="wb-btn wb-btn-secondary" type="submit">{{ $text('select_site') }}</button></div>
            </form>
            @if($site)<button class="wb-btn wb-btn-primary" type="button" data-wb-toggle="modal" data-wb-target="#application-asset-upload"><i class="wb-icon wb-icon-upload" aria-hidden="true"></i> {{ $text('upload_file') }}</button>@endif
        </div>
        <div class="wb-card-body">
            @if(!$site)<div class="wb-alert wb-alert-info"><div>{{ $text('no_sites') }}</div></div>
            @elseif(count($assets) === 0)<div class="wb-alert wb-alert-info"><div>{{ $text('assets_empty') }}</div></div>
            @else
                <div class="wb-table-wrap"><table class="wb-table wb-table-striped wb-table-hover"><thead><tr><th>{{ $text('filename') }}</th><th>{{ $text('setting_type') }}</th><th>{{ $text('public_path') }}</th><th>{{ $text('size') }}</th><th>{{ $text('updated_at') }}</th><th>{{ $text('actions') }}</th></tr></thead><tbody>
                @foreach($assets as $asset)
                    <tr><td><strong>{{ $asset['filename'] }}</strong></td><td><code>{{ $asset['type'] }}</code></td><td><code>{{ $asset['public_path'] }}</code></td><td>{{ number_format($asset['size']) }} B</td><td>{{ $asset['updated_at'] ? date('Y-m-d H:i', $asset['updated_at']) : '—' }}</td><td class="wb-table-actions"><div class="wb-flex wb-gap-2"><button class="wb-action-btn wb-action-btn-edit" type="button" data-wb-toggle="modal" data-wb-target="#application-asset-edit-{{ $loop->index }}" aria-label="{{ $text('edit') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></button><button class="wb-action-btn wb-action-btn-delete" type="button" data-wb-toggle="modal" data-wb-target="#application-asset-delete-{{ $loop->index }}" aria-label="{{ $text('delete') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button></div></td></tr>
                @endforeach
                </tbody></table></div>
            @endif
        </div>
        <div class="wb-card-footer"><a class="wb-btn wb-btn-secondary" href="{{ route('admin.embedded-applications.edit', $application) }}">{{ $text('back_to_application') }}</a></div>
    </div>
@endsection

@if($site)
@push('overlays')
    <div class="wb-modal" id="application-asset-upload" role="dialog" aria-modal="true" aria-labelledby="application-asset-upload-title"><div class="wb-modal-dialog"><form method="POST" enctype="multipart/form-data" action="{{ route('admin.embedded-applications.assets.store', $application) }}">@csrf<input type="hidden" name="site_id" value="{{ $site->id }}"><div class="wb-modal-header"><h2 class="wb-modal-title" id="application-asset-upload-title">{{ $text('upload_file') }}</h2><button class="wb-modal-close" type="button" data-wb-dismiss="modal" aria-label="{{ $text('cancel') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button></div><div class="wb-modal-body wb-stack wb-gap-2"><label for="application_asset_file">{{ $text('file') }}</label><input class="wb-input" id="application_asset_file" type="file" name="asset" accept=".css,.js,.html,text/css,text/html,text/javascript,application/javascript" required><div class="wb-text-sm wb-text-muted">{{ $text('file_help') }}</div></div><div class="wb-modal-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ $text('upload') }}</button><button class="wb-btn wb-btn-secondary" type="button" data-wb-dismiss="modal">{{ $text('cancel') }}</button></div></form></div></div>
    @foreach($assets as $asset)
        <div class="wb-modal wb-modal-lg" id="application-asset-edit-{{ $loop->index }}" role="dialog" aria-modal="true" aria-labelledby="application-asset-edit-title-{{ $loop->index }}"><div class="wb-modal-dialog"><form method="POST" action="{{ route('admin.embedded-applications.assets.update', [$application, $asset['type'], $asset['filename']]) }}">@csrf @method('PUT')<input type="hidden" name="site_id" value="{{ $site->id }}"><input type="hidden" name="expected_checksum" value="{{ $asset['checksum'] }}"><div class="wb-modal-header"><h2 class="wb-modal-title" id="application-asset-edit-title-{{ $loop->index }}">{{ $text('edit_file') }}: {{ $asset['filename'] }}</h2><button class="wb-modal-close" type="button" data-wb-dismiss="modal" aria-label="{{ $text('cancel') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button></div><div class="wb-modal-body"><textarea class="wb-textarea wb-font-mono" name="contents" rows="18" required>{{ $asset['contents'] }}</textarea></div><div class="wb-modal-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ $text('save_file') }}</button><button class="wb-btn wb-btn-secondary" type="button" data-wb-dismiss="modal">{{ $text('cancel') }}</button></div></form></div></div>
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'application-asset-delete-'.$loop->index, 'title' => $text('delete_file'), 'description' => $text('delete_file_help'), 'action' => route('admin.embedded-applications.assets.destroy', [$application, $asset['type'], $asset['filename']]), 'method' => 'DELETE', 'submitLabel' => $text('delete')])<input type="hidden" name="site_id" value="{{ $site->id }}"><input type="hidden" name="expected_checksum" value="{{ $asset['checksum'] }}"><p>{{ $asset['filename'] }}</p>@endcomponent
    @endforeach
@endpush
@endif
