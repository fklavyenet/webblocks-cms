<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use WebBlocks\Cms\Notifications\System\TwoFactorEmail;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;

final class EmailTwoFactorCodes
{
  public const SESSION = 'wbcms.two_factor.email_codes';

  public function send(Request $request, Authenticatable $user, string $purpose, string $email, string $binding): string
  {
    if (! in_array($purpose, ['login', 'setup', 'action'], true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return 'send_failed';
    }
    $id = (string) Str::uuid();
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $created = DB::transaction(function () use ($user, $purpose, $email, $binding, $id, $code): bool {
      DB::table('users')->where('id', $user->getAuthIdentifier())->lockForUpdate()->first();
      // Limit resends across sessions, with a separate account-wide send ceiling.
      $recent = DB::table('wbcms_two_factor_email_codes')->where('user_id', $user->getAuthIdentifier())->where('sent_at', '>', now()->subMinute());
      if ((clone $recent)->count() >= 3 || (clone $recent)->where('purpose', $purpose)->exists()) {
        return false;
      }
      DB::table('wbcms_two_factor_email_codes')->where('user_id', $user->getAuthIdentifier())->where('expires_at', '<=', now())->delete();
      DB::table('wbcms_two_factor_email_codes')->insert([
        'id' => $id, 'user_id' => $user->getAuthIdentifier(), 'purpose' => $purpose, 'email' => $email,
        'binding' => $binding, 'code_hash' => $this->hash($id, $code), 'attempts' => 0,
        'sent_at' => now(), 'expires_at' => now()->addMinutes(5), 'consumed_at' => null,
      ]);

      return true;
    });
    if (! $created) {
      return 'send_throttled';
    }
    try {
      $mailer = app(CmsMailSettingsResolver::class)->resolvedMailerName();
      $this->assertDeliverable($mailer);
      Notification::route('mail', $email)->notify((new TwoFactorEmail($code))->locale(app()->getLocale()));
    } catch (\Throwable $exception) {
      DB::table('wbcms_two_factor_email_codes')->where('id', $id)->update(['consumed_at' => now()]);
      // SMTP exceptions can contain the entire message, including its verification code.
      Log::warning('CMS verification email could not be sent.', ['exception_class' => $exception::class]);

      return 'send_failed';
    }
    $previous = $request->session()->get(self::SESSION.'.'.$purpose);
    if (is_string($previous)) {
      DB::table('wbcms_two_factor_email_codes')->where('id', $previous)->where('user_id', $user->getAuthIdentifier())->update(['consumed_at' => now()]);
    }
    $request->session()->put(self::SESSION.'.'.$purpose, $id);

    return 'sent';
  }

  public function consume(Authenticatable $user, string $purpose, string $email, string $binding, string $id, #[\SensitiveParameter] string $code): bool
  {
    return DB::transaction(function () use ($user, $purpose, $email, $binding, $id, $code): bool {
      $row = DB::table('wbcms_two_factor_email_codes')->where('id', $id)->where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();
      if (! $row || $row->purpose !== $purpose || $row->email !== $email || ! hash_equals($row->binding, $binding)
        || $row->consumed_at !== null || $row->attempts >= 5 || strtotime($row->expires_at) <= now()->timestamp) {
        return false;
      }
      $valid = hash_equals($row->code_hash, $this->hash($id, $code));
      DB::table('wbcms_two_factor_email_codes')->where('id', $id)->update([
        'attempts' => $row->attempts + 1, 'consumed_at' => $valid ? now() : null,
      ]);

      return $valid;
    });
  }

  private function hash(string $id, #[\SensitiveParameter] string $code): string
  {
    return hash_hmac('sha256', $id.'|'.$code, (string) config('app.key'));
  }

  public function assertDeliverable(string $mailer, array $visited = []): void
  {
    $transport = config('mail.mailers.'.$mailer.'.transport');
    if (in_array($mailer, $visited, true) || ! is_string($transport) || in_array($transport, ['log', 'array'], true)) {
      throw new \RuntimeException('Verification email requires a delivery transport.');
    }
    if (in_array($transport, ['failover', 'roundrobin'], true)) {
      foreach ((array) config('mail.mailers.'.$mailer.'.mailers', []) as $nested) {
        $this->assertDeliverable($nested, [...$visited, $mailer]);
      }
    }
  }
}
