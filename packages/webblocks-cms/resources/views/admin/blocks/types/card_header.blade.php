<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="name">Name</label>
        <input id="name" name="name" class="wb-input" type="text" maxlength="100" value="{{ old('name', $block->layoutAdminName()) }}">
        <div class="wb-text-sm wb-text-muted">Admin-only label used in the block tree and parent selector.</div>
    </div>

    @include('webblocks-cms::admin.blocks.types.partials.icon-badge-fields', ['supportsBadgeLabel' => false])

    <div class="wb-text-sm wb-text-muted">Card Header is a composable region. Add nested content blocks inside it.</div>
</div>
