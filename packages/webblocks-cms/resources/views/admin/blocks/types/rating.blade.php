@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">System Rating</div>
            <div>Rating collects lightweight 1-5 star feedback. Visible headings should be built with normal content blocks before this block.</div>
        </div>
    </div>

    <input type="hidden" name="rating_scale" value="5">

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="rating_allow_change">Vote changes</label>
            <select id="rating_allow_change" name="rating_allow_change" class="wb-select">
                <option value="1" @selected(old('rating_allow_change', ($settings['allow_change'] ?? true) ? '1' : '0') === '1')>Allow visitors to update their rating</option>
                <option value="0" @selected(old('rating_allow_change', ($settings['allow_change'] ?? true) ? '1' : '0') === '0')>Keep the first rating</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="rating_show_summary">Public summary</label>
            <select id="rating_show_summary" name="rating_show_summary" class="wb-select">
                <option value="1" @selected(old('rating_show_summary', ($settings['show_summary'] ?? true) ? '1' : '0') === '1')>Show average and count</option>
                <option value="0" @selected(old('rating_show_summary', ($settings['show_summary'] ?? true) ? '1' : '0') === '0')>Hide average and count</option>
            </select>
        </div>
    </div>
</div>
