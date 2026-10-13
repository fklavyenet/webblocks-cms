<?php

namespace WebBlocks\Cms\Support\Memberships;

use Illuminate\Http\Request;

interface MemberDirectory
{
  public function current(Request $request, int $siteId): ?MemberIdentity;

  public function hasPackage(MemberIdentity $member, int $packageId): bool;

  public function packages(int $siteId): array;
}
