@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.comments.'.$key, $adminLocale);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('system_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="comments_form_enabled">{{ $adminText('form_label') }}</label>
            <select id="comments_form_enabled" name="comments_form_enabled" class="wb-select">
                <option value="1" @selected(old('comments_form_enabled', ($settings['form_enabled'] ?? true) ? '1' : '0') === '1')>{{ $adminText('accept_new') }}</option>
                <option value="0" @selected(old('comments_form_enabled', ($settings['form_enabled'] ?? true) ? '1' : '0') === '0')>{{ $adminText('close_new') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="comments_show_approved">{{ $adminText('approved_label') }}</label>
            <select id="comments_show_approved" name="comments_show_approved" class="wb-select">
                <option value="1" @selected(old('comments_show_approved', ($settings['show_approved'] ?? true) ? '1' : '0') === '1')>{{ $adminText('show_approved') }}</option>
                <option value="0" @selected(old('comments_show_approved', ($settings['show_approved'] ?? true) ? '1' : '0') === '0')>{{ $adminText('hide_public') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="comments_show_author_name">{{ $adminText('author_label') }}</label>
            <select id="comments_show_author_name" name="comments_show_author_name" class="wb-select">
                <option value="0" @selected(old('comments_show_author_name', ($settings['show_author_name'] ?? false) ? '1' : '0') === '0')>{{ $adminText('hide_authors') }}</option>
                <option value="1" @selected(old('comments_show_author_name', ($settings['show_author_name'] ?? false) ? '1' : '0') === '1')>{{ $adminText('show_authors') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="comments_sort_order">{{ $adminText('sort_label') }}</label>
            <select id="comments_sort_order" name="comments_sort_order" class="wb-select">
                <option value="newest" @selected(old('comments_sort_order', $settings['sort_order'] ?? 'newest') === 'newest')>{{ $adminText('newest') }}</option>
                <option value="oldest" @selected(old('comments_sort_order', $settings['sort_order'] ?? 'newest') === 'oldest')>{{ $adminText('oldest') }}</option>
            </select>
        </div>
    </div>
</div>
