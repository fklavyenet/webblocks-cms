<?php

namespace App\Http\Requests\Admin;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Media;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SharedSlot;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationRegistry;
use App\Support\Icons\IconCatalog;
use App\Support\Users\AdminAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $selectedBlockTypeId = (int) ($this->input('block_type_id') ?: $this->route('block')?->block_type_id ?: 0);
        $selectedBlockType = $selectedBlockTypeId > 0 ? BlockType::query()->find($selectedBlockTypeId) : null;
        $localeCode = Locale::normalizeCode($this->input('locale'));

        if ($selectedBlockType?->slug !== 'contact_form' || $localeCode !== null || $this->has('store_submissions')) {
            return;
        }

        $this->merge([
            'store_submissions' => '1',
        ]);
    }

    public function rules(): array
    {
        $block = $this->route('block');
        $selectedBlockTypeId = (int) ($this->input('block_type_id') ?: $block?->block_type_id ?: 0);
        $selectedBlockType = $selectedBlockTypeId > 0 ? BlockType::query()->find($selectedBlockTypeId) : null;
        $translationRegistry = app(BlockTranslationRegistry::class);
        $isTranslatedBuilderChild = in_array($selectedBlockType?->slug, ['column_item', 'feature-item', 'link-list-item'], true)
            && $translationRegistry->isTranslatable($selectedBlockType?->slug)
            && $this->filled('locale');
        $isBuilderChild = in_array($selectedBlockType?->slug, ['column_item', 'feature-item', 'link-list-item'], true);
        $isColumns = $selectedBlockType?->slug === 'columns';
        $isFeatureGrid = $selectedBlockType?->slug === 'feature-grid';
        $isLinkList = $selectedBlockType?->slug === 'link-list';
        $isNavigationAuto = in_array($selectedBlockType?->slug, ['navigation-auto', 'menu'], true);
        $isContactForm = $selectedBlockType?->slug === 'contact_form';
        $isHero = $selectedBlockType?->slug === 'hero';
        $isCode = $selectedBlockType?->slug === 'code';
        $isHeader = $selectedBlockType?->slug === 'header';
        $isPlainText = $selectedBlockType?->slug === 'plain_text';
        $isRichText = $selectedBlockType?->slug === 'rich-text';
        $isContentHeader = $selectedBlockType?->slug === 'content_header';
        $isButtonLink = $selectedBlockType?->slug === 'button_link';
        $isAlert = $selectedBlockType?->slug === 'alert';
        $isImage = $selectedBlockType?->slug === 'image';
        $isGallery = $selectedBlockType?->slug === 'gallery';
        $isDownload = $selectedBlockType?->slug === 'download';
        $isFile = $selectedBlockType?->slug === 'file';
        $isVideo = $selectedBlockType?->slug === 'video';
        $isAudio = $selectedBlockType?->slug === 'audio';
        $isBreadcrumb = $selectedBlockType?->slug === 'breadcrumb';
        $isHeaderActions = $selectedBlockType?->slug === 'header-actions';
        $isStickyNavbar = $selectedBlockType?->slug === 'sticky-navbar';
        $isNavbarBrand = $selectedBlockType?->slug === 'navbar-brand';
        $isNavbarNavigation = $selectedBlockType?->slug === 'navbar-navigation';
        $isSidebarBrand = $selectedBlockType?->slug === 'sidebar-brand';
        $isSidebarNavigation = $selectedBlockType?->slug === 'sidebar-navigation';
        $isSidebarNavItem = $selectedBlockType?->slug === 'sidebar-nav-item';
        $isSidebarNavGroup = $selectedBlockType?->slug === 'sidebar-nav-group';
        $isSidebarFooter = $selectedBlockType?->slug === 'sidebar-footer';
        $isSearchForm = $selectedBlockType?->slug === 'search-form';
        $isCluster = $selectedBlockType?->slug === 'cluster';
        $isGrid = $selectedBlockType?->slug === 'grid';
        $isCard = $selectedBlockType?->slug === 'card';
        $isStatCard = $selectedBlockType?->slug === 'stat-card';
        $supportsAlignment = $isHeader || $isPlainText || $isContentHeader;
        $supportsSectionSpacing = $selectedBlockType?->slug === 'section';
        $supportsContainerWidth = $selectedBlockType?->slug === 'container';
        $supportsClusterAlignment = $isCluster;
        $supportsClusterGap = $isCluster;
        $supportsGridColumns = $isGrid;
        $supportsGridGap = $isGrid;
        $isLayoutPrimitive = in_array($selectedBlockType?->slug, ['section', 'container', 'cluster', 'grid'], true);
        $isLocaleRequest = $this->filled('locale');
        $requiresContactCopy = $isContactForm && (! $isLocaleRequest || $this->route('block') instanceof Block);

        return [
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:blocks,id',
                Rule::notIn([$block?->id]),
            ],
            'block_type_id' => ['required', 'integer', 'exists:block_types,id'],
            'slot_type_id' => ['required', 'integer', 'exists:slot_types,id'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'locale' => ['nullable', 'string', 'regex:'.Locale::CODE_VALIDATION_PATTERN, 'exists:locales,code'],
            'title' => [($isContentHeader || $isCard || $isStatCard || $isDownload || $isSidebarNavItem || $isSidebarNavGroup || $isSearchForm) ? 'required' : (($isBuilderChild || ($isLocaleRequest && $isTranslatedBuilderChild)) ? 'required' : 'nullable'), 'string', 'max:255'],
            'eyebrow' => [$isCard ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'content' => [($isAlert || $isBuilderChild || ($isLocaleRequest && $isTranslatedBuilderChild) || $isSearchForm) ? 'required' : 'nullable', 'string'],
            'text' => [($isHeader || $isPlainText) ? 'required' : 'nullable', 'string'],
            'level' => [$isHeader ? 'required' : 'nullable', Rule::in(['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])],
            'anchor' => [$isHeader ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'name' => [($isLayoutPrimitive || $isStickyNavbar || $isSidebarNavigation || $isSidebarNavGroup) ? 'nullable' : 'prohibited', 'string', 'max:100'],
            'alignment' => [$supportsAlignment ? 'nullable' : 'prohibited', Rule::in(['', 'left', 'center', 'right'])],
            'spacing' => [$supportsSectionSpacing ? 'nullable' : 'prohibited', Rule::in(['', 'sm', 'lg'])],
            'width' => [$supportsContainerWidth ? 'nullable' : 'prohibited', Rule::in(['', 'sm', 'md', 'lg', 'xl', 'full'])],
            'container_flow' => [$supportsContainerWidth ? 'nullable' : 'prohibited', Rule::in(['', 'none', 'stack'])],
            'cluster_gap' => [$supportsClusterGap ? 'nullable' : 'prohibited', Rule::in(['', 'none', 'xs', 'sm', 'md', 'lg'])],
            'cluster_justify' => [$supportsClusterAlignment ? 'nullable' : 'prohibited', Rule::in(['', 'start', 'center', 'end', 'between'])],
            'cluster_align' => [$supportsClusterAlignment ? 'nullable' : 'prohibited', Rule::in(['', 'start', 'center', 'end', 'stretch'])],
            'cluster_wrap' => [$supportsClusterAlignment ? 'nullable' : 'prohibited', Rule::in(['', 'wrap', 'nowrap'])],
            'cluster_width' => [$supportsClusterAlignment ? 'nullable' : 'prohibited', Rule::in(['', 'auto', 'full'])],
            'grid_columns' => [$supportsGridColumns ? 'nullable' : 'prohibited', Rule::in(['2', '3', '4'])],
            'grid_gap' => [$supportsGridGap ? 'nullable' : 'prohibited', Rule::in(['', '3', '4', '6'])],
            'intro_text' => [$isContentHeader ? 'nullable' : 'prohibited', 'string'],
            'meta_items' => [$isContentHeader ? 'nullable' : 'prohibited', 'array'],
            'meta_items.*' => [$isContentHeader ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'title_level' => ['prohibited'],
            'url' => [(($isButtonLink || $selectedBlockType?->slug === 'link-list-item' || $isSidebarNavItem) && ! $isLocaleRequest) ? 'required' : 'nullable', 'string', 'max:2048'],
            'label' => [$isButtonLink ? 'required' : 'prohibited', 'string', 'max:255'],
            'target' => [($isButtonLink || $isNavbarBrand || $isSidebarBrand || $isSidebarNavItem) ? 'nullable' : 'prohibited', Rule::in(['_self', '_blank'])],
            'action_label' => [$isCard ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'card_url' => [$isCard ? 'nullable' : 'prohibited', 'string', 'max:2048'],
            'card_target' => [$isCard ? 'nullable' : 'prohibited', Rule::in(['_self', '_blank'])],
            'card_variant' => [$isCard ? 'nullable' : 'prohibited', Rule::in(['default', 'promo'])],
            'image_alt' => [$isCard ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'image_caption' => [$isCard ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'image_position' => [$isCard ? 'nullable' : 'prohibited', Rule::in(['none', 'top', 'middle', 'bottom'])],
            'image_align' => [$isCard ? 'nullable' : 'prohibited', Rule::in(['start', 'center', 'end', 'stretch'])],
            'image_aspect' => [$isCard ? 'nullable' : 'prohibited', Rule::in(['auto', 'square', 'wide', 'portrait'])],
            'alert_variant' => [$isAlert ? 'nullable' : 'prohibited', Rule::in(['info', 'success', 'warning', 'danger'])],
            'layout' => [$isHero ? 'nullable' : 'nullable', 'string', 'max:255'],
            'title_tag' => [$isHero ? 'nullable' : 'nullable', Rule::in(['h1', 'h2', 'h3'])],
            'language' => [$isCode ? 'nullable' : 'nullable', 'string', 'max:255'],
            'breadcrumb_home_label' => [$isBreadcrumb ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'breadcrumb_include_current' => [$isBreadcrumb ? 'nullable' : 'prohibited', Rule::in(['0', '1'])],
            'primary_cta_label' => ['nullable', 'string', 'max:255'],
            'primary_cta_url' => ['nullable', 'string', 'max:2048'],
            'secondary_cta_label' => ['nullable', 'string', 'max:255'],
            'secondary_cta_url' => ['nullable', 'string', 'max:2048'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'asset_id' => ['nullable', 'integer', 'exists:media,id'],
            'gallery_media_ids' => ['nullable', 'array'],
            'gallery_media_ids.*' => ['integer', 'exists:media,id'],
            'gallery_asset_ids' => ['nullable', 'array'],
            'gallery_asset_ids.*' => ['integer', 'exists:media,id'],
            'gallery_items' => [$isGallery ? 'nullable' : 'prohibited', 'array'],
            'gallery_items.*.media_id' => [$isGallery ? 'required' : 'prohibited', 'integer', 'exists:media,id'],
            'gallery_items.*.sort_order' => [$isGallery ? 'nullable' : 'prohibited', 'integer', 'min:0'],
            'gallery_items.*.alt_text' => [$isGallery ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'gallery_items.*.caption' => [$isGallery ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'gallery_items.*.overlay_title' => [$isGallery ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'gallery_items.*.overlay_text' => [$isGallery ? 'nullable' : 'prohibited', 'string'],
            'gallery_variant' => [$isGallery ? 'nullable' : 'prohibited', Rule::in(['grid', 'masonry', 'masonary', 'collage'])],
            'gallery_columns' => [$isGallery ? 'nullable' : 'prohibited', Rule::in(['2', '3', '4', '5'])],
            'gallery_gap' => [$isGallery ? 'nullable' : 'prohibited', Rule::in(['none', 'sm', 'md', 'lg'])],
            'gallery_aspect_ratio' => [$isGallery ? 'nullable' : 'prohibited', Rule::in(['auto', 'square', '4:3', '16:9', 'portrait'])],
            'gallery_captions_mode' => [$isGallery ? 'nullable' : 'prohibited', Rule::in(['hidden', 'below', 'overlay', 'on-hover'])],
            'gallery_overlay_mode' => [$isGallery ? 'nullable' : 'prohibited', Rule::in(['none', 'gradient', 'solid'])],
            'gallery_lightbox_enabled' => [$isGallery ? 'nullable' : 'prohibited', 'boolean'],
            'attachment_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'attachment_asset_id' => ['nullable', 'integer', 'exists:media,id'],
            'column_items' => ['nullable', 'array'],
            'column_items.*.id' => ['nullable', 'integer', 'exists:blocks,id'],
            'column_items.*.block_type_id' => ['nullable', 'integer', 'exists:block_types,id'],
            'column_items.*.title' => ['nullable', 'string', 'max:255'],
            'column_items.*.content' => ['nullable', 'string'],
            'column_items.*.url' => ['nullable', 'string', 'max:2048'],
            'column_items.*.status' => ['nullable', Rule::in(['draft', 'published'])],
            'column_items.*.is_system' => ['nullable', 'boolean'],
            'column_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'column_items.*._delete' => ['nullable', 'boolean'],
            'feature_items' => ['nullable', 'array'],
            'feature_items.*.id' => ['nullable', 'integer', 'exists:blocks,id'],
            'feature_items.*.block_type_id' => ['nullable', 'integer', 'exists:block_types,id'],
            'feature_items.*.title' => ['nullable', 'string', 'max:255'],
            'feature_items.*.content' => ['nullable', 'string'],
            'feature_items.*.url' => ['nullable', 'string', 'max:2048'],
            'feature_items.*.status' => ['nullable', Rule::in(['draft', 'published'])],
            'feature_items.*.is_system' => ['nullable', 'boolean'],
            'feature_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'feature_items.*._delete' => ['nullable', 'boolean'],
            'link_list_items' => ['nullable', 'array'],
            'link_list_items.*.id' => ['nullable', 'integer', 'exists:blocks,id'],
            'link_list_items.*.block_type_id' => ['nullable', 'integer', 'exists:block_types,id'],
            'link_list_items.*.title' => ['nullable', 'string', 'max:255'],
            'link_list_items.*.subtitle' => ['nullable', 'string', 'max:255'],
            'link_list_items.*.content' => ['nullable', 'string'],
            'link_list_items.*.url' => ['nullable', 'string', 'max:2048'],
            'link_list_items.*.status' => ['nullable', Rule::in(['draft', 'published'])],
            'link_list_items.*.is_system' => ['nullable', 'boolean'],
            'link_list_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'link_list_items.*._delete' => ['nullable', 'boolean'],
            'variant' => [($isLayoutPrimitive || $isContentHeader || $isBreadcrumb) ? 'prohibited' : 'nullable', ($isButtonLink || $isDownload) ? Rule::in(['primary', 'secondary', 'ghost']) : 'string', 'max:255'],
            'meta' => [$isLayoutPrimitive ? 'prohibited' : 'nullable', 'string'],
            'settings' => [$isLayoutPrimitive ? 'prohibited' : 'nullable', 'string'],
            'heading' => [$isContactForm ? 'nullable' : 'nullable', 'string', 'max:255'],
            'intro_text' => [$isContactForm ? 'nullable' : 'nullable', 'string'],
            'submit_label' => [$requiresContactCopy ? 'required' : 'nullable', 'string', 'max:255'],
            'success_message' => [$requiresContactCopy ? 'required' : 'nullable', 'string', 'max:1000'],
            'recipient_email' => [($isContactForm && ! $isLocaleRequest) ? 'nullable' : 'nullable', 'email:rfc', 'max:255'],
            'send_email_notification' => [($isContactForm && ! $isLocaleRequest) ? 'required' : 'nullable', 'boolean'],
            'store_submissions' => [($isContactForm && ! $isLocaleRequest) ? 'required' : 'nullable', 'boolean'],
            'navigation_menu_key' => [$isNavigationAuto ? 'required' : 'nullable', Rule::in(NavigationItem::menuKeys())],
            'header_actions_show_mode_toggle' => [$isHeaderActions ? 'nullable' : 'prohibited', 'boolean'],
            'header_actions_show_accent_toggle' => [$isHeaderActions ? 'nullable' : 'prohibited', 'boolean'],
            'header_actions_show_search' => [$isHeaderActions ? 'nullable' : 'prohibited', 'boolean'],
            'sticky_navbar_mode' => [$isStickyNavbar ? 'nullable' : 'prohibited', Rule::in(['sticky', 'fixed', 'static'])],
            'navbar_brand_aria_label' => [$isNavbarBrand ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'sidebar_brand_aria_label' => [$isSidebarBrand ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'navbar_navigation_menu_key' => [$isNavbarNavigation ? 'required' : 'prohibited', Rule::in(NavigationItem::menuKeys())],
            'sidebar_navigation_menu_key' => [$isSidebarNavigation ? 'nullable' : 'prohibited', Rule::in(array_merge([''], NavigationItem::menuKeys()))],
            'sidebar_navigation_show_icons' => [$isSidebarNavigation ? 'nullable' : 'prohibited', 'boolean'],
            'sidebar_navigation_active_matching' => [$isSidebarNavigation ? 'nullable' : 'prohibited', Rule::in(['path', 'current-page', 'exact'])],
            'sidebar_nav_item_icon' => [$isSidebarNavItem ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'sidebar_nav_item_active_mode' => [$isSidebarNavItem ? 'nullable' : 'prohibited', Rule::in(['exact', 'path', 'current-page', 'manual'])],
            'sidebar_nav_item_manual_active' => [$isSidebarNavItem ? 'nullable' : 'prohibited', 'boolean'],
            'sidebar_nav_group_icon' => [$isSidebarNavGroup ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'sidebar_nav_group_initially_open' => [$isSidebarNavGroup ? 'nullable' : 'prohibited', 'boolean'],
            'sidebar_footer_variant' => [$isSidebarFooter ? 'nullable' : 'prohibited', Rule::in(['info', 'success', 'warning', 'danger'])],
            'show_button' => [$isSearchForm ? 'nullable' : 'prohibited', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $page = Page::query()->with('site.locales')->find($this->integer('page_id'));
            $localeCode = Locale::normalizeCode($this->input('locale'));

            if ($localeCode !== null && (! $page || ! $page->site || ! $page->site->hasEnabledLocale($localeCode))) {
                $validator->errors()->add('locale', 'Selected locale must be enabled for the page site.');
            }

            $parentId = $this->has('parent_id')
                ? $this->integer('parent_id')
                : (int) ($this->route('block')?->parent_id ?: 0);
            $existingBlock = $this->route('block');
            $existingBlock = $existingBlock instanceof Block ? $existingBlock : null;
            $selectedBlockTypeId = (int) ($this->input('block_type_id') ?: $this->route('block')?->block_type_id ?: 0);
            $selectedBlockType = $selectedBlockTypeId > 0 ? BlockType::query()->find($selectedBlockTypeId) : null;
            $isColumns = $selectedBlockType?->slug === 'columns';
            $isFeatureGrid = $selectedBlockType?->slug === 'feature-grid';
            $isLinkList = $selectedBlockType?->slug === 'link-list';

            if ($selectedBlockType?->slug === 'button_link') {
                $url = trim((string) $this->input('url', ''));

                if ($url !== '' && ! preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $url)) {
                    $validator->errors()->add('url', 'Button link URL must be a full URL, site path, anchor, mailto link, or telephone link.');
                }
            }

            if ($selectedBlockType?->slug === 'stat-card') {
                $url = trim((string) $this->input('url', ''));

                if ($url !== '' && ! preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $url)) {
                    $validator->errors()->add('url', 'Stat card URL must be a full URL, site path, anchor, mailto link, or telephone link.');
                }
            }

            if ($selectedBlockType?->slug === 'search-form') {
                if (blank($this->input('content'))) {
                    $validator->errors()->add('content', 'Search placeholder is required.');
                }
            }

            if ($selectedBlockType?->slug === 'header') {
                $anchor = trim((string) $this->input('anchor', ''));

                if ($anchor !== '' && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_:.]*$/', $anchor)) {
                    $validator->errors()->add('anchor', 'Anchor ID must start with a letter or number and may contain letters, numbers, dashes, underscores, colons, or periods.');
                }
            }

            if ($selectedBlockType?->slug === 'link-list-item') {
                $url = trim((string) $this->input('url', ''));

                if ($url !== '' && ! $this->isAllowedLinkListItemUrl($url)) {
                    $validator->errors()->add('url', 'Link list item URL must be a full URL, site path, relative docs path, anchor, mailto link, or telephone link.');
                }
            }

            if (in_array($selectedBlockType?->slug, ['navbar-brand', 'sidebar-brand', 'sidebar-nav-item'], true)) {
                $url = trim((string) $this->input('url', ''));

                if ($url !== '' && ! $this->isAllowedLinkListItemUrl($url)) {
                    $validator->errors()->add('url', 'URL must be a full URL, site path, relative docs path, anchor, mailto link, or telephone link.');
                }
            }

            if (in_array($selectedBlockType?->slug, ['navbar-brand', 'sidebar-brand'], true) && ($this->filled('media_id') || $this->filled('asset_id'))) {
                $asset = Media::query()->find((int) ($this->input('media_id') ?: $this->input('asset_id')));

                if (! $asset?->isImage()) {
                    $validator->errors()->add('media_id', 'Brand logo must be an image from Media.');
                }
            }

            if ($selectedBlockType?->slug === 'navbar-brand') {
                $hasTitle = trim((string) $this->input('title', '')) !== '';
                $hasLogo = (int) ($this->input('media_id') ?: $this->input('asset_id') ?: 0) > 0;

                if (! $hasTitle && ! $hasLogo) {
                    $validator->errors()->add('title', 'Navbar Brand requires visible title text or a logo image.');
                }
            }

            if ($selectedBlockType?->slug === 'sidebar-brand') {
                $hasTitle = trim((string) $this->input('title', '')) !== '';
                $hasLogo = (int) ($this->input('media_id') ?: $this->input('asset_id') ?: 0) > 0;

                if (! $hasTitle && ! $hasLogo) {
                    $validator->errors()->add('title', 'Sidebar Brand requires visible title text or a logo image.');
                }
            }

            if ($selectedBlockType?->slug === 'navbar-navigation' && $page && $page->site && ! NavigationItem::query()->forSite($page->site_id)->forMenu((string) $this->input('navbar_navigation_menu_key'))->visible()->exists()) {
                $validator->errors()->add('navbar_navigation_menu_key', 'Selected navigation menu has no visible items for this site yet. Create navigation items first.');
            }

            if ($selectedBlockType?->slug === 'sidebar-nav-item') {
                $icon = app(IconCatalog::class)->normalizeSlug($this->input('sidebar_nav_item_icon'));
                $currentIcon = $existingBlock ? app(IconCatalog::class)->normalizeSlug($existingBlock->sidebarNavItemIcon()) : null;

                if (! app(IconCatalog::class)->isValidNavigationSelection($icon, $currentIcon)) {
                    $validator->errors()->add('sidebar_nav_item_icon', 'Select an active navigation icon from the catalog.');
                }
            }

            if ($selectedBlockType?->slug === 'sidebar-nav-group') {
                $icon = app(IconCatalog::class)->normalizeSlug($this->input('sidebar_nav_group_icon'));
                $currentIcon = $existingBlock ? app(IconCatalog::class)->normalizeSlug($existingBlock->sidebarNavItemIcon()) : null;

                if (! app(IconCatalog::class)->isValidNavigationSelection($icon, $currentIcon)) {
                    $validator->errors()->add('sidebar_nav_group_icon', 'Select an active navigation icon from the catalog.');
                }
            }

            if ($selectedBlockType?->slug === 'card') {
                $url = trim((string) $this->input('card_url', ''));

                if ($url !== '' && ! preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $url)) {
                    $validator->errors()->add('card_url', 'Card URL must be a full URL, site path, anchor, mailto link, or telephone link.');
                }

                if ($this->filled('media_id') || $this->filled('asset_id')) {
                    $asset = Media::query()->find((int) ($this->input('media_id') ?: $this->input('asset_id')));

                    if (! $asset?->isImage()) {
                        $validator->errors()->add('media_id', 'Card media must be an image from Media.');
                    }
                }
            }

            if ($selectedBlockType?->slug === 'image') {
                $url = trim((string) $this->input('url', ''));

                if ($url !== '' && ! preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $url)) {
                    $validator->errors()->add('url', 'Image link URL must be a full URL, site path, anchor, mailto link, or telephone link.');
                }

                if ($this->filled('media_id') || $this->filled('asset_id')) {
                    $asset = Media::query()->find((int) ($this->input('media_id') ?: $this->input('asset_id')));

                    if (! $asset?->isImage()) {
                        $validator->errors()->add('media_id', 'Image block media must be an image from Media.');
                    }
                }
            }

            if ($selectedBlockType?->slug === 'gallery') {
                $galleryMediaIds = collect($this->input('gallery_items', []))
                    ->pluck('media_id')
                    ->whenEmpty(fn ($items) => $items->merge($this->input('gallery_media_ids', $this->input('gallery_asset_ids', []))))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values();

                if ($galleryMediaIds->isNotEmpty()) {
                    $invalidGalleryMediaExists = Media::query()
                        ->whereIn('id', $galleryMediaIds)
                        ->where('kind', '!=', Media::KIND_IMAGE)
                        ->exists();

                    if ($invalidGalleryMediaExists) {
                        $validator->errors()->add('gallery_media_ids', 'Gallery items must be images from Media.');
                    }
                }
            }

            if (in_array($selectedBlockType?->slug, ['download', 'file', 'video', 'audio'], true)) {
                $url = trim((string) $this->input('url', ''));

                if ($url !== '' && ! preg_match('/^https?:\/\//i', $url)) {
                    $validator->errors()->add('url', 'External media URL must be a full HTTP or HTTPS URL.');
                }
            }

            if (in_array($selectedBlockType?->slug, ['download', 'file'], true) && ($this->filled('media_id') || $this->filled('asset_id'))) {
                $asset = Media::query()->find((int) ($this->input('media_id') ?: $this->input('asset_id')));

                if (! $asset || ! in_array($asset->kind, [Media::KIND_DOCUMENT, Media::KIND_OTHER], true)) {
                    $validator->errors()->add('media_id', 'File and download media must be a document or file from Media.');
                }
            }

            if ($selectedBlockType?->slug === 'video' && ($this->filled('media_id') || $this->filled('asset_id'))) {
                $asset = Media::query()->find((int) ($this->input('media_id') ?: $this->input('asset_id')));

                if (! $asset?->isVideo()) {
                    $validator->errors()->add('media_id', 'Video block media must be a video from Media.');
                }
            }

            if (! $parentId) {
                if (in_array($selectedBlockType?->slug, ['navbar-brand', 'navbar-navigation', 'sidebar-nav-item', 'sidebar-nav-group'], true)) {
                    $validator->errors()->add('parent_id', 'This block type requires a supported parent block.');

                    return;
                }

                if (! in_array($selectedBlockType?->slug, ['columns', 'feature-grid', 'link-list'], true)) {
                    return;
                }
            }

            if ($isColumns) {
                foreach ($this->input('column_items', []) as $index => $columnItem) {
                    if ((bool) ($columnItem['_delete'] ?? false)) {
                        continue;
                    }

                    if (blank($columnItem['title'] ?? null)) {
                        $validator->errors()->add("column_items.{$index}.title", 'Column item title is required.');
                    }

                    if (blank($columnItem['content'] ?? null)) {
                        $validator->errors()->add("column_items.{$index}.content", 'Column item text is required.');
                    }
                }
            }

            if ($isFeatureGrid) {
                foreach ($this->input('feature_items', []) as $index => $featureItem) {
                    if ((bool) ($featureItem['_delete'] ?? false)) {
                        continue;
                    }

                    if (blank($featureItem['title'] ?? null)) {
                        $validator->errors()->add("feature_items.{$index}.title", 'Feature item title is required.');
                    }

                    if (blank($featureItem['content'] ?? null)) {
                        $validator->errors()->add("feature_items.{$index}.content", 'Feature item text is required.');
                    }
                }
            }

            if ($isLinkList) {
                foreach ($this->input('link_list_items', []) as $index => $item) {
                    if ((bool) ($item['_delete'] ?? false)) {
                        continue;
                    }

                    if (blank($item['title'] ?? null)) {
                        $validator->errors()->add("link_list_items.{$index}.title", 'Link list item title is required.');
                    }

                    if (blank($item['subtitle'] ?? null)) {
                        $validator->errors()->add("link_list_items.{$index}.subtitle", 'Link list item meta is required.');
                    }

                    if (blank($item['content'] ?? null)) {
                        $validator->errors()->add("link_list_items.{$index}.content", 'Link list item description is required.');
                    }

                    if (blank($item['url'] ?? null)) {
                        $validator->errors()->add("link_list_items.{$index}.url", 'Link list item URL is required.');
                    }
                }
            }

            if (! $parentId) {
                return;
            }

            $parent = Block::query()->with(['parent', 'blockType'])->find($parentId);
            $block = $this->route('block');

            if (! $parent || $parent->page_id !== $this->integer('page_id')) {
                $validator->errors()->add('parent_id', 'Parent block must belong to the same page.');

                return;
            }

            if (! $parent->canAcceptChildren()) {
                $validator->errors()->add('parent_id', 'Selected parent block cannot accept child blocks.');

                return;
            }

            if ($selectedBlockType && ! $parent->canAcceptChildType($selectedBlockType->slug)) {
                $validator->errors()->add('parent_id', $selectedBlockType->name.' blocks cannot be placed inside '.$parent->typeName().'.');

                return;
            }

            if ($selectedBlockType?->slug === 'link-list-item' && ! $parent->isLinkList()) {
                $validator->errors()->add('parent_id', 'Link list items can only be placed under a link-list block.');

                return;
            }

            if (in_array($selectedBlockType?->slug, ['navbar-brand', 'navbar-navigation'], true) && ! $parent->hasNavbarAncestorOrSelf()) {
                $validator->errors()->add('parent_id', 'Navbar Brand and Navbar Navigation blocks can only be placed under Navbar blocks.');

                return;
            }

            if ($selectedBlockType?->slug === 'sidebar-nav-item' && ! ($parent->isSidebarNavigation() || $parent->isSidebarNavGroup())) {
                $validator->errors()->add('parent_id', 'Sidebar nav items can only be placed under Sidebar Navigation or Sidebar Nav Group blocks.');

                return;
            }

            if ($selectedBlockType?->slug === 'sidebar-nav-group' && ! $parent->isSidebarNavigation()) {
                $validator->errors()->add('parent_id', 'Sidebar nav groups can only be placed under Sidebar Navigation blocks.');

                return;
            }

            if (! $block) {
                return;
            }

            if ($parent->id === $block->id) {
                $validator->errors()->add('parent_id', 'A block cannot be its own parent.');

                return;
            }

            $cursor = $parent;

            while ($cursor) {
                if ($cursor->id === $block->id) {
                    $validator->errors()->add('parent_id', 'A block cannot be moved under its own child tree.');

                    return;
                }

                $cursor = $cursor->parent;
            }
        }];
    }

    public function messages(): array
    {
        return [
            'locale.regex' => 'Use a valid locale code.',
            'locale.exists' => 'Selected locale is invalid.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        $slotRedirectUrl = $this->slotEditorRedirectUrl();

        if ($slotRedirectUrl !== null) {
            return $slotRedirectUrl;
        }

        return parent::getRedirectUrl();
    }

    private function isAllowedLinkListItemUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (preg_match('/\s/', $url) === 1) {
            return false;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url) === 1) {
            $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));

            if (in_array($scheme, ['http', 'https'], true)) {
                return filter_var($url, FILTER_VALIDATE_URL) !== false;
            }

            if ($scheme === 'mailto') {
                $target = substr($url, strlen('mailto:'));

                return $target !== '';
            }

            if ($scheme === 'tel') {
                $target = substr($url, strlen('tel:'));

                return $target !== '';
            }

            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        if (str_starts_with($url, '#')) {
            return strlen($url) > 1;
        }

        if (preg_match('/^[A-Za-z0-9._\/-]+(?:\?[A-Za-z0-9._~!$&\'()*+,;=:@%\/-]*)?(?:#[A-Za-z0-9._~!$&\'()*+,;=:@%\/-]*)?$/', $url) !== 1) {
            return false;
        }

        return ! str_starts_with($url, '//');
    }

    private function slotEditorRedirectUrl(): ?string
    {
        $mode = trim((string) $this->input('_slot_block_mode', ''));

        if (! in_array($mode, ['create', 'edit'], true)) {
            return null;
        }

        $page = $this->route('block')?->page;

        if (! $page instanceof Page) {
            $page = Page::query()->find($this->integer('page_id'));
        }

        if (! $page instanceof Page) {
            return null;
        }

        $sharedSlot = $this->requestedSharedSlot($page);
        $locale = Locale::normalizeCode($this->input('locale'));
        $returnUrl = trim((string) $this->input('return_url', ''));
        $parameters = array_filter([
            'locale' => $locale,
            'return_url' => $returnUrl !== '' ? $returnUrl : null,
        ], fn (mixed $value) => $value !== null && $value !== '');

        if ($mode === 'create') {
            $parameters['picker'] = 1;
            $parameters['block_type_id'] = $this->integer('block_type_id') ?: null;
            $parameters['parent_id'] = $this->filled('parent_id') ? $this->integer('parent_id') : null;
        }

        if ($mode === 'edit') {
            $block = $this->route('block');

            if (! $block instanceof Block) {
                return null;
            }

            $parameters['edit'] = $block->id;
        }

        $parameters = array_filter($parameters, fn (mixed $value) => $value !== null && $value !== '');

        if ($sharedSlot instanceof SharedSlot) {
            return route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot] + $parameters);
        }

        $slotTypeId = $this->integer('slot_type_id') ?: (int) ($this->route('block')?->slot_type_id ?: 0);
        $pageSlot = $slotTypeId > 0
            ? PageSlot::query()
                ->where('page_id', $page->id)
                ->where('slot_type_id', $slotTypeId)
                ->first()
            : null;

        if (! $pageSlot instanceof PageSlot) {
            return null;
        }

        return route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot] + $parameters);
    }

    private function requestedSharedSlot(Page $page): ?SharedSlot
    {
        $sharedSlotId = $this->integer('shared_slot_id');

        if ($sharedSlotId > 0) {
            return SharedSlot::query()->find($sharedSlotId);
        }

        if (! $page->isSharedSlotSourcePage()) {
            return null;
        }

        $sourceSharedSlotId = (int) data_get($page->settings, 'shared_slot_id');

        return $sourceSharedSlotId > 0
            ? SharedSlot::query()->find($sourceSharedSlotId)
            : null;
    }

    public function validatedData(): array
    {
        /** @var AdminAuthorization $authorization */
        $authorization = app(AdminAuthorization::class);
        $data = $this->validated();
        $existingBlock = $this->route('block');
        $existingBlock = $existingBlock instanceof Block ? $existingBlock : null;
        $data['locale'] = Locale::normalizeCode($data['locale'] ?? null);
        $pageId = (int) $data['page_id'];

        if (! empty($data['parent_id'])) {
            $parentMatchesPage = Block::query()
                ->whereKey($data['parent_id'])
                ->where('page_id', $pageId)
                ->exists();

            if (! $parentMatchesPage) {
                $data['parent_id'] = null;
            }
        }

        $settings = trim((string) ($data['settings'] ?? ''));
        $data['settings'] = $settings === '' ? null : $settings;
        $meta = trim((string) ($data['meta'] ?? ''));
        $data['meta'] = $meta === '' ? null : $meta;
        $data['media_id'] = $authorization->normalizeAllowedMediaId($this->user(), ! empty($data['media_id']) ? (int) $data['media_id'] : (! empty($data['asset_id']) ? (int) $data['asset_id'] : null));

        if ($data['media_id'] === null && $existingBlock && ! $this->has('media_id') && ! $this->has('asset_id')) {
            $data['media_id'] = $existingBlock->media_id;
        }

        if ($data['locale'] !== null && $existingBlock && ($existingBlock->typeSlug() === 'card') && $data['media_id'] === null) {
            $data['media_id'] = $existingBlock->media_id;
        }

        $submittedGalleryItems = collect($data['gallery_items'] ?? [])
            ->map(function (array $item, int $index): array {
                return [
                    'media_id' => (int) ($item['media_id'] ?? 0),
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                    'alt_text' => trim((string) ($item['alt_text'] ?? '')) ?: null,
                    'caption' => trim((string) ($item['caption'] ?? '')) ?: null,
                    'overlay_title' => trim((string) ($item['overlay_title'] ?? '')) ?: null,
                    'overlay_text' => trim((string) ($item['overlay_text'] ?? '')) ?: null,
                ];
            })
            ->sortBy('sort_order')
            ->values();

        $galleryAssetIds = $authorization->filterAllowedMediaIds(
            $this->user(),
            $submittedGalleryItems->isNotEmpty()
                ? $submittedGalleryItems->pluck('media_id')->all()
                : ($data['gallery_media_ids'] ?? $data['gallery_asset_ids'] ?? []),
        );
        $attachmentAssetId = $authorization->normalizeAllowedMediaId($this->user(), ! empty($data['attachment_media_id']) ? (int) $data['attachment_media_id'] : (! empty($data['attachment_asset_id']) ? (int) $data['attachment_asset_id'] : null));

        $decodedSettings = [];

        if (! empty($data['settings'])) {
            $parsedSettings = json_decode((string) $data['settings'], true);
            $decodedSettings = is_array($parsedSettings) ? $parsedSettings : [];
        }

        $data['settings'] = $decodedSettings === []
            ? null
            : json_encode($decodedSettings, JSON_UNESCAPED_SLASHES);

        unset($data['asset_id']);
        unset($data['gallery_asset_ids']);
        unset($data['gallery_media_ids']);
        unset($data['gallery_items']);
        unset($data['attachment_asset_id']);
        unset($data['attachment_media_id']);

        $data['_block_media'] = [
            'gallery_item' => $galleryAssetIds,
            'attachment' => $attachmentAssetId ? [$attachmentAssetId] : [],
        ];
        $data['_gallery_items'] = $submittedGalleryItems
            ->filter(fn (array $item) => in_array($item['media_id'], $galleryAssetIds, true))
            ->values()
            ->all();

        if (! empty($data['block_type_id'])) {
            $blockType = BlockType::query()->find($data['block_type_id']);
            $data['type'] = $blockType?->slug;
            $data['source_type'] = $blockType?->source_type ?? 'static';
            $data['is_system'] = (bool) ($blockType?->is_system ?? false);

            if ($blockType?->slug === 'hero') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedHeroEdit = $data['locale'] !== null;

                $layout = trim((string) ($data['layout'] ?? ''));
                $titleTag = trim((string) ($data['title_tag'] ?? ''));

                $settings = $existingSettings;

                if (! $isTranslatedHeroEdit) {
                    $settings['layout'] = $layout !== '' ? $layout : null;
                    $settings['title_tag'] = in_array($titleTag, ['h1', 'h2', 'h3'], true) ? $titleTag : null;
                }

                $data['url'] = null;
                $data['variant'] = $isTranslatedHeroEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (trim((string) ($data['variant'] ?? '')) ?: null);
                $data['settings'] = json_encode(array_filter($settings, fn ($value) => $value !== null && $value !== '' && $value !== []), JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }
            }

            if ($blockType?->slug === 'cta') {
                $isTranslatedCtaEdit = $data['locale'] !== null;

                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = $isTranslatedCtaEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (trim((string) ($data['variant'] ?? '')) ?: null);
            }

            if ($blockType?->slug === 'code') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedCodeEdit = $data['locale'] !== null;
                $language = trim((string) ($data['language'] ?? ''));
                $settings = $existingSettings;

                unset($settings['lang']);

                if (! $isTranslatedCodeEdit) {
                    $settings['language'] = $language !== '' ? $language : null;
                }

                $data['url'] = null;
                $data['asset_id'] = null;
                $data['settings'] = json_encode(array_filter($settings, fn ($value) => $value !== null && $value !== '' && $value !== []), JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = is_string($data['content'] ?? null)
                    ? $data['content']
                    : null;
                $data['variant'] = null;
                $data['meta'] = null;
            }

            if ($blockType?->slug === 'table') {
                $isTranslatedTableEdit = $data['locale'] !== null;
                $variant = trim((string) ($data['variant'] ?? 'header-row'));

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = is_string($data['content'] ?? null)
                    ? $data['content']
                    : null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
                $data['variant'] = $isTranslatedTableEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (in_array($variant, ['header-row', 'plain'], true) ? $variant : 'header-row');
            }

            if (in_array($blockType?->slug, ['navigation-auto', 'menu'], true)) {
                $data['title'] = null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['asset_id'] = null;
                $data['settings'] = json_encode([
                    'menu_key' => $data['navigation_menu_key'] ?? NavigationItem::MENU_PRIMARY,
                ], JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'contact_form') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedContactFormEdit = $data['locale'] !== null;

                $data['title'] = trim((string) ($data['heading'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = trim((string) ($data['intro_text'] ?? '')) ?: null;
                $data['url'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['asset_id'] = null;
                $data['settings'] = json_encode([
                    'recipient_email' => $isTranslatedContactFormEdit
                        ? ($existingSettings['recipient_email'] ?? null)
                        : (trim((string) ($data['recipient_email'] ?? '')) ?: null),
                    'send_email_notification' => $isTranslatedContactFormEdit
                        ? (bool) ($existingSettings['send_email_notification'] ?? true)
                        : (bool) ($data['send_email_notification'] ?? true),
                    'store_submissions' => $isTranslatedContactFormEdit
                        ? (bool) ($existingSettings['store_submissions'] ?? true)
                        : (bool) ($data['store_submissions'] ?? true),
                ], JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'header') {
                $isTranslatedHeaderEdit = $data['locale'] !== null;
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;
                $alignment = trim((string) ($data['alignment'] ?? ''));
                $anchor = trim((string) ($data['anchor'] ?? ''));

                if (! $isTranslatedHeaderEdit) {
                    if (in_array($alignment, ['left', 'center', 'right'], true)) {
                        $settings['alignment'] = $alignment;
                    } else {
                        unset($settings['alignment']);
                    }

                    if ($anchor !== '') {
                        $settings['anchor'] = $anchor;
                    } else {
                        unset($settings['anchor']);
                    }
                }

                $data['title'] = trim((string) ($data['text'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = $isTranslatedHeaderEdit
                    ? ($this->route('block')?->getRawOriginal('url'))
                    : ($anchor !== '' ? $anchor : null);
                $data['asset_id'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === []
                    ? null
                    : json_encode($settings, JSON_UNESCAPED_SLASHES);
                $data['variant'] = $isTranslatedHeaderEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (trim((string) ($data['level'] ?? '')) ?: 'h2');
            }

            if ($blockType?->slug === 'content_header') {
                $isTranslatedContentHeaderEdit = $data['locale'] !== null;
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;
                $alignment = trim((string) ($data['alignment'] ?? ''));
                $metaItems = collect($data['meta_items'] ?? [])
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();

                if (! $isTranslatedContentHeaderEdit) {
                    if (in_array($alignment, ['left', 'center', 'right'], true)) {
                        $settings['alignment'] = $alignment;
                    } else {
                        unset($settings['alignment']);
                    }
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['intro_text'] ?? '')) ?: null;
                $data['content'] = null;
                $data['meta'] = $metaItems === []
                    ? null
                    : json_encode($metaItems, JSON_UNESCAPED_SLASHES);
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['settings'] = $settings === []
                    ? null
                    : json_encode($settings, JSON_UNESCAPED_SLASHES);
                $data['variant'] = null;
            }

            if ($blockType?->slug === 'columns') {
                $isTranslatedColumnsEdit = $data['locale'] !== null;

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
                $data['variant'] = $isTranslatedColumnsEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (in_array(trim((string) ($data['variant'] ?? 'cards')), ['cards', 'plain', 'stats'], true) ? trim((string) ($data['variant'] ?? 'cards')) : 'cards');
            }

            if ($blockType?->slug === 'feature-grid') {
                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
            }

            if (in_array($blockType?->slug, ['column_item', 'feature-item'], true)) {
                $isTranslatedStructuredChildEdit = $data['locale'] !== null;

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: null;
                $data['url'] = $isTranslatedStructuredChildEdit
                    ? ($this->route('block')?->getRawOriginal('url'))
                    : (trim((string) ($data['url'] ?? '')) ?: null);
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
            }

            if ($blockType?->slug === 'button_link') {
                $isTranslatedButtonLinkEdit = $data['locale'] !== null;
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;

                if (! $isTranslatedButtonLinkEdit) {
                    $settings['url'] = trim((string) ($data['url'] ?? '')) ?: null;
                    $settings['target'] = ($data['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                }

                $data['title'] = trim((string) ($data['label'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['meta'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $settings = array_filter($settings, fn ($value) => $value !== null && $value !== '');
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }

                $data['variant'] = $isTranslatedButtonLinkEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (in_array(trim((string) ($data['variant'] ?? 'primary')), ['primary', 'secondary'], true) ? trim((string) ($data['variant'] ?? 'primary')) : 'primary');
            }

            if ($blockType?->slug === 'card') {
                $isTranslatedCardEdit = $data['locale'] !== null;
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;
                $submittedImagePosition = trim((string) ($data['image_position'] ?? ''));
                $submittedImageAlign = trim((string) ($data['image_align'] ?? ''));
                $resolvedImagePosition = in_array($submittedImagePosition, ['top', 'middle', 'bottom'], true)
                    ? $submittedImagePosition
                    : 'top';
                $resolvedImageAlign = in_array($submittedImageAlign, ['start', 'center', 'end', 'stretch'], true)
                    ? $submittedImageAlign
                    : 'center';

                if (! $isTranslatedCardEdit) {
                    $settings['url'] = trim((string) ($data['card_url'] ?? '')) ?: null;
                    $settings['target'] = ($data['card_target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                    $settings['variant'] = in_array(trim((string) ($data['card_variant'] ?? 'default')), ['default', 'promo'], true)
                        ? trim((string) ($data['card_variant'] ?? 'default'))
                        : 'default';
                    $settings['image_position'] = $resolvedImagePosition;
                    $settings['image_align'] = $resolvedImageAlign;
                    $settings['image_aspect'] = in_array(trim((string) ($data['image_aspect'] ?? 'auto')), ['auto', 'square', 'wide', 'portrait'], true)
                        ? trim((string) ($data['image_aspect'] ?? 'auto'))
                        : 'auto';
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['eyebrow'] = trim((string) ($data['eyebrow'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: null;
                $data['meta'] = trim((string) ($data['action_label'] ?? '')) ?: null;
                $data['url'] = null;
                $data['media_id'] = $isTranslatedCardEdit
                    ? ($this->route('block')?->media_id)
                    : $data['media_id'];
                unset($data['asset_id']);
                $data['variant'] = null;
                $settings = array_filter($settings, fn ($value) => $value !== null && $value !== '');
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }
            }

            if ($blockType?->slug === 'alert') {
                $isTranslatedAlertEdit = $data['locale'] !== null;
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;

                if (! $isTranslatedAlertEdit) {
                    $settings['variant'] = in_array(trim((string) ($data['alert_variant'] ?? 'info')), ['info', 'success', 'warning', 'danger'], true)
                        ? trim((string) ($data['alert_variant'] ?? 'info'))
                        : 'info';
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $settings = array_filter($settings, fn ($value) => $value !== null && $value !== '');
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }
            }

            if ($blockType?->slug === 'image') {
                $isTranslatedImageEdit = $data['locale'] !== null;

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = null;
                $data['url'] = $isTranslatedImageEdit
                    ? ($this->route('block')?->getRawOriginal('url'))
                    : (trim((string) ($data['url'] ?? '')) ?: null);
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
            }

            if ($blockType?->slug === 'gallery') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedGalleryEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedGalleryEdit) {
                    $submittedVariant = trim((string) ($data['gallery_variant'] ?? 'grid'));
                    $settings['variant'] = match ($submittedVariant) {
                        'masonry', 'masonary' => 'masonry',
                        'collage' => 'collage',
                        default => 'grid',
                    };
                    $settings['columns'] = in_array(trim((string) ($data['gallery_columns'] ?? '3')), ['2', '3', '4', '5'], true)
                        ? trim((string) ($data['gallery_columns'] ?? '3'))
                        : '3';
                    $settings['gap'] = in_array(trim((string) ($data['gallery_gap'] ?? 'md')), ['none', 'sm', 'md', 'lg'], true)
                        ? trim((string) ($data['gallery_gap'] ?? 'md'))
                        : 'md';
                    $settings['aspect_ratio'] = in_array(trim((string) ($data['gallery_aspect_ratio'] ?? 'auto')), ['auto', 'square', '4:3', '16:9', 'portrait'], true)
                        ? trim((string) ($data['gallery_aspect_ratio'] ?? 'auto'))
                        : 'auto';
                    $settings['captions_mode'] = in_array(trim((string) ($data['gallery_captions_mode'] ?? 'below')), ['hidden', 'below', 'overlay', 'on-hover'], true)
                        ? trim((string) ($data['gallery_captions_mode'] ?? 'below'))
                        : 'below';
                    $settings['overlay_mode'] = in_array(trim((string) ($data['gallery_overlay_mode'] ?? 'gradient')), ['none', 'gradient', 'solid'], true)
                        ? trim((string) ($data['gallery_overlay_mode'] ?? 'gradient'))
                        : 'gradient';
                    $settings['lightbox_enabled'] = (bool) ($data['gallery_lightbox_enabled'] ?? true);
                }

                $data['title'] = array_key_exists('title', $data)
                    ? (trim((string) ($data['title'] ?? '')) ?: null)
                    : ($existingBlock?->getRawOriginal('title'));
                $data['subtitle'] = array_key_exists('subtitle', $data)
                    ? (trim((string) ($data['subtitle'] ?? '')) ?: null)
                    : ($existingBlock?->getRawOriginal('subtitle'));
                $data['content'] = null;
                $data['url'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = json_encode(array_filter($settings, fn ($value) => $value !== null && $value !== '' && $value !== []), JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }
            }

            if ($blockType?->slug === 'download') {
                $isTranslatedDownloadEdit = $data['locale'] !== null;

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = null;
                $data['url'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
                $data['variant'] = $isTranslatedDownloadEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (in_array(trim((string) ($data['variant'] ?? 'secondary')), ['primary', 'secondary', 'ghost'], true) ? trim((string) ($data['variant'] ?? 'secondary')) : 'secondary');
            }

            if (in_array($blockType?->slug, ['file', 'video', 'audio'], true)) {
                $isTranslatedMediaCardEdit = $data['locale'] !== null;

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: null;
                $data['url'] = $isTranslatedMediaCardEdit
                    ? ($this->route('block')?->getRawOriginal('url'))
                    : (trim((string) ($data['url'] ?? '')) ?: null);
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
            }

            if ($blockType?->slug === 'breadcrumb') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;

                $homeLabel = trim((string) ($data['breadcrumb_home_label'] ?? ''));

                if ($homeLabel !== '') {
                    $settings['home_label'] = $homeLabel;
                } else {
                    unset($settings['home_label']);
                }

                $settings['include_current'] = ($data['breadcrumb_include_current'] ?? '1') !== '0';

                $data['title'] = null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'stat-card') {
                $isTranslatedStatCardEdit = $data['locale'] !== null;
                $title = trim((string) ($data['title'] ?? ''));
                $subtitle = trim((string) ($data['subtitle'] ?? ''));
                $content = trim((string) ($data['content'] ?? ''));

                $data['title'] = $title !== '' ? $title : null;
                $data['subtitle'] = $subtitle !== '' ? $subtitle : null;
                $data['content'] = $content !== '' ? $content : null;
                $data['url'] = $isTranslatedStatCardEdit
                    ? ($this->route('block')?->getRawOriginal('url'))
                    : (trim((string) ($data['url'] ?? '')) ?: null);
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
            }

            if ($blockType?->slug === 'link-list') {
                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
            }

            if ($blockType?->slug === 'header-actions') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedHeaderActionsEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedHeaderActionsEdit) {
                    $settings['show_mode_toggle'] = (bool) ($data['header_actions_show_mode_toggle'] ?? true);
                    $settings['show_accent_toggle'] = (bool) ($data['header_actions_show_accent_toggle'] ?? true);
                    $settings['show_search'] = (bool) ($data['header_actions_show_search'] ?? true);
                }

                $data['title'] = null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'sticky-navbar') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedStickyNavbarEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedStickyNavbarEdit) {
                    $layoutName = trim((string) ($data['name'] ?? ''));
                    $stickyMode = trim((string) ($data['sticky_navbar_mode'] ?? 'sticky'));

                    if ($layoutName !== '') {
                        $settings['layout_name'] = $layoutName;
                    } else {
                        unset($settings['layout_name']);
                    }

                    $settings['sticky_mode'] = in_array($stickyMode, ['sticky', 'fixed', 'static'], true) ? $stickyMode : 'sticky';
                    unset($settings['menu_key'], $settings['visual_variant'], $settings['compact'], $settings['brand_url'], $settings['logo_path'], $settings['width']);
                }

                $data['title'] = null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['asset_id'] = null;
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }
            }

            if ($blockType?->slug === 'navbar-brand') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedNavbarBrandEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedNavbarBrandEdit) {
                    $settings['url'] = trim((string) ($data['url'] ?? '')) ?: null;
                    $settings['target'] = ($data['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                    $settings['aria_label'] = trim((string) ($data['navbar_brand_aria_label'] ?? '')) ?: null;
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = null;
                $data['url'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $settings = array_filter($settings, fn ($value) => $value !== null && $value !== '');
                $data['settings'] = $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'navbar-navigation') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedNavbarNavigationEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedNavbarNavigationEdit) {
                    $settings['menu_key'] = $data['navbar_navigation_menu_key'] ?? NavigationItem::MENU_PRIMARY;
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: 'Primary navigation';
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }
            }

            if ($blockType?->slug === 'sidebar-brand') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedSidebarBrandEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedSidebarBrandEdit) {
                    $settings['url'] = trim((string) ($data['url'] ?? '')) ?: null;
                    $settings['target'] = ($data['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                    $settings['aria_label'] = trim((string) ($data['sidebar_brand_aria_label'] ?? '')) ?: null;
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = null;
                $data['url'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $settings = array_filter($settings, fn ($value) => $value !== null && $value !== '');
                $data['settings'] = $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'sidebar-navigation') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedSidebarNavigationEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedSidebarNavigationEdit) {
                    $menuKey = trim((string) ($data['sidebar_navigation_menu_key'] ?? ''));
                    $layoutName = trim((string) ($data['name'] ?? ''));
                    $activeMatching = trim((string) ($data['sidebar_navigation_active_matching'] ?? 'path'));

                    if ($menuKey !== '' && in_array($menuKey, NavigationItem::menuKeys(), true)) {
                        $settings['menu_key'] = $menuKey;
                    } else {
                        unset($settings['menu_key']);
                    }

                    $settings['show_icons'] = (bool) ($data['sidebar_navigation_show_icons'] ?? true);
                    $settings['active_matching'] = in_array($activeMatching, ['path', 'current-page', 'exact'], true)
                        ? $activeMatching
                        : 'path';

                    if ($layoutName !== '') {
                        $settings['layout_name'] = $layoutName;
                    } else {
                        unset($settings['layout_name']);
                    }
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'sidebar-nav-item') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedSidebarNavItemEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedSidebarNavItemEdit) {
                    $icon = app(IconCatalog::class)->normalizeSlug($data['sidebar_nav_item_icon'] ?? null);
                    $activeMode = trim((string) ($data['sidebar_nav_item_active_mode'] ?? 'path'));

                    $settings['url'] = trim((string) ($data['url'] ?? '')) ?: null;
                    $settings['target'] = ($data['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                    $settings['active_mode'] = in_array($activeMode, ['exact', 'path', 'current-page', 'manual'], true)
                        ? $activeMode
                        : 'path';
                    $settings['manual_active'] = (bool) ($data['sidebar_nav_item_manual_active'] ?? false);

                    if ($icon !== null) {
                        $settings['icon'] = $icon;
                    } else {
                        unset($settings['icon']);
                    }
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $settings = array_filter($settings, fn ($value) => $value !== null && $value !== '');
                $data['settings'] = $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'sidebar-nav-group') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedSidebarNavGroupEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedSidebarNavGroupEdit) {
                    $icon = app(IconCatalog::class)->normalizeSlug($data['sidebar_nav_group_icon'] ?? null);
                    $layoutName = trim((string) ($data['name'] ?? ''));

                    $settings['initially_open'] = (bool) ($data['sidebar_nav_group_initially_open'] ?? false);

                    if ($icon !== null) {
                        $settings['icon'] = $icon;
                    } else {
                        unset($settings['icon']);
                    }

                    if ($layoutName !== '') {
                        $settings['layout_name'] = $layoutName;
                    } else {
                        unset($settings['layout_name']);
                    }
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'sidebar-footer') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedSidebarFooterEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedSidebarFooterEdit) {
                    $settings['variant'] = in_array(trim((string) ($data['sidebar_footer_variant'] ?? 'info')), ['info', 'success', 'warning', 'danger'], true)
                        ? trim((string) ($data['sidebar_footer_variant'] ?? 'info'))
                        : 'info';
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: null;
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'plain_text') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;
                $alignment = trim((string) ($data['alignment'] ?? ''));

                if (in_array($alignment, ['left', 'center', 'right'], true)) {
                    $settings['alignment'] = $alignment;
                } else {
                    unset($settings['alignment']);
                }

                $data['content'] = trim((string) ($data['text'] ?? '')) ?: null;
                $data['title'] = null;
                $data['subtitle'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === []
                    ? null
                    : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'rich-text') {
                $data['title'] = null;
                $data['subtitle'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = null;
                $data['content'] = is_string($data['content'] ?? null)
                    ? $data['content']
                    : null;
            }

            if (in_array($blockType?->slug, ['section', 'container', 'cluster', 'grid'], true)) {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $layoutName = trim((string) ($data['name'] ?? ''));
                $settings = $existingSettings;
                $spacing = trim((string) ($data['spacing'] ?? ''));
                $width = trim((string) ($data['width'] ?? ''));
                $containerFlow = trim((string) ($data['container_flow'] ?? ''));
                $clusterGap = trim((string) ($data['cluster_gap'] ?? ''));
                $clusterJustify = trim((string) ($data['cluster_justify'] ?? ''));
                $clusterAlign = trim((string) ($data['cluster_align'] ?? ''));
                $clusterWrap = trim((string) ($data['cluster_wrap'] ?? ''));
                $clusterWidth = trim((string) ($data['cluster_width'] ?? ''));
                $gridColumns = trim((string) ($data['grid_columns'] ?? ''));
                $gridGap = trim((string) ($data['grid_gap'] ?? ''));

                if ($layoutName !== '') {
                    $settings['layout_name'] = $layoutName;
                } else {
                    unset($settings['layout_name']);
                }

                if ($blockType->slug === 'section') {
                    if (in_array($spacing, ['sm', 'lg'], true)) {
                        $settings['spacing'] = $spacing;
                    } else {
                        unset($settings['spacing']);
                    }

                    unset($settings['width']);
                }

                if ($blockType->slug === 'container') {
                    if (in_array($width, ['sm', 'md', 'lg', 'xl', 'full'], true)) {
                        $settings['width'] = $width;
                    } else {
                        unset($settings['width']);
                    }

                    if (in_array($containerFlow, ['none', 'stack'], true)) {
                        $settings['flow'] = $containerFlow;
                    } else {
                        unset($settings['flow']);
                    }

                    unset($settings['spacing']);
                }

                if ($blockType->slug === 'cluster') {
                    if (in_array($clusterGap, ['none', 'xs', 'sm', 'md', 'lg'], true)) {
                        $settings['gap'] = $clusterGap;
                    } else {
                        unset($settings['gap']);
                    }

                    if (in_array($clusterJustify, ['center', 'end', 'between'], true)) {
                        $settings['alignment'] = $clusterJustify;
                    } else {
                        unset($settings['alignment']);
                    }

                    if (in_array($clusterAlign, ['start', 'end', 'stretch'], true)) {
                        $settings['items_alignment'] = $clusterAlign;
                    } else {
                        unset($settings['items_alignment']);
                    }

                    if ($clusterWrap === 'nowrap') {
                        $settings['wrap'] = $clusterWrap;
                    } else {
                        unset($settings['wrap']);
                    }

                    if ($clusterWidth === 'full') {
                        $settings['width'] = $clusterWidth;
                    } else {
                        unset($settings['width']);
                    }

                    unset($settings['spacing']);
                }

                if ($blockType->slug === 'grid') {
                    if (in_array($gridColumns, ['2', '3', '4'], true)) {
                        $settings['columns'] = $gridColumns;
                    } else {
                        unset($settings['columns']);
                    }

                    if (in_array($gridGap, ['3', '4', '6'], true)) {
                        $settings['gap'] = $gridGap;
                    } else {
                        unset($settings['gap']);
                    }

                    unset($settings['spacing'], $settings['width'], $settings['alignment']);
                }

                $data['title'] = null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === []
                    ? null
                    : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'search-form') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedSearchFormEdit = $data['locale'] !== null;
                $settings = $existingSettings;

                if (! $isTranslatedSearchFormEdit) {
                    $settings['show_button'] = (bool) ($data['show_button'] ?? true);
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: 'Search';
                $data['subtitle'] = trim((string) ($data['subtitle'] ?? '')) ?: 'Search';
                $data['content'] = trim((string) ($data['content'] ?? '')) ?: 'Search this site';
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['meta'] = null;
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }

                $data['variant'] = $isTranslatedSearchFormEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (in_array(trim((string) ($data['variant'] ?? 'primary')), ['primary', 'secondary'], true) ? trim((string) ($data['variant'] ?? 'primary')) : 'primary');
            }
        }

        if (! empty($data['slot_type_id'])) {
            $slotType = SlotType::query()->find($data['slot_type_id']);
            $data['slot'] = $slotType?->slug;
        }

        unset($data['heading'], $data['intro_text'], $data['recipient_email'], $data['send_email_notification'], $data['store_submissions']);
        unset($data['layout']);
        unset($data['title_tag']);
        unset($data['language']);
        unset($data['navigation_menu_key']);
        unset($data['text'], $data['level'], $data['anchor']);
        unset($data['label'], $data['target'], $data['action_label'], $data['card_url'], $data['card_target'], $data['card_variant'], $data['image_position'], $data['image_align'], $data['image_aspect'], $data['alert_variant']);
        unset($data['header_actions_show_mode_toggle'], $data['header_actions_show_accent_toggle']);
        unset($data['sticky_navbar_mode'], $data['navbar_brand_aria_label'], $data['navbar_navigation_menu_key']);
        unset($data['sidebar_navigation_menu_key'], $data['sidebar_navigation_show_icons'], $data['sidebar_navigation_active_matching']);
        unset($data['sidebar_nav_item_icon'], $data['sidebar_nav_item_active_mode'], $data['sidebar_nav_item_manual_active']);
        unset($data['sidebar_nav_group_icon'], $data['sidebar_nav_group_initially_open'], $data['sidebar_footer_variant']);
        unset($data['show_button']);
        unset($data['name'], $data['alignment'], $data['spacing'], $data['width'], $data['container_flow'], $data['cluster_gap'], $data['cluster_justify'], $data['cluster_align'], $data['cluster_wrap'], $data['cluster_width'], $data['grid_columns'], $data['grid_gap'], $data['intro_text'], $data['meta_items'], $data['title_level']);

        return $data;
    }
}
