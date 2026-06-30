@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">System Comments</div>
            <div>Comments stores moderated visitor text outside editorial block copy. Visible headings should be built with normal content blocks before this block.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="comments_form_enabled">Comment form</label>
            <select id="comments_form_enabled" name="comments_form_enabled" class="wb-select">
                <option value="1" @selected(old('comments_form_enabled', ($settings['form_enabled'] ?? true) ? '1' : '0') === '1')>Accept new comments</option>
                <option value="0" @selected(old('comments_form_enabled', ($settings['form_enabled'] ?? true) ? '1' : '0') === '0')>Close new comments</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="comments_show_approved">Approved comments</label>
            <select id="comments_show_approved" name="comments_show_approved" class="wb-select">
                <option value="1" @selected(old('comments_show_approved', ($settings['show_approved'] ?? true) ? '1' : '0') === '1')>Show approved comments</option>
                <option value="0" @selected(old('comments_show_approved', ($settings['show_approved'] ?? true) ? '1' : '0') === '0')>Hide comments publicly</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="comments_show_author_name">Author display</label>
            <select id="comments_show_author_name" name="comments_show_author_name" class="wb-select">
                <option value="0" @selected(old('comments_show_author_name', ($settings['show_author_name'] ?? false) ? '1' : '0') === '0')>Hide author names</option>
                <option value="1" @selected(old('comments_show_author_name', ($settings['show_author_name'] ?? false) ? '1' : '0') === '1')>Show author names</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="comments_sort_order">Sort order</label>
            <select id="comments_sort_order" name="comments_sort_order" class="wb-select">
                <option value="newest" @selected(old('comments_sort_order', $settings['sort_order'] ?? 'newest') === 'newest')>Newest first</option>
                <option value="oldest" @selected(old('comments_sort_order', $settings['sort_order'] ?? 'newest') === 'oldest')>Oldest first</option>
            </select>
        </div>
    </div>
</div>
