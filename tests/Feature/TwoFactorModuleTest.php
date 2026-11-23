<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use TwoFactorConsumer\Auth\AccountState;
use TwoFactorConsumer\Auth\Credentials;
use TwoFactorConsumer\Auth\EmailCodes;
use TwoFactorConsumer\Auth\Schema as TwoFactorSchema;
use TwoFactorConsumer\Auth\Storage;
use TwoFactorConsumer\Auth\VerificationSession;
use WebBlocks\Cms\Support\Auth\Module\TwoFactor;
use WebBlocks\Cms\Tests\TestCase;

final class TwoFactorModuleTest extends TestCase
{
  #[Test]
  public function the_versioned_export_can_manage_a_separate_laravel_accounts_table_and_session(): void
  {
    $directory = sys_get_temp_dir().'/two-factor-consumer-'.bin2hex(random_bytes(8));
    $exporter = dirname(__DIR__, 2).'/scripts/embed-two-factor.php';
    try {
      (new Process([PHP_BINARY, $exporter, TwoFactor::VERSION, 'TwoFactorConsumer\\Auth', $directory]))->mustRun();
      $manifest = json_decode(file_get_contents($directory.'/module.json'), true, flags: JSON_THROW_ON_ERROR);
      $this->assertSame('two-factor', $manifest['module']);
      $this->assertSame(TwoFactor::NAME, $manifest['name']);
      $this->assertSame(TwoFactor::VERSION, $manifest['version']);
      foreach ($manifest['files'] as $file => $checksum) {
        $this->assertSame($checksum, hash_file('sha256', $directory.'/'.$file));
      }
      foreach (glob($directory.'/*.php') as $path) {
        require $path;
      }

      Schema::create('accounts', fn (Blueprint $table) => $table->id('account_id'));
      DB::table('accounts')->insert(['account_id' => 42]);
      $user = new GenericUser(['id' => 42, 'password' => 'fixture-hash']);
      $storage = new Storage('product_2fa_', 'product.2fa', 'accounts', 'account_id');
      $schema = new TwoFactorSchema($storage);
      $schema->migrate();
      $schema->migrate();
      $emailCodes = new EmailCodes($storage);
      $sessions = new VerificationSession($storage);
      $credentials = new Credentials($storage, $emailCodes, $sessions);
      $account = new AccountState($storage);
      $request = Request::create('/');
      $request->setLaravelSession(app('session.store'));
      $email = 'consumer@example.test';
      $generation = $credentials->start($user, $email);
      $code = null;
      $this->assertSame('sent', $emailCodes->send($request, $user, 'setup', $email, $sessions->setupBinding($user, $generation),
        function (string $recipient, string $value) use ($email, &$code): bool {
          $this->assertSame($email, $recipient);
          $code = $value;

          return true;
        }));
      $recovery = $credentials->confirm($user, $generation, $code, $emailCodes->challengeId($request, 'setup'));
      $this->assertCount(10, $recovery);
      $credential = $credentials->credential($user);
      $this->assertSame($email, $credential->email);
      $account->stamp($request, $user);
      $sessions->mark($request, $user, $credential);
      $this->assertTrue($sessions->verified($request, $user, $credential));
      $this->assertFalse($request->session()->has('wbcms.two_factor.verified'));
      $this->assertTrue($credentials->verify($user, '', $recovery[0]));
      $this->assertFalse($credentials->verify($user, '', $recovery[0]));
      $this->assertTrue($credentials->reset($user));
      $this->assertFalse($account->current($request, $user));
      $this->assertTrue((bool) $account->state($user)->enrollment_required);
      $this->assertNull($credentials->credential($user));
    } finally {
      (new Filesystem)->deleteDirectory($directory);
    }
  }
}
