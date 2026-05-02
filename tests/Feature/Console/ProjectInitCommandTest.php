<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithProjectLayerFiles;
use Tests\TestCase;

class ProjectInitCommandTest extends TestCase
{
    use InteractsWithProjectLayerFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateProjectLayer();
    }

    protected function tearDown(): void
    {
        $this->restoreProjectLayer();
        $this->refreshApplication();

        parent::tearDown();
    }

    #[Test]
    public function project_init_creates_the_scaffold_directories_and_files(): void
    {
        $this->artisan('project:init')
            ->expectsOutputToContain('Created: project/Providers')
            ->expectsOutputToContain('Project layer scaffold ready.')
            ->assertExitCode(0);

        $this->assertDirectoryExists(base_path('project/Providers'));
        $this->assertDirectoryExists(base_path('project/Routes'));
        $this->assertDirectoryExists(base_path('project/Console/Commands'));
        $this->assertDirectoryExists(base_path('project/Support'));
        $this->assertDirectoryExists(base_path('project/config'));
        $this->assertDirectoryExists(base_path('project/resources/views'));
        $this->assertFileExists(base_path('project/README.md'));
        $this->assertFileExists(base_path('project/config/providers.php'));
        $this->assertFileExists(base_path('project/config/sites.php'));
        $this->assertFileExists(base_path('project/Routes/web.php'));
        $this->assertFileExists(base_path('project/Routes/console.php'));
    }

    #[Test]
    public function project_init_does_not_overwrite_existing_files(): void
    {
        File::ensureDirectoryExists(base_path('project/config'));
        File::ensureDirectoryExists(base_path('project/Routes'));
        File::put(base_path('project/config/providers.php'), "<?php\n\nreturn ['existing-provider'];\n");
        File::put(base_path('project/README.md'), "Existing README\n");

        $this->artisan('project:init')
            ->expectsOutputToContain('Exists: project/config/providers.php')
            ->expectsOutputToContain('Exists: project/README.md')
            ->assertExitCode(0);

        $this->assertSame("<?php\n\nreturn ['existing-provider'];\n", File::get(base_path('project/config/providers.php')));
        $this->assertSame("Existing README\n", File::get(base_path('project/README.md')));
    }
}
