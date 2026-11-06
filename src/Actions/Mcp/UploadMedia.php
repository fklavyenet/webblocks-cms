<?php

namespace WebBlocks\Cms\Actions\Mcp;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Mcp\McpToolException;
use WebBlocks\Cms\Support\Media\MediaUploader;
use WebBlocks\Cms\Support\Media\MediaUploadRules;

final class UploadMedia
{
  public const MAX_BYTES = 512 * 1024;

  public const MAX_BASE64_LENGTH = 699_052; // 4 * ceil(MAX_BYTES / 3)

  public function __construct(
    private readonly McpPagePolicy $policy,
    private readonly MediaUploader $uploader,
  ) {}

  public function handle(User $user, int $siteId, array $input): array
  {
    if (! $this->policy->uploadMedia($user, $siteId)) {
      throw new McpToolException('forbidden');
    }

    $bytes = base64_decode($input['content_base64'], true);
    if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES
      || base64_encode($bytes) !== $input['content_base64']) {
      throw new McpToolException('invalid_media_file');
    }

    $temporary = tmpfile();
    if ($temporary === false) {
      throw new McpToolException('media_upload_failed');
    }

    try {
      if (fwrite($temporary, $bytes) !== strlen($bytes) || ! fflush($temporary)) {
        throw new McpToolException('media_upload_failed');
      }
      $file = new UploadedFile(stream_get_meta_data($temporary)['uri'], $input['filename'], test: true);
      $mime = $file->getMimeType();
      if (Validator::make(['file' => $file], ['file' => MediaUploadRules::file()])->fails()
        || ! $mime || $mime === 'image/svg+xml') {
        throw new McpToolException('invalid_media_file');
      }
      $extensions = MimeTypes::getDefault()->getExtensions($mime);
      if ($mime === 'text/plain') {
        $extensions[] = 'csv'; // Short CSV files can be detected as plain text.
      }
      if (! in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)) {
        throw new McpToolException('invalid_media_file');
      }
      try {
        $media = $this->uploader->upload($file, $input, $user->id);
      } catch (RuntimeException $exception) {
        report($exception);

        throw new McpToolException('media_upload_failed');
      }

      return [
        'media_id' => $media->id,
        'kind' => $media->kind,
        'mime_type' => $media->mime_type,
        'size' => $media->size,
        'title' => $media->displayTitle(),
        'alt_text' => $media->alt_text,
        'caption' => $media->caption,
        'width' => $media->width,
        'height' => $media->height,
      ];
    } finally {
      fclose($temporary);
    }
  }
}
