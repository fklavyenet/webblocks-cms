<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Database\Seeders\CoreCatalogSeeder;
use WebBlocks\Cms\Database\Seeders\FoundationSiteLocaleSeeder;
use WebBlocks\Cms\Models\Layout;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Install\InstallState;
use WebBlocks\Cms\Support\Install\LaravelSupportTableInstaller;
use WebBlocks\Cms\Support\Install\LaravelWelcomeRouteCleaner;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferDisk;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\SystemBackupArchiveResolver;
use WebBlocks\Cms\Support\Users\EnsuresCmsUserAccess;
use WebBlocks\Cms\Support\WebBlocks;

class InstallWebBlocksCmsCommand extends Command
{
  private const DEFAULT_ADMIN_NAME = 'WebBlocks Admin';

  private const DEFAULT_ADMIN_EMAIL = 'admin@example.com';

  private const DEFAULT_ADMIN_PASSWORD = 'password';

  protected $signature = 'webblocks:install
    {--name= : First super admin name}
    {--email= : First super admin email}
    {--password= : First super admin password}
    {--site-name= : Default site name}
    {--site-handle= : Default site handle}
    {--force : Overwrite package-owned assets}';

  protected $description = 'Install WebBlocks CMS into the current Laravel application';

  public function __construct(
    private readonly Filesystem $files,
    private readonly EnsuresCmsUserAccess $ensuresCmsUserAccess,
    private readonly InstallState $installState,
    private readonly InstalledVersionStore $installedVersionStore,
    private readonly LaravelSupportTableInstaller $laravelSupportTableInstaller,
    private readonly LaravelWelcomeRouteCleaner $laravelWelcomeRouteCleaner,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    $this->components->info('Installing WebBlocks CMS');

    $this->ensureCmsConfigIsResolvable();
    $this->publishPackageConfigIfMissing();
    $this->ensurePublicRoutesCanResolveToCms();
    $this->ensureUserModelIsCmsAware();
    $this->runPackageMigrations();
    $this->ensureLaravelSupportTables();
    $this->ensureSiteTransferStorage();
    $this->ensureBackupStorage();
    $this->installAssets();
    $this->ensureStorageLink();
    $this->seedCoreData();
    $site = $this->ensureDefaultSite();
    $this->ensureDefaultSiteDomain($site);
    $this->ensureHomepage($site);
    $this->ensureFirstSuperAdmin($site);
    $this->installedVersionStore->persist(WebBlocks::version());
    $this->installState->markInstalled();

    $this->components->info('WebBlocks CMS is ready. Open /cms to sign in.');

    return self::SUCCESS;
  }

  private function ensurePublicRoutesCanResolveToCms(): void
  {
    $result = $this->laravelWelcomeRouteCleaner->clean(config('webblocks-cms.install.web_routes_path'));

    if ($result->removedWelcomeRoute()) {
      $message = 'Removed untouched Laravel welcome route so WebBlocks CMS can serve public routes.';

      if ($result->backupPath) {
        $message .= ' Backup created at '.$result->backupPath.'.';
      }

      $this->components->info($message);

      return;
    }

    if ($result->skippedCustomRouteFile()) {
      $this->components->warn('Skipped welcome route cleanup because routes/web.php is custom.');
    }
  }

  private function ensureUserModelIsCmsAware(): void
  {
    if (! $this->shouldPatchUserModel()) {
      return;
    }

    if ($this->ensuresCmsUserAccess->ensure()) {
      $message = 'Patched App\\Models\\User with the CMS access trait.';

      if ($backupPath = $this->ensuresCmsUserAccess->lastBackupPath()) {
        $message .= ' Backup created at '.$backupPath.'.';
      }

      $this->components->info($message);

      return;
    }

    $this->components->info('App\\Models\\User already contains the CMS access trait.');
  }

  private function runPackageMigrations(): void
  {
    if ($this->cmsSchemaAlreadyExists()) {
      $this->components->info('CMS schema already exists. Skipping fresh-install migrations.');

      return;
    }

    $migrationPath = realpath(__DIR__.'/../../database/migrations/fresh');

    if (! $migrationPath) {
      throw new \RuntimeException('Package fresh-install migrations were not found.');
    }

    Artisan::call('migrate', [
      '--path' => $migrationPath,
      '--realpath' => true,
      '--force' => true,
    ]);

    $this->output->write(Artisan::output());
  }

