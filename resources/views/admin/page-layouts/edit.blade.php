@php
    $indexUrl = route('admin.page-layouts.index');
@endphp

@extends('layouts.admin', ['title' => 'Edit Page Layout', 'heading' => 'Page Layouts'])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$indexUrl.'">Page Layouts</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($pageLayout->name).'</span></li></ol></nav>',
        'title' => 'Edit Page Layout',
        'context' => '<span><code>'.e($pageLayout->handle).'</code></span>',
        'actions' => '<a href="'.$indexUrl.'" class="wb-btn wb-btn-secondary">Back to Page Layouts</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-gap-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <form method="POST" action="{{ route('admin.page-layouts.update', $pageLayout) }}" class="wb-stack wb-gap-0">
                    @csrf
                    @method('PUT')

                    <div class="wb-card-header"><strong>Layout Settings</strong></div>

                    <div class="wb-card-body">
                        @include('admin.page-layouts._form', ['pageLayout' => $pageLayout])
                    </div>

                    <div class="wb-card-footer">
                        <x-admin.form-actions :cancel-url="$indexUrl" submit-label="Save Changes" />
                    </div>
                </form>
            </div>

            <div class="wb-stack wb-gap-4">
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-header"><strong>Usage</strong></div>
                    <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                        <div><strong>Handle:</strong> <code>{{ $pageLayout->handle }}</code></div>
                        <div><strong>Status:</strong> <span class="wb-status-pill {{ $pageLayout->statusBadgeClass() }}">{{ $pageLayout->statusLabel() }}</span></div>
                        <div><strong>Ownership:</strong> {{ $pageLayout->ownershipLabel() }}</div>
                        <div><strong>Body Class:</strong> <code>{{ $pageLayout->body_class ?: '-' }}</code></div>
                    </div>
                </div>

                @if ($pageLayout->is_system)
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>System Layout</strong></div>
                        <div class="wb-card-body wb-text-sm wb-text-muted">
                            System layouts keep their handle fixed for backward compatibility. Seeded system layout slots can be edited for rendering details, but their stable slot mapping stays protected.
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @include('admin.page-layouts.partials.slots-card', ['pageLayout' => $pageLayout])
    </div>
@endsection
