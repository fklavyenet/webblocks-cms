<?php

namespace App\Support\Sites\ExportImport;

class SiteTransferPackage
{
    public const PRODUCT = 'WebBlocks CMS';

    public const PACKAGE_TYPE = 'site_export';

    public const FEATURE_VERSION = 1;

    public const FORMAT_VERSION = 1;

    public const REQUIRED_DATA_FILES = [
        'data/site.json',
        'data/locales.json',
        'data/site_locales.json',
        'data/pages.json',
        'data/page_translations.json',
        'data/page_slots.json',
        'data/blocks.json',
        'data/block_assets.json',
        'data/block_text_translations.json',
        'data/block_button_translations.json',
        'data/block_image_translations.json',
        'data/block_contact_form_translations.json',
        'data/navigation_items.json',
        'data/asset_folders.json',
        'data/assets.json',
    ];

    public const OPTIONAL_DATA_FILES = [
        'data/shared_slots.json',
    ];

    public const ALL_DATA_FILES = [
        ...self::REQUIRED_DATA_FILES,
        ...self::OPTIONAL_DATA_FILES,
    ];
}