  private function ensureLaravelSupportTables(): void
  {
    $this->laravelSupportTableInstaller->ensureRequiredTables();
  }

  private function ensureSiteTransferStorage(): void
  {
    app(SiteTransferDisk::class)->ensureReady();

    $this->components->info('Site transfer storage is ready.');
  }

  private function ensureBackupStorage(): void
  {
    app(SystemBackupArchiveResolver::class)->archiveDisk();

    $this->components->info('Backup storage is ready.');
  }

  private function installAssets(): void
  {
    $source = realpath(__DIR__.'/../../public/cms');

    if (! $source) {
      return;
    }

    $target = public_path('cms');

    if (! is_dir($target)) {
      File::ensureDirectoryExists($target);
    }

    foreach ($this->files->allFiles($source) as $file) {
      $relative = ltrim(str_replace($source, '', $file->getPathname()), DIRECTORY_SEPARATOR);
      $destination = $target.DIRECTORY_SEPARATOR.$relative;
      $destinationDirectory = dirname($destination);

      if (! is_dir($destinationDirectory)) {
        File::ensureDirectoryExists($destinationDirectory);
      }

      if (is_file($destination) && ! $this->option('force')) {
        continue;
      }

      $this->files->copy($file->getPathname(), $destination);
    }

    $this->components->info('Installed CMS assets into public/cms.');
  }

  private function ensureStorageLink(): void
  {
    if (file_exists(public_path('storage'))) {
      return;
    }

    try {
      Artisan::call('storage:link');
    } catch (\Throwable) {
      // Ignore local environments that cannot create symlinks.
    }
  }

  private function seedCoreData(): void
  {
    $this->components->info('Seeding CMS baseline data.');

    $this->callSilent('db:seed', ['--class' => FoundationSiteLocaleSeeder::class, '--force' => true]);
    $this->callSilent('db:seed', ['--class' => CoreCatalogSeeder::class, '--force' => true]);
    $this->callSilent('block-types:sync-core', ['--force' => true]);
  }

  private function ensureDefaultSite(): Site
  {
    $siteName = trim((string) ($this->option('site-name') ?: config('webblocks-cms.defaults.site_name', 'Default Site')));
    $siteHandle = Str::slug((string) ($this->option('site-handle') ?: config('webblocks-cms.defaults.site_handle', 'default')));

    $site = Site::query()->updateOrCreate(
      ['handle' => $siteHandle],
      [
        'name' => $siteName !== '' ? $siteName : 'Default Site',
        'display_name' => $siteName !== '' ? $siteName : 'Default Site',
        'is_primary' => true,
      ],
    );

    Locale::query()->where('code', config('webblocks-cms.defaults.locale', 'en'))->update([
      'is_default' => true,
      'is_enabled' => true,
    ]);

    Locale::query()->firstOrCreate(
      ['code' => config('webblocks-cms.defaults.locale', 'en')],
      [
        'name' => strtoupper((string) config('webblocks-cms.defaults.locale', 'en')),
        'is_default' => true,
        'is_enabled' => true,
      ],
    );

    $defaultLocale = Locale::query()->where('is_default', true)->first();

    if ($defaultLocale) {
      $site->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);
    }

    Site::query()->whereKeyNot($site->id)->update(['is_primary' => false]);

