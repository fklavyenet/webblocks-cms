<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">Navbar Primitive</div>
            <div>Navbar renders only <code>nav.wb-navbar</code> and its child blocks. Add Container, Navbar Brand, Navbar Navigation, Header Actions, Search Form, or other compatible child blocks inside it.</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="name">Admin Label</label>
        <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $block->layoutAdminName()) }}" placeholder="Main navbar">
        <div class="wb-text-sm wb-text-muted">Editor-only label for the block tree. No public container or layout wrappers are added automatically.</div>
    </div>
</div>
