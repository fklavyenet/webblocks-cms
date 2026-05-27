<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;

class WebBlocksUiReleaseRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('webblocks-ui-manager.manage');
  }

  /**
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    $release = $this->route('release');
    $releaseId = $release instanceof WebBlocksUiRelease ? $release->id : null;

    return [
      'version' => [
        'required',
        'string',
        'max:50',
        'regex:/^v?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',
        Rule::unique('webblocks_ui_manager_releases', 'version')->ignore($releaseId),
      ],
      'label' => ['nullable', 'string', 'max:255'],
      'status' => ['required', Rule::in([
        WebBlocksUiRelease::STATUS_DRAFT,
        WebBlocksUiRelease::STATUS_PREPARED,
        WebBlocksUiRelease::STATUS_PUBLISHED,
      ])],
      'notes' => ['nullable', 'string'],
      'cdn_base_path' => ['nullable', 'string', 'max:255'],
      'cdn_base_url' => ['nullable', 'url', 'max:255'],
    ];
  }
}
