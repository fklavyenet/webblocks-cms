@extends('webblocks-cms::layouts.admin', ['title' => 'Edit Slot Type', 'heading' => 'Edit Slot Type'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Edit Slot Type',
        'description' => 'Slot type management remains product-owned and is not editable from this screen.',
    ])

    <div class="wb-card">
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-alert wb-alert-info">
                <div>
                    <div class="wb-alert-title">Read only</div>
                    <div>Slot Types are maintained by the CMS core catalog. Use the Slot Types index to review them.</div>
                </div>
            </div>
        </div>
    </div>
@endsection
