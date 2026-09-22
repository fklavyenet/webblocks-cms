@once('webblocks-builder-items-script')
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/builder-items.js'])
    @endpush
@endonce
@once('webblocks-inline-block-builder-script')
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/inline-block-builder.js'])
    @endpush
@endonce
@once('webblocks-table-editor-script')
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/table-editor.js'])
    @endpush
@endonce
@once('webblocks-asset-picker-script')
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/asset-picker.js'])
    @endpush
@endonce
@once('webblocks-gallery-items-script')
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/gallery-items.js'])
    @endpush
@endonce

@include('webblocks-cms::admin.blocks.types.partials.rich-text-editor-assets')
@include('webblocks-cms::admin.blocks.partials.icon-picker-modal', ['context' => 'content'])
