<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class MovePageSiteRequest extends FormRequest
{
  public function authorize(): bool
  {
    $page = $this->route('page');

    return $page instanceof Page
      && $this->user()?->canAccessAdmin()
      && ! $this->user()?->isEditor();
  }

  public function rules(): array
  {
    return [
      'target_site_id' => ['required', 'integer', 'exists:wbcms_sites,id'],
    ];
  }

  public function after(): array
  {
    return [function (Validator $validator): void {
      $page = $this->route('page');
      $page = $page instanceof Page ? $page : null;
      $targetSiteId = (int) $this->input('target_site_id');

      if (! $page || $targetSiteId < 1) {
        return;
      }

      /** @var AdminAuthorization $authorization */
      $authorization = app(AdminAuthorization::class);

      if ($this->user()?->isEditor()) {
        $validator->errors()->add('target_site_id', 'Editors cannot move pages between sites.');

        return;
      }

      $targetSite = Site::query()->find($targetSiteId);

      if (! $targetSite) {
        return;
      }

      if (! $this->user()?->isSuperAdmin()) {
        try {
          $authorization->abortUnlessSiteAccess($this->user(), $page);
          $authorization->abortUnlessSiteAccess($this->user(), $targetSite);
        } catch (HttpException) {
          $validator->errors()->add('target_site_id', 'You must have access to both the current site and the target site to move this page.');
        }
      }
    }];
  }
}
