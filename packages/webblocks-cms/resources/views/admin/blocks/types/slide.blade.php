<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="name">Name</label>
        <input id="name" name="name" class="wb-input" type="text" maxlength="100" value="{{ old('name', $block->layoutAdminName()) }}">
        <div class="wb-text-sm wb-text-muted">Admin-only label used in the block tree and parent selector.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="slide_aria_label">Accessible Label</label>
        <input id="slide_aria_label" name="slide_aria_label" class="wb-input" type="text" maxlength="255" value="{{ old('slide_aria_label', $block->setting('aria_label')) }}">
        <div class="wb-text-sm wb-text-muted">Optional label for screen readers when the slide background carries meaning.</div>
    </div>

    @include('webblocks-cms::admin.blocks.types.partials.background-media-fields')

    <div class="wb-alert wb-alert-info">
        Slide is a container. Add normal content blocks inside it for visible text, buttons, cards, and layout.
    </div>
</div>
