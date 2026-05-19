@php
    $modalId = 'blockTypeEditModal-'.$blockType->id;
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $isOpen = old('_block_type_modal', request('modal')) === 'edit-block-type' && (int) old('_block_type_id', request('block_type')) === $blockType->id;
@endphp

<div class="wb-modal wb-modal-lg" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}" data-wb-admin-close-url="{{ $closeUrl }}" @if ($isOpen) data-wb-admin-autoload-overlay hidden @else hidden @endif>
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">Edit Block Type: {{ $blockType->name }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">Update the selected install-specific block type record.</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close block type edit modal">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ route('admin.block-types.update', $blockType) }}" class="wb-stack wb-gap-0" data-wb-admin-dirty-form data-wb-admin-dirty-close-confirm="Discard block type changes?">
                @csrf
                @method('PUT')
                <input type="hidden" name="return_url" value="{{ $blockTypesReturnUrl }}">
                <input type="hidden" name="_block_type_modal" value="edit-block-type">
                <input type="hidden" name="_block_type_id" value="{{ $blockType->id }}">

                <div class="wb-modal-body">
                    @include('admin.block-types._form')
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    submit-label="Save Changes"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
</div>
