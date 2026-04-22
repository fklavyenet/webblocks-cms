@extends('layouts.admin', ['title' => 'Asset Detail', 'heading' => 'Asset Detail'])

@php
    $showPreviewBackLink = request()->boolean('back_to_preview');
    $cancelUrl = $showPreviewBackLink
        ? route('admin.media.index', ['preview' => $asset->id])
        : route('admin.media.index');
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => $asset->title ?: $asset->filename,
        'description' => 'Review metadata, edit asset fields, and manage shared media records safely.',
        'actions' => ($showPreviewBackLink
            ? '<a href="'.route('admin.media.index', ['preview' => $asset->id]).'" class="wb-btn wb-btn-secondary">Back to Preview</a> '
            : '')
            .'<a href="'.route('admin.media.edit', array_filter(['asset' => $asset, 'back_to_preview' => $showPreviewBackLink ? 1 : null])).'" class="wb-btn wb-btn-primary">Edit Asset</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>Preview</strong></div>
            <div class="wb-card-body">
                @if ($asset->canPreview())
                    <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $asset->title ?: $asset->filename }}">
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">Preview unavailable</div>
                        <div class="wb-empty-text">This asset type does not have an inline preview in the current UI.</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Metadata</strong></div>
            <div class="wb-card-body">
                <div class="wb-stack wb-gap-2">
                    <div><strong>Title:</strong> {{ $asset->title ?? '-' }}</div>
                    <div><strong>Alt Text:</strong> {{ $asset->alt_text ?? '-' }}</div>
                    <div><strong>Caption:</strong> {{ $asset->caption ?? '-' }}</div>
                    <div><strong>Description:</strong> {{ $asset->description ?? '-' }}</div>
                    <div><strong>Folder:</strong> {{ $asset->folder?->name ?? '-' }}</div>
                    <div><strong>Usage State:</strong> {{ $usages->isNotEmpty() ? 'In use' : 'Unused' }}</div>
                    <div><strong>Usage Count:</strong> {{ $usages->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Usage</strong></div>
        <div class="wb-card-body">
            @if ($usages->isEmpty())
                <div class="wb-empty wb-empty-sm">
                    <div class="wb-empty-title">Unused asset</div>
                    <div class="wb-empty-text">This asset is not referenced by any protected CMS consumer yet.</div>
                </div>
            @else
                <div class="wb-stack wb-gap-2">
                    @foreach ($usages as $usage)
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body">
                                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $usage['label'] }}</strong>
                                        <div class="wb-text-sm wb-text-muted">{{ $usage['type'] }} | {{ $usage['context'] }}@if($usage['page_title']) | {{ $usage['page_title'] }}@endif</div>
                                    </div>
                                    @if (! empty($usage['admin_url']))
                                        <a href="{{ $usage['admin_url'] }}" class="wb-btn wb-btn-secondary">Open</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>File Details</strong></div>
        <div class="wb-card-body">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-2">
                    <div><strong>Filename:</strong> {{ $asset->filename }}</div>
                    <div><strong>Original Name:</strong> {{ $asset->original_name }}</div>
                    <div><strong>MIME Type:</strong> {{ $asset->mime_type ?? '-' }}</div>
                    <div><strong>Extension:</strong> {{ $asset->extension ?? '-' }}</div>
                    <div><strong>Size:</strong> {{ $asset->humanSize() }}</div>
                </div>
                <div class="wb-stack wb-gap-2">
                    <div><strong>Kind:</strong> {{ $asset->kind }}</div>
                    <div><strong>Path:</strong> <code>{{ $asset->path }}</code></div>
                    <div><strong>Disk:</strong> {{ $asset->disk }}</div>
                    <div><strong>Dimensions:</strong> {{ $asset->width && $asset->height ? $asset->width.' x '.$asset->height : '-' }}</div>
                    <div><strong>Created:</strong> {{ $asset->created_at?->format('Y-m-d H:i') }}</div>
                </div>
            </div>

            <x-admin.form-actions
                :cancel-url="$cancelUrl"
                :show-submit="false"
                :delete-form-action="route('admin.media.destroy', $asset)"
                delete-confirm="Delete this asset?"
                :delete-disabled="$usages->isNotEmpty()"
            />
        </div>
    </div>
@endsection
