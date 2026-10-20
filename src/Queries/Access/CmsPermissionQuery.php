<?php

namespace WebBlocks\Cms\Queries\Access;

use App\Models\User;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Access\PermissionCatalog;

final class CmsPermissionQuery
{
  public function __construct(private CmsAuthorization $acl, private PermissionCatalog $catalog) {}

  public function inspect(?User $user, ?Site $site, array $filters): array
  {
    $effective = [];
    $visible = [];
    $needle = $this->searchText($filters['q']);
    if ($user && $site) {
      foreach ($this->catalog->editable() as $key => $definition) {
        $allowed = $this->acl->allows($user, $key, $site->id);
        $effective[$key] = $allowed;
        if (($filters['status'] === 'allowed' && ! $allowed) || ($filters['status'] === 'denied' && $allowed)) {
          continue;
        }
        $haystack = $this->searchText($key.' '.$definition['label'].' '.__('webblocks-cms::acl.groups.'.$definition['group']));
        if ($needle !== '' && ! str_contains($haystack, $needle)) {
          continue;
        }
        $visible[$key] = [...$definition, 'allowed' => $allowed];
      }
    }
    $grantedCount = count(array_filter($effective));

    return [
      'permissionGroups' => collect($visible)->groupBy('group', preserveKeys: true),
      'totalCount' => count($effective), 'filteredCount' => count($visible),
      'grantedCount' => $grantedCount, 'deniedCount' => count($effective) - $grantedCount,
      'assignedRole' => $user && $site ? CmsRole::query()->find($this->acl->roleId($user, $site->id)) : null,
      'subjectIsSuperAdmin' => $user && $this->acl->isSuperAdmin($user),
    ];
  }

  private function searchText(string $value): string
  {
    $normalized = Str::ascii($value, app()->getLocale());

    return Str::lower($normalized !== '' ? $normalized : $value);
  }
}
