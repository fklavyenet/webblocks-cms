<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ProjectInitCommand extends Command
{
    protected $signature = 'project:init';

    protected $description = 'Create the update-safe project layer scaffold';

    public function handle(): int
    {
        foreach ($this->directories() as $relativePath) {
            $this->ensureDirectory($relativePath);
        }

        foreach ($this->files() as $relativePath => $contents) {
            $this->ensureFile($relativePath, $contents);
        }

        $this->info('Project layer scaffold ready.');

        return self::SUCCESS;
    }

    private function directories(): array
    {
        return [
            'project/Providers',
            'project/Routes',
            'project/Console/Commands',
            'project/config',
            'project/resources/views',
        ];
    }

    private function files(): array
    {
        return [
            'project/README.md' => <<<'MARKDOWN'
# Project Layer

Use `project/` for site-specific code that must survive CMS core updates.

## Structure

- `Providers/`
- `Routes/`
- `Console/Commands/`
- `config/`
- `resources/views/`

## Rules

- Keep instance-specific code here instead of core `app/`, `routes/`, `resources/`, or `config/`.
- Project config loads under the `project.*` namespace.
- Project views are available through the `project::` namespace.
- This layer is for one install or site instance. It is not the plugin system.
MARKDOWN,
            'project/config/providers.php' => <<<'PHP'
<?php

return [
    // Project\Providers\ProjectServiceProvider::class,
];
PHP,
            'project/config/sites.php' => <<<'PHP'
<?php

return [
    // Instance-specific site settings live here.
];
PHP,
            'project/Routes/web.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project Web Routes
|--------------------------------------------------------------------------
|
| Register update-safe, instance-specific routes here.
|
*/

// Route::get('/project-health', fn () => 'ok');
PHP,
        ];
    }

    private function ensureDirectory(string $relativePath): void
    {
        $path = base_path($relativePath);

        if (is_dir($path)) {
            $this->line('Exists: '.$relativePath);

            return;
        }

        File::ensureDirectoryExists($path);
        $this->info('Created: '.$relativePath);
    }

    private function ensureFile(string $relativePath, string $contents): void
    {
        $path = base_path($relativePath);

        if (is_file($path)) {
            $this->line('Exists: '.$relativePath);

            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents.PHP_EOL);
        $this->info('Created: '.$relativePath);
    }
}
