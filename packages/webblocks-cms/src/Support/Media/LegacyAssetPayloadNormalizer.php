<?php

namespace WebBlocks\Cms\Support\Media;

class LegacyAssetPayloadNormalizer
{
    public function normalizePayload(array $payload): array
    {
        $normalized = $payload;
        $normalized['site'] = $this->normalizeSiteData((array) ($normalized['site'] ?? []));
        $normalized['page_translations'] = array_map(fn (array $row) => $this->normalizePageTranslationData($row), (array) ($normalized['page_translations'] ?? []));
        $normalized['blocks'] = array_map(fn (array $row) => $this->normalizeBlockData($row), (array) ($normalized['blocks'] ?? []));
        $normalized['block_media'] = array_map(fn (array $row) => $this->normalizeBlockMediaData($row), $this->blockMediaRows($normalized));
        $normalized['media_folders'] = (array) ($normalized['media_folders'] ?? $normalized['asset_folders'] ?? []);
        $normalized['media'] = (array) ($normalized['media'] ?? $normalized['assets'] ?? []);

        return $normalized;
    }

    public function normalizeSiteData(array $siteData): array
    {
        if (! array_key_exists('favicon_media_id', $siteData) && array_key_exists('favicon_asset_id', $siteData)) {
            $siteData['favicon_media_id'] = $siteData['favicon_asset_id'];
        }

        if (! array_key_exists('social_image_media_id', $siteData) && array_key_exists('social_image_asset_id', $siteData)) {
            $siteData['social_image_media_id'] = $siteData['social_image_asset_id'];
        }

        return $siteData;
    }

    public function normalizePageTranslationData(array $translation): array
    {
        if (! array_key_exists('og_image_media_id', $translation) && array_key_exists('og_image_asset_id', $translation)) {
            $translation['og_image_media_id'] = $translation['og_image_asset_id'];
        }

        return $translation;
    }

    public function normalizeBlockData(array $block): array
    {
        if (! array_key_exists('media_id', $block) && array_key_exists('asset_id', $block)) {
            $block['media_id'] = $block['asset_id'];
        }

        $block['settings'] = $this->normalizeBlockSettings($block['settings'] ?? null);

        return $block;
    }

    public function normalizeBlockSettings(mixed $settings): mixed
    {
        if (is_string($settings) && trim($settings) !== '') {
            $decoded = json_decode($settings, true);

            if (is_array($decoded)) {
                return json_encode($this->normalizeBlockSettingsArray($decoded), JSON_UNESCAPED_SLASHES);
            }

            return $settings;
        }

        if (! is_array($settings)) {
            return $settings;
        }

        return $this->normalizeBlockSettingsArray($settings);
    }

    public function normalizeBlockSettingsArray(array $settings): array
    {
        if (! array_key_exists('media_id', $settings) && array_key_exists('asset_id', $settings)) {
            $settings['media_id'] = $settings['asset_id'];
        }

        if (! array_key_exists('media_ids', $settings) && array_key_exists('asset_ids', $settings)) {
            $settings['media_ids'] = $settings['asset_ids'];
        }

        if (! array_key_exists('gallery_media_ids', $settings) && array_key_exists('gallery_asset_ids', $settings)) {
            $settings['gallery_media_ids'] = $settings['gallery_asset_ids'];
        }

        if (! array_key_exists('attachment_media_id', $settings) && array_key_exists('attachment_asset_id', $settings)) {
            $settings['attachment_media_id'] = $settings['attachment_asset_id'];
        }

        return $settings;
    }

    public function normalizeRevisionSnapshot(array $snapshot): array
    {
        $snapshot['translations'] = array_map(fn (array $row) => $this->normalizePageTranslationData($row), (array) ($snapshot['translations'] ?? []));
        $snapshot['blocks'] = array_map(function (array $block): array {
            $block = $this->normalizeBlockData($block);
            $block['block_media'] = array_map(fn (array $row) => $this->normalizeBlockMediaData($row), (array) ($block['block_media'] ?? $block['block_assets'] ?? []));

            return $block;
        }, (array) ($snapshot['blocks'] ?? []));

        return $snapshot;
    }

    public function normalizeBlockMediaData(array $row): array
    {
        if (! array_key_exists('media_id', $row) && array_key_exists('asset_id', $row)) {
            $row['media_id'] = $row['asset_id'];
        }

        return $row;
    }

    private function blockMediaRows(array $payload): array
    {
        return (array) ($payload['block_media'] ?? $payload['block_assets'] ?? []);
    }
}
