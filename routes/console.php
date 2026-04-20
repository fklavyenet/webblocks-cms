<?php

use App\Support\Imports\LegacyFklavyeSandboxImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('legacy:import-fklavye-sandbox
    {--source-db-host=ddev-project-fklavye-net-db : Source MariaDB host reachable from the target runtime}
    {--source-db-port=3306 : Source MariaDB port}
    {--source-db-database=db : Source database name}
    {--source-db-username=db : Source database username}
    {--source-db-password=db : Source database password}
    {--force : Skip the confirmation prompt before clearing target site content}', function (LegacyFklavyeSandboxImporter $importer) {
    if (! $this->option('force') && ! $this->confirm('This will remove current target pages, blocks, navigation, and media records before importing the legacy sandbox content. Continue?')) {
        return self::FAILURE;
    }

    $summary = $importer->import([
        'host' => (string) $this->option('source-db-host'),
        'port' => (int) $this->option('source-db-port'),
        'database' => (string) $this->option('source-db-database'),
        'username' => (string) $this->option('source-db-username'),
        'password' => (string) $this->option('source-db-password'),
    ]);

    $this->info('Legacy sandbox import completed.');
    $this->table(['Target cleanup category', 'Removed rows'], collect($summary['cleanup'])->map(fn ($count, $label) => [$label, $count])->values());
    $this->table(['Imported category', 'Total rows'], collect($summary['imported'])->map(fn ($count, $label) => [$label, $count])->values());

    return self::SUCCESS;
})->purpose('Import the legacy fklavye sandbox content into the current CMS install');
