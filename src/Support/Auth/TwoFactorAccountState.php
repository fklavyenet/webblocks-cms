<?php

namespace WebBlocks\Cms\Support\Auth;

use WebBlocks\Cms\Support\Auth\Module\AccountState;

final class TwoFactorAccountState extends AccountState
{
  public const SESSION = 'wbcms.two_factor.account_version';
}
