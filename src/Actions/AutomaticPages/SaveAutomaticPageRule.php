<?php

namespace WebBlocks\Cms\Actions\AutomaticPages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\AutomaticPagePolicy;

final class SaveAutomaticPageRule
{
  public function handle(array $data, User $actor, ?AutomaticPageRule $rule = null): AutomaticPageRule
  {
    return DB::transaction(function () use ($data, $actor, $rule): AutomaticPageRule {
      $site = Site::query()->lockForUpdate()->findOrFail($data['site_id']);
      if ($rule) {
        $rule = AutomaticPageRule::query()->lockForUpdate()->findOrFail($rule->id);
        abort_unless(app(AutomaticPagePolicy::class)->update($actor, $rule) && (int) $rule->site_id === (int) $site->id, 403);
        if ((int) $rule->revision !== (int) $data['revision']) {
          throw ValidationException::withMessages(['revision' => __('webblocks-cms::automatic-pages.stale')]);
        }
      } else {
        abort_unless(app(AutomaticPagePolicy::class)->create($actor, $site), 403);
        $rule = new AutomaticPageRule;
      }
      $rule->fill(collect($data)->except('revision')->all());
      $rule->revision = $rule->exists ? $rule->revision + 1 : 1;
      $rule->save();

      return $rule;
    });
  }
}
