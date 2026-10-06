<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Blocks\MobileBlockMedia;

class BlockMobileMediaRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return ['mobile_media_id' => self::rulesForType($this->route('block')?->typeSlug())];
  }

  public static function rulesForType(?string $type): array
  {
    return [
      MobileBlockMedia::supports($type) ? 'nullable' : 'prohibited',
      'integer',
      Rule::exists('wbcms_media', 'id')->where('kind', Media::KIND_IMAGE),
    ];
  }
}
