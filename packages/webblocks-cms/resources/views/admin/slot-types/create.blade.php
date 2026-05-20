@extends('webblocks-cms::layouts.admin', ['title' => 'Create Slot Type', 'heading' => 'Create Slot Type'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Create Slot Type',
        'description' => 'Slot type management remains product-owned and typically does not require manual creation.',
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
