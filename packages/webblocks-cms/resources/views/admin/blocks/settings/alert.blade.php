<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="alert_variant">Variant</label>
        <select id="alert_variant" name="alert_variant" class="wb-select">
            <option value="info" @selected(old('alert_variant', $block->alertVariant()) === 'info')>Info</option>
            <option value="success" @selected(old('alert_variant', $block->alertVariant()) === 'success')>Success</option>
            <option value="warning" @selected(old('alert_variant', $block->alertVariant()) === 'warning')>Warning</option>
            <option value="danger" @selected(old('alert_variant', $block->alertVariant()) === 'danger')>Danger</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Maps only to shipped WebBlocks UI alert variants.</div>
    </div>
</div>
