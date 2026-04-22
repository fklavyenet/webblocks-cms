@extends('layouts.admin', ['title' => 'Clone Site', 'heading' => 'Clone Site'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Clone Site',
        'description' => 'Duplicate one site into another site in the same install. Use overwrite only when you intentionally want to replace target content.',
    ])

    @include('admin.partials.flash')

    @if ($errors->has('clone'))
        <div class="wb-alert wb-alert-danger">{{ $errors->first('clone') }}</div>
    @endif

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.sites.clone.store') }}" class="wb-stack wb-gap-4">
                @csrf

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-2">
                        <label for="source_site_id">Source site</label>
                        <select id="source_site_id" name="source_site_id" class="wb-select" required>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected((int) old('source_site_id', $sourceSite?->id) === $site->id)>
                                    {{ $site->name }} ({{ $site->handle }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-2">
                        <label for="target_identifier">Target site id / handle / domain</label>
                        <input id="target_identifier" name="target_identifier" class="wb-input" type="text" value="{{ old('target_identifier') }}" required>
                    </div>
                </div>

                <div class="wb-grid wb-grid-3">
                    <div class="wb-stack wb-gap-2">
                        <label for="target_name">Target name</label>
                        <input id="target_name" name="target_name" class="wb-input" type="text" value="{{ old('target_name') }}">
                    </div>

                    <div class="wb-stack wb-gap-2">
                        <label for="target_handle">Target handle</label>
                        <input id="target_handle" name="target_handle" class="wb-input" type="text" value="{{ old('target_handle') }}">
                    </div>

                    <div class="wb-stack wb-gap-2">
                        <label for="target_domain">Target domain</label>
                        <input id="target_domain" name="target_domain" class="wb-input" type="text" value="{{ old('target_domain') }}">
                    </div>
                </div>

                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-grid wb-grid-2">
                        <label class="wb-nowrap"><input type="hidden" name="with_navigation" value="0"><input type="checkbox" name="with_navigation" value="1" @checked(old('with_navigation', true))> <span>Clone navigation</span></label>
                        <label class="wb-nowrap"><input type="hidden" name="with_media" value="0"><input type="checkbox" name="with_media" value="1" @checked(old('with_media', true))> <span>Clone media references</span></label>
                        <label class="wb-nowrap"><input type="hidden" name="copy_media_files" value="0"><input type="checkbox" name="copy_media_files" value="1" @checked(old('copy_media_files'))> <span>Copy media files</span></label>
                        <label class="wb-nowrap"><input type="hidden" name="with_translations" value="0"><input type="checkbox" name="with_translations" value="1" @checked(old('with_translations', true))> <span>Clone translations</span></label>
                        <label class="wb-nowrap"><input type="hidden" name="overwrite_target" value="0"><input type="checkbox" name="overwrite_target" value="1" @checked(old('overwrite_target'))> <span>Overwrite target</span></label>
                        <label class="wb-nowrap"><input type="hidden" name="dry_run" value="0"><input type="checkbox" name="dry_run" value="1" @checked(old('dry_run'))> <span>Dry run only</span></label>
                    </div>
                </div>

                <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
                    <a href="{{ route('admin.sites.index') }}" class="wb-btn wb-btn-secondary">Back</a>
                    <button type="submit" class="wb-btn wb-btn-primary">Run Clone</button>
                </div>
            </form>
        </div>
    </div>
@endsection
