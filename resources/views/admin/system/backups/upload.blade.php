@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('backups.upload_title'), 'heading' => $adminText('backups.upload_title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('backups.upload_title'),
        'description' => $adminText('backups.upload_description'),
        'actions' => '<a href="'.route('admin.system.backups.index').'" class="wb-btn wb-btn-secondary">'.$adminText('backups.back_to_backups').'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-alert wb-alert-warning">
            <div>
                <div class="wb-alert-title">{{ $adminText('backups.full_system_restore_only') }}</div>
                <div>{{ $adminText('backups.full_system_restore_only_help') }}</div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.system.backups.upload.store') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4">
                    @csrf

                    <div class="wb-stack wb-gap-2">
                        <label for="archive">{{ $adminText('backups.archive_zip') }}</label>
                        <input id="archive" type="file" name="archive" class="wb-file" accept=".zip,application/zip" required>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('backups.upload_description') }}</div>
                    </div>

                    <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                            <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('common.cancel') }}</a>
                            <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('backups.upload_backup') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
