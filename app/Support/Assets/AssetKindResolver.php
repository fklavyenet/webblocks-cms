<?php

namespace App\Support\Assets;

use App\Models\Asset;

class AssetKindResolver
{
    private const IMAGE_MIME_PREFIX = 'image/';

    private const VIDEO_MIME_PREFIX = 'video/';

    private const DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/rtf',
        'application/zip',
    ];

    private const DOCUMENT_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
        'rtf',
        'csv',
        'zip',
    ];

    public static function resolve(?string $mimeType, ?string $extension): string
    {
        $mimeType = strtolower((string) $mimeType);
        $extension = strtolower((string) $extension);

        if (str_starts_with($mimeType, self::IMAGE_MIME_PREFIX)) {
            return Asset::KIND_IMAGE;
        }

        if (str_starts_with($mimeType, self::VIDEO_MIME_PREFIX)) {
            return Asset::KIND_VIDEO;
        }

        if (in_array($mimeType, self::DOCUMENT_MIME_TYPES, true) || in_array($extension, self::DOCUMENT_EXTENSIONS, true)) {
            return Asset::KIND_DOCUMENT;
        }

        return Asset::KIND_OTHER;
    }

    public static function directoryFor(string $kind): string
    {
        return match ($kind) {
            Asset::KIND_IMAGE => 'images',
            Asset::KIND_VIDEO => 'videos',
            Asset::KIND_DOCUMENT => 'documents',
            default => 'other',
        };
    }
}
