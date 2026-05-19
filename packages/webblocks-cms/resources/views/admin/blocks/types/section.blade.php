<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="name">Name</label>
        <input id="name" name="name" class="wb-input" type="text" maxlength="100" value="{{ old('name', $block->layoutAdminName()) }}">
        <div class="wb-text-sm wb-text-muted">Admin-only label used in the block tree and parent selector.</div>
    </div>

    <div class="wb-text-sm wb-text-muted">This layout block has no public content fields.</div>
</div>
