<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="sticky_navbar_mode">Position</label>
        <select id="sticky_navbar_mode" name="sticky_navbar_mode" class="wb-select">
            @foreach (['sticky' => 'Sticky', 'fixed' => 'Fixed', 'static' => 'Static'] as $value => $label)
                <option value="{{ $value }}" @selected(old('sticky_navbar_mode', $block->navbarPosition()) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">Only position belongs to Navbar. Add child blocks and WebBlocks UI classes inside the wrapper for layout and styling.</div>
    </div>
</div>
