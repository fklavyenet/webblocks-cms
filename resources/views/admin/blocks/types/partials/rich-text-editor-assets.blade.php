@once
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/rich-text-editor.js'])
    @endpush

    @push('overlays')
        @include('webblocks-cms::admin.blocks.types.partials.rich-text-link-modal')
    @endpush
@endonce
