<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use WebBlocks\Cms\Actions\Auth\ResetTwoFactor;
use WebBlocks\Cms\Policies\TwoFactorPolicy;

final class ResetTwoFactorCommand extends Command
{
  protected $signature = 'webblocks:two-factor:reset {user} {--email=} {--reason=} {--force} {--dry-run}';

  public function __construct()
  {
    $this->signature = 'webblocks:two-factor:reset';
    foreach (['user', '--email=', '--reason=', '--force', '--dry-run'] as $token) {
      $key = str_replace('-', '_', trim($token, '-='));
      $this->signature .= ' {'.$token.' : '.__('webblocks-cms::two-factor.cli_'.$key).'}';
    }
    parent::__construct();
    $this->setDescription(__('webblocks-cms::two-factor.cli_description'));
  }

  public function handle(ResetTwoFactor $reset): int
  {
    $selector = trim((string) $this->argument('user'));
    $provider = Auth::guard('web')->getProvider();
    $user = ctype_digit($selector) ? $provider->retrieveById($selector) : $provider->retrieveByCredentials(['email' => strtolower($selector)]);
    $reason = trim((string) $this->option('reason'));
    $email = filled($this->option('email')) ? strtolower(trim((string) $this->option('email'))) : null;
    if (! app(TwoFactorPolicy::class)->managedTarget($user) || ! $reset->validInput($user, $reason, $email)) {
      $this->error(__('webblocks-cms::two-factor.cli_invalid'));

      return self::FAILURE;
    }
    if ($this->option('dry-run')) {
      $this->info(__('webblocks-cms::two-factor.cli_preview', ['id' => $user->getAuthIdentifier()]));

      return self::SUCCESS;
    }
    if (! $this->option('force') && (! $this->input->isInteractive()
      || ! $this->confirm(__('webblocks-cms::two-factor.cli_confirm', ['id' => $user->getAuthIdentifier()]), false))) {
      $this->error(__('webblocks-cms::two-factor.cli_cancelled'));

      return self::FAILURE;
    }
    if (! $reset->fromConsole($user, $reason, $email)) {
      $this->error(__('webblocks-cms::two-factor.cli_invalid'));

      return self::FAILURE;
    }
    $this->info(__('webblocks-cms::two-factor.cli_done', ['id' => $user->getAuthIdentifier()]));

    return self::SUCCESS;
  }
}
