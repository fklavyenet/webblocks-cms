<?php

namespace WebBlocks\Cms\Support\Memberships;

final readonly class MemberIdentity
{
  public function __construct(public int $id, public int $siteId, public string $name, public string $email, public bool $verified) {}
}
