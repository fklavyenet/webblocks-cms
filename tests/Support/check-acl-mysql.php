<?php

/** Run only against a disposable Laravel consumer whose database starts with cms_acl_. */
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\AclSchema;

$consumer = $argv[1] ?? '';
require $consumer.'/vendor/autoload.php';
$app = require $consumer.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || ! str_starts_with(DB::connection()->getDatabaseName(), 'cms_acl_')) {
  throw new RuntimeException('Requires MySQL 8 and an explicitly disposable cms_acl_ database.');
}
$version = DB::selectOne('select version() as v')->v;
if (! str_starts_with($version, '8.')) {
  throw new RuntimeException('Requires MySQL 8 and an explicitly disposable cms_acl_ database.');
}
$write = app(WriteCmsAccess::class);
$directory = $argv[3] ?? sys_get_temp_dir().'/cms-acl-race-'.bin2hex(random_bytes(6));
if (($argv[2] ?? '') === 'worker') {
  $job = json_decode(file_get_contents($directory.'/job'), true, flags: JSON_THROW_ON_ERROR);
  DB::connection()->beforeExecuting(function ($sql) use ($directory): void {
    if (str_contains($sql, 'wbcms_acl_locks') && str_contains($sql, 'for update')) {
      touch($directory.'/waiting');
    }
  });
  try {
    $actor = User::findOrFail($job['actor']);
    match ($job['operation']) {
      'role' => $write->role($actor, $job['data'], CmsRole::findOrFail($job['role'])),
      'delete-role' => $write->deleteRole($actor, CmsRole::findOrFail($job['role']), $job['version']),
      'user' => $write->user($actor, User::findOrFail($job['user']), $job['data']),
      'delete-user' => $write->deleteUser($actor, User::findOrFail($job['user'])),
    };
    $result = 'ok';
  } catch (ValidationException $e) {
    $result = 'validation';
  } catch (HttpException $e) {
    $result = 'http-'.$e->getStatusCode();
  } catch (ModelNotFoundException $e) {
    $result = 'missing';
  }
  file_put_contents($directory.'/result', $result);
  exit;
}
mkdir($directory);
$users = [];
$roles = [];
$sites = [];
$assertions = 0;
$assert = function (bool $condition, string $message) use (&$assertions): void {
  $assertions++;
  if (! $condition) {
    throw new RuntimeException($message);
  }
};
$actor = function (string $role = 'super_admin') use (&$users) {
  $user = User::forceCreate(['name' => 'Concurrency fixture', 'email' => bin2hex(random_bytes(8)).'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]);
  $users[] = $user->id;

  return $user;
};
$role = function () use ($write, $actor, &$roles) {
  $role = $write->role($actor(), ['name' => 'Race fixture', 'permissions' => ['cms.panel.access', 'cms.pages.read']]);
  $roles[] = $role->id;

  return $role;
};
$wait = function (callable $ready): void {
  $deadline = microtime(true) + 20;
  while (! $ready()) {
    if (microtime(true) > $deadline) {
      throw new RuntimeException('Worker barrier timed out.');
    }
    usleep(10000);
  }
};
// A second connection observes actual InnoDB lock waits, not elapsed-time guesses.
config(['database.connections.acl_observer' => config('database.connections.mysql')]);
$race = function (array $jobs, ?callable $whileLocked = null) use ($directory, $consumer, $wait, $assert): array {
  DB::beginTransaction();
  DB::table('wbcms_acl_locks')->where('id', 1)->lockForUpdate()->first();
  $workers = [];
  try {
    foreach ($jobs as $job) {
      $path = $directory.'/'.bin2hex(random_bytes(5));
      mkdir($path);
      file_put_contents($path.'/job', json_encode($job, JSON_THROW_ON_ERROR));
      $process = proc_open([PHP_BINARY, __FILE__, $consumer, 'worker', $path], [0 => ['pipe', 'r'], 1 => ['file', $path.'/stdout', 'w'], 2 => ['file', $path.'/stderr', 'w']], $pipes);
      fclose($pipes[0]);
      $workers[] = [$process, $path];
      $count = count($workers);
      $wait(fn () => file_exists($path.'/waiting'));
      $wait(fn () => (int) DB::connection('acl_observer')->selectOne('select count(*) as n from performance_schema.data_lock_waits')->n >= $count);
    }
    $whileLocked?->__invoke();
    DB::commit();
  } catch (Throwable $e) {
    DB::rollBack();
    throw $e;
  } finally {
    foreach ($workers as [$process, $path]) {
      $code = proc_close($process);
      $assert($code === 0 && file_exists($path.'/result'), 'Worker failed: '.file_get_contents($path.'/stderr'));
    }
  }

  return array_map(fn ($worker) => file_get_contents($worker[1].'/result'), $workers);
};
$memberships = DB::table('wbcms_acl_system_administrators')->pluck('user_id')->all();
try {
  echo 'MySQL '.$version.'; isolation '.DB::selectOne('select @@transaction_isolation as v')->v."\n";
  $root = $actor();
  $r = $role();
  $before = DB::table('wbcms_acl_events')->where('role_id', $r->id)->where('action', 'role.saved')->count();
  $job = ['operation' => 'role', 'actor' => $root->id, 'role' => $r->id, 'data' => ['name' => 'Winning edit', 'version' => $r->version, 'permissions' => ['cms.panel.access', 'cms.media.read']]];
  $assert($race([$job, $job]) === ['ok', 'validation'], 'Concurrent stale role edit was accepted.');
  $assert($r->fresh()->version === $r->version + 1, 'Role version incremented twice.');
  $assert(DB::table('wbcms_acl_events')->where('role_id', $r->id)->where('action', 'role.saved')->count() === $before + 1, 'Rejected save wrote audit records.');
  echo "PASS competing role versions and atomic audit\n";

  foreach ([false, true] as $deleteFirst) {
    $r = $role();
    $user = $actor('editor');
    $site = Site::create(['name' => 'Race site', 'handle' => 'race-'.bin2hex(random_bytes(4))]);
    $sites[] = $site->id;
    $assign = ['operation' => 'user', 'actor' => $root->id, 'user' => $user->id, 'data' => ['name' => 'Assigned name', 'role' => 'editor', 'is_active' => true, 'site_ids' => [$site->id], 'site_roles' => [$site->id => $r->id]]];
    $delete = ['operation' => 'delete-role', 'actor' => $root->id, 'role' => $r->id, 'version' => $r->version];
    $assert($race($deleteFirst ? [$delete, $assign] : [$assign, $delete]) === ($deleteFirst ? ['ok', 'missing'] : ['ok', 'validation']), 'Assignment/delete race had an unexpected result.');
    $assert($deleteFirst ? $user->fresh()->name === 'Concurrency fixture' : (int) DB::table('wbcms_site_user')->where('user_id', $user->id)->value('role_id') === $r->id, 'Assignment rollback or referential integrity failed.');
  }
  echo "PASS role assignment/deletion in both lock orders and rollback\n";

  foreach (['user', 'delete-user'] as $operation) {
    $a = $actor();
    $b = $actor();
    $restore = DB::table('wbcms_acl_system_administrators')->pluck('user_id')->all();
    DB::table('wbcms_acl_system_administrators')->whereNotIn('user_id', [$a->id, $b->id])->delete();
    $jobs = array_map(fn ($u) => ['operation' => $operation, 'actor' => $u->id, 'user' => $u->id, 'data' => ['role' => 'editor', 'is_active' => true, 'site_ids' => []]], [$a, $b]);
    $assert($race($jobs) === ['ok', 'validation'], 'Last administrator was lost in '.$operation.' race.');
    $assert(DB::table('wbcms_acl_system_administrators as a')->join('users as u', 'u.id', '=', 'a.user_id')->where('u.is_active', true)->count() === 1, 'Expected one surviving active administrator.');
    foreach ($restore as $id) {
      if (User::find($id) && ! in_array($id, [$a->id, $b->id])) {
        DB::table('wbcms_acl_system_administrators')->insertOrIgnore(['user_id' => $id]);
      }
    }
  }
  echo "PASS simultaneous last-administrator demotion and deletion\n";

  $a = $actor();
  $target = $actor('editor');
  $audit = DB::table('wbcms_acl_events')->count();
  $result = $race([['operation' => 'delete-user', 'actor' => $a->id, 'user' => $target->id]], fn () => DB::table('wbcms_acl_system_administrators')->where('user_id', $a->id)->delete());
  $assert($result === ['http-403'], 'Revoked administrator deleted another user after waiting for the ACL lock.');
  $assert($target->fresh() !== null && DB::table('wbcms_acl_events')->count() === $audit, 'Denied deletion changed data/audit.');
  echo "PASS administrator revocation while delete waits\n";

  $a = $actor('editor');
  $result = $race([['operation' => 'delete-user', 'actor' => $a->id, 'user' => $a->id]], fn () => DB::table('users')->where('id', $a->id)->update(['is_active' => false]));
  $assert($result === ['http-403'] && $a->fresh() !== null, 'Deactivated user self-deleted after waiting for the ACL lock.');
  echo "PASS account deactivation while self-delete waits\n";

  $a = $actor();
  DB::table('wbcms_acl_system_administrators')->where('user_id', $a->id)->delete();
  app(AclSchema::class)->migrate();
  $assert(! DB::table('wbcms_acl_system_administrators')->where('user_id', $a->id)->exists(), 'Migration resurrected revoked authority.');
  echo "PASS migration rerun preserves revocation\n";
  echo 'OK '.$assertions." assertions\n";
} catch (Throwable $e) {
  $failure = $e;
} finally {
  DB::table('wbcms_site_user')->whereIn('user_id', $users)->delete();
  DB::table('users')->whereIn('id', $users)->delete();
  DB::table('wbcms_acl_roles')->whereIn('id', $roles)->delete();
  DB::table('wbcms_sites')->whereIn('id', $sites)->delete();
  DB::table('wbcms_acl_events')->whereIn('actor_id', $users)->delete();
  foreach ($memberships as $id) {
    DB::table('wbcms_acl_system_administrators')->insertOrIgnore(['user_id' => $id]);
  }
  foreach (glob($directory.'/*/*') as $file) {
    unlink($file);
  }
  foreach (glob($directory.'/*') as $path) {
    rmdir($path);
  }
  rmdir($directory);
}
if (isset($failure)) {
  fwrite(STDERR, $failure->getMessage()."\n");
  exit(1);
}