    return $site->fresh();
  }

  private function ensureDefaultSiteDomain(Site $site): void
  {
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);
    $host = is_string($host) && $host !== '' ? $host : 'localhost';

    SiteDomain::query()->updateOrCreate(
      ['site_id' => $site->id, 'domain' => $host],
      [
        'is_primary' => true,
        'redirect_to_primary' => false,
        'status' => SiteDomain::STATUS_ACTIVE,
      ],
    );

    $site->forceFill(['domain' => $host])->save();
  }

  private function ensureHomepage(Site $site): void
  {
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $layout = Layout::query()->firstOrCreate(
      ['slug' => 'default-layout'],
      ['name' => 'Default Layout']
    );

    $homePage = Page::query()->firstOrCreate(
      [
        'site_id' => $site->id,
        'page_type' => 'default',
        'status' => Page::STATUS_PUBLISHED,
      ],
      [
        'layout_id' => $layout->id,
        'published_at' => now(),
        'settings' => ['public_shell' => PageLayoutCatalog::handles()[0] ?? 'default'],
      ],
    );

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $homePage->id, 'locale_id' => $defaultLocale->id],
      [
        'site_id' => $site->id,
        'name' => $site->display_name ?: $site->name,
        'slug' => 'home',
        'path' => '/',
      ],
    );

    foreach (['header', 'main', 'sidebar', 'footer'] as $index => $slotSlug) {
      $slotTypeId = SlotType::query()->where('slug', $slotSlug)->value('id');

      if (! $slotTypeId) {
        continue;
      }

      PageSlot::query()->updateOrCreate(
        [
          'page_id' => $homePage->id,
          'slot_type_id' => $slotTypeId,
        ],
        [
          'source_type' => PageSlot::SOURCE_TYPE_PAGE,
          'sort_order' => ($index + 1) * 10,
        ],
      );
    }

    $layoutHandle = PageLayout::query()->where('handle', 'default')->value('handle') ?? 'default';
    $settings = is_array($homePage->settings) ? $homePage->settings : [];
    $settings['public_shell'] = $layoutHandle;
    $homePage->forceFill(['settings' => $settings, 'layout_id' => $layout->id, 'status' => Page::STATUS_PUBLISHED, 'published_at' => $homePage->published_at ?? now()])->save();
  }

  private function ensureFirstSuperAdmin(Site $site): void
  {
    $userModel = config('auth.providers.users.model', 'App\\Models\\User');

    if (! is_string($userModel) || ! class_exists($userModel)) {
      throw new \RuntimeException('Configured auth user model was not found.');
    }

    $existingAdmin = $userModel::query()
      ->where('role', 'super_admin')
      ->where('is_active', true)
      ->first();

    if ($existingAdmin) {
      $this->components->info('An active super admin already exists. Leaving existing admin users unchanged.');

      return;
    }

    $name = (string) ($this->option('name') ?: ($this->input->isInteractive() ? $this->ask('First super admin name', self::DEFAULT_ADMIN_NAME) : self::DEFAULT_ADMIN_NAME));
    $email = (string) ($this->option('email') ?: ($this->input->isInteractive() ? $this->ask('First super admin email', self::DEFAULT_ADMIN_EMAIL) : self::DEFAULT_ADMIN_EMAIL));
    $password = (string) ($this->option('password') ?: ($this->input->isInteractive() ? $this->secret('First super admin password') : self::DEFAULT_ADMIN_PASSWORD));

    if ($password === '') {
      throw new \RuntimeException('A non-empty admin password is required.');
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \RuntimeException('A valid admin email address is required.');
    }

    $user = $userModel::query()->firstOrNew(['email' => Str::lower($email)]);
    $user->forceFill([
      'name' => $name !== '' ? $name : self::DEFAULT_ADMIN_NAME,
      'email' => Str::lower($email),
      'password' => Hash::make($password),
      'role' => 'super_admin',
      'is_admin' => true,
      'is_active' => true,
    ])->save();

    if (method_exists($user, 'sites')) {
      $user->sites()->sync([]);
    }

    $this->components->info('Created the first super admin: '.$user->email);
  }

  private function ensureCmsConfigIsResolvable(): void
  {
    if (! is_array(config('webblocks-cms'))) {
      throw new \RuntimeException('The webblocks-cms config is not available. Make sure the package service provider is loaded.');
    }
  }

  private function shouldPatchUserModel(): bool
  {
    $userModel = config('auth.providers.users.model', 'App\\Models\\User');

    return $userModel === 'App\\Models\\User';
  }

  private function publishPackageConfigIfMissing(): void
  {
    $source = realpath(__DIR__.'/../../config/webblocks-cms.php');

    if (! $source) {
      return;
    }

    $target = config_path('webblocks-cms.php');

    if (is_file($target) && ! $this->option('force')) {
      return;
    }

    File::ensureDirectoryExists(dirname($target));
    $this->files->copy($source, $target);
  }

  private function cmsSchemaAlreadyExists(): bool
  {
    foreach (['page_types', 'layout_types', 'slot_types', 'block_types', 'sites', 'locales', 'system_settings'] as $table) {
      if (! Schema::hasTable($table)) {
        return false;
      }
    }

    return true;
  }
}
