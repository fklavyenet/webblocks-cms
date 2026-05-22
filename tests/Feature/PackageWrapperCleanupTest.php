<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Console\SearchRebuildCommand;
use WebBlocks\Cms\Http\Controllers\Admin\PageController as PackageAdminPageController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemBackupController as PackageSystemBackupController;
use WebBlocks\Cms\Http\Controllers\Public\PageController as PackagePublicPageController;

class PackageWrapperCleanupTest extends TestCase
{
  #[Test]
  public function maintenance_app_keeps_only_host_owned_and_operational_transition_php_files(): void
  {
    $actual = collect($this->appPhpFiles())
      ->sort()
      ->values()
      ->all();

    $unexpected = array_values(array_diff($actual, $this->allowedRootAppFiles()));

    $this->assertSame([], $unexpected);
  }

  #[Test]
  public function deleted_root_wrappers_are_not_required_for_package_admin_public_or_command_runtime(): void
  {
    $this->assertFileDoesNotExist(base_path('app/Http/Controllers/Admin/PageController.php'));
    $this->assertFileDoesNotExist(base_path('app/Http/Controllers/PageController.php'));
    $this->assertFileDoesNotExist(base_path('app/Console/Commands/SearchRebuildCommand.php'));
    $this->assertFileDoesNotExist(base_path('app/Models/Page.php'));
    $this->assertFileDoesNotExist(base_path('app/Support/Pages/PageRouteResolver.php'));

    $adminRoute = app('router')->getRoutes()->getByName('admin.pages.index');
    $publicRoute = app('router')->getRoutes()->getByName('home');
    $backupRoute = app('router')->getRoutes()->getByName('admin.system.backups.index');

    $this->assertSame(PackageAdminPageController::class.'@index', ltrim((string) $adminRoute?->getActionName(), '\\'));
    $this->assertSame(PackagePublicPageController::class.'@home', ltrim((string) $publicRoute?->getActionName(), '\\'));
    $this->assertSame(PackageSystemBackupController::class.'@index', ltrim((string) $backupRoute?->getActionName(), '\\'));
    $this->assertInstanceOf(SearchRebuildCommand::class, Artisan::all()['search:rebuild']);
  }

  #[Test]
  public function root_app_does_not_reintroduce_unallowlisted_package_wrapper_classes(): void
  {
    $violations = [];

    foreach ($this->appPhpFiles() as $path) {
      if (in_array($path, $this->allowedRootAppFiles(), true)) {
        continue;
      }

      $contents = (string) file_get_contents(base_path($path));

      if (str_contains($contents, 'extends Package') || str_contains($contents, 'extends \\WebBlocks\\Cms\\')) {
        $violations[] = $path;
      }
    }

    $this->assertSame([], $violations);
  }

  #[Test]
  public function removed_package_counterpart_directories_do_not_return(): void
  {
    foreach ($this->removedPackageCounterpartDirectories() as $path) {
      $this->assertDirectoryDoesNotExist(base_path($path));
    }
  }

  /**
  * @return list<string>
  */
  private function appPhpFiles(): array
  {
    $directory = new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS);
    $files = [];

    foreach (new \RecursiveIteratorIterator($directory) as $file) {
      if ($file->getExtension() !== 'php') {
        continue;
      }

      $files[] = str_replace(base_path().'/', '', $file->getPathname());
    }

    return $files;
  }

  /**
  * @return list<string>
  */
  private function allowedRootAppFiles(): array
  {
    return [
      'app/Console/Commands/ProjectInitCommand.php',
      'app/Http/Controllers/Auth/AuthenticatedSessionController.php',
      'app/Http/Controllers/Auth/ConfirmablePasswordController.php',
      'app/Http/Controllers/Auth/EmailVerificationNotificationController.php',
      'app/Http/Controllers/Auth/EmailVerificationPromptController.php',
      'app/Http/Controllers/Auth/NewPasswordController.php',
      'app/Http/Controllers/Auth/PasswordController.php',
      'app/Http/Controllers/Auth/PasswordResetLinkController.php',
      'app/Http/Controllers/Auth/RegisteredUserController.php',
      'app/Http/Controllers/Auth/VerifyEmailController.php',
      'app/Http/Controllers/Controller.php',
      'app/Http/Controllers/Install/InstallWizardController.php',
      'app/Http/Controllers/ProfileController.php',
      'app/Http/Middleware/RedirectIfInstalled.php',
      'app/Http/Middleware/RedirectIfNotInstalled.php',
      'app/Http/Requests/Auth/LoginRequest.php',
      'app/Http/Requests/ProfileUpdateRequest.php',
      'app/Models/User.php',
      'app/Providers/AppServiceProvider.php',
      'app/Providers/AuthServiceProvider.php',
      'app/Providers/ProjectLayerServiceProvider.php',
      'app/Support/Install/EnvironmentWriter.php',
      'app/Support/Install/Installer.php',
      'app/Support/Install/InstallState.php',
      'app/Support/ProjectLayer/ProjectLayer.php',
      'app/View/Components/AppLayout.php',
      'app/View/Components/GuestLayout.php',
    ];
  }

  /**
  * @return list<string>
  */
  private function removedPackageCounterpartDirectories(): array
  {
    return [
      'app/Http/Controllers/Admin',
      'app/Http/Controllers/AdminApi',
      'app/Http/Requests/Admin',
      'app/Mail',
      'app/Models/Concerns',
      'app/Support/Assets',
      'app/Support/Admin',
      'app/Support/Audit',
      'app/Support/BlockTypes',
      'app/Support/Blocks',
      'app/Support/Contact',
      'app/Support/Database',
      'app/Support/Development',
      'app/Support/Formatting',
      'app/Support/Icons',
      'app/Support/Locales',
      'app/Support/Media',
      'app/Support/Navigation',
      'app/Support/Pages',
      'app/Support/PublicRendering',
      'app/Support/Search',
      'app/Support/SharedSlots',
      'app/Support/SitePromotion',
      'app/Support/Sites',
      'app/Support/System',
      'app/Support/Users',
      'app/Support/Visitors',
    ];
  }
}
