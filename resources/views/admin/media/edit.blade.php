@extends('layouts.admin', ['title' => 'Edit Asset', 'heading' => 'Edit Asset'])

@php
    $showPreviewBackLink = request()->boolean('back_to_preview');
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Asset',
        'description' => 'Update shared asset metadata without changing the stored file itself.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.media.update', $asset) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')
                @if ($showPreviewBackLink)
                    <input type="hidden" name="back_to_preview" value="1">
                @endif

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1">
                        <label for="title">Title</label>
                        <input id="title" name="title" type="text" class="wb-input" value="{{ old('title', $asset->title) }}">
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="alt_text">Alt Text</label>
                        <input id="alt_text" name="alt_text" type="text" class="wb-input" value="{{ old('alt_text', $asset->alt_text) }}">
                    </div>
                </div>

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1">
                        <label for="folder_id">Folder</label>
                        <select id="folder_id" name="folder_id" class="wb-select">
                            <option value="">No folder</option>
                            @foreach ($folders as $folder)
                                <option value="{{ $folder->id }}" @selected((string) old('folder_id', $asset->folder_id) === (string) $folder->id)>{{ $folder->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label>Kind</label>
                        <div class="wb-card wb-card-muted"><div class="wb-card-body">{{ $asset->kind }}</div></div>
                    </div>
                </div>

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1">
                        <label for="caption">Caption</label>
                        <textarea id="caption" name="caption" class="wb-textarea" rows="4">{{ old('caption', $asset->caption) }}</textarea>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="wb-textarea" rows="4">{{ old('description', $asset->description) }}</textarea>
                    </div>
                </div>

                <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
                    <a href="{{ route('admin.media.show', array_filter(['asset' => $asset, 'back_to_preview' => $showPreviewBackLink ? 1 : null])) }}" class="wb-btn wb-btn-secondary">Back</a>
                    <button type="submit" class="wb-btn wb-btn-primary">Save Asset</button>
                </div>
            </form>
        </div>
    </div>
@endsection
