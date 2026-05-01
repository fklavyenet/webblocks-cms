@extends('layouts.admin', ['title' => 'Upload Backup', 'heading' => 'Upload Backup'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Upload Backup',
        'description' => 'Upload a WebBlocks CMS backup archive previously downloaded from this backup system. This is not a site export/import package.',
        'actions' => '<a href="'.route('admin.system.backups.index').'" class="wb-btn wb-btn-secondary">Back to Backups</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-alert wb-alert-warning">
            <div>
                <div class="wb-alert-title">Full system restore only</div>
                <div>This restores a full system backup. It will overwrite the current database and uploaded files. It is different from Export/Import, which creates a new site from a site package.</div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.system.backups.upload.store') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4">
                    @csrf

                    <div class="wb-stack wb-gap-2">
                        <label for="archive">Backup archive (.zip)</label>
                        <input id="archive" type="file" name="archive" class="wb-input" accept=".zip,application/zip" required>
                        <div class="wb-text-sm wb-text-muted">Upload a WebBlocks CMS backup archive previously downloaded from this backup system. This is not a site export/import package.</div>
                    </div>

                    <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                            <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            <button type="submit" class="wb-btn wb-btn-primary">Upload backup</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
