<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\SiteNotificationSettingsPolicy;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;

class SiteNotificationSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    $site = $this->route('site');
    if (! $site instanceof Site) {
      return false;
    }

    return app(SiteNotificationSettingsPolicy::class)->access($this, $site);
  }

  public function rules(): array
  {
    return SiteNotificationPolicy::rules();
  }

  public function after(): array
  {
    return [function ($validator): void {
      if (! $this->isMethod('GET') && ! Schema::hasColumn('wbcms_sites', 'notification_settings')) {
        $validator->errors()->add('notification_settings', __('webblocks-cms::notifications.schema_required'));
      }
      $keys = array_keys(SiteNotificationPolicy::DEFAULTS);
      if (! $this->isMethod('GET') && ($this->all() === [] || array_diff(array_keys($this->all()), $keys) !== [])) {
        $validator->errors()->add('notification_settings', __('webblocks-cms::notifications.invalid_fields'));
      }
    }];
  }
}
