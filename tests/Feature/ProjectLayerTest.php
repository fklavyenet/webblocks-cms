<?php

namespace Tests\Feature;

use App\Support\ProjectLayer\ProjectLayer;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithProjectLayerFiles;
use Tests\TestCase;

class ProjectLayerTest extends TestCase
{
    use InteractsWithProjectLayerFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateProjectLayer();
    }

    protected function tearDown(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
        $_SERVER['APP_DEBUG'] = 'true';

        $this->restoreProjectLayer();
        $this->refreshApplication();

        parent::tearDown();
    }

    #[Test]
    public function app_boots_when_project_directory_is_absent(): void
    {
        $this->refreshApplication();

        $this->get('/login')->assertOk();
    }

    #[Test]
    public function project_config_is_loaded_under_the_project_namespace(): void
    {
        $this->writeProjectFile('config/sites.php', <<<'PHP'
<?php

return [
    'default_handle' => 'alpha',
    'features' => [
        'docs' => true,
    ],
];
PHP);

        $this->refreshApplication();

        $this->assertSame('alpha', config('project.sites.default_handle'));
        $this->assertTrue((bool) config('project.sites.features.docs'));
    }

    #[Test]
    public function project_web_routes_can_be_registered(): void
    {
        $this->writeProjectFile('Routes/web.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/project-layer-ping', fn () => 'project-layer-ok');
PHP);

        $this->refreshApplication();

        $this->get('/project-layer-ping')
            ->assertOk()
            ->assertSee('project-layer-ok');
    }

    #[Test]
    public function project_view_namespace_can_render_project_views(): void
    {
        $this->writeProjectFile('resources/views/docs/layout.blade.php', 'Project view: {{ $message }}');

        $this->refreshApplication();

        $this->assertSame('Project view: hello', trim(view('project::docs.layout', ['message' => 'hello'])->render()));
    }

    #[Test]
    public function project_provider_listed_in_project_config_can_be_registered(): void
    {
        $this->writeProjectFile('Providers/TestProjectServiceProvider.php', <<<'PHP'
<?php

namespace Project\Providers;

use Illuminate\Support\ServiceProvider;

class TestProjectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['project.provider_loaded' => true]);
    }
}
PHP);

        $this->writeProjectFile('config/providers.php', <<<'PHP'
<?php

return [
    Project\Providers\TestProjectServiceProvider::class,
];
PHP);

        $this->refreshApplication();

        $this->assertTrue((bool) config('project.provider_loaded'));
    }

    #[Test]
    public function missing_project_provider_does_not_crash_production_mode_and_logs_a_warning(): void
    {
        config()->set('app.debug', false);
        config()->set('project.providers', [
            'Project\\Providers\\MissingProjectServiceProvider',
        ]);

        Log::spy();

        app(ProjectLayer::class)->registerConfiguredProviders();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context = []) => $message === 'Project provider [Project\\Providers\\MissingProjectServiceProvider] could not be loaded.'
                && ($context['provider'] ?? null) === 'Project\\Providers\\MissingProjectServiceProvider');
    }
}
