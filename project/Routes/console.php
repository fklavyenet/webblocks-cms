<?php

use Illuminate\Console\Application as ArtisanApplication;
use Project\Console\Commands\SyncUiDocsGettingStartedCommand;
use Project\Console\Commands\SyncUiDocsHomeMainCommand;
use Project\Console\Commands\SyncUiDocsNavigationCommand;

require_once base_path('project/Console/Commands/SyncUiDocsNavigationCommand.php');
require_once base_path('project/Console/Commands/SyncUiDocsGettingStartedCommand.php');
require_once base_path('project/Console/Commands/SyncUiDocsHomeMainCommand.php');

ArtisanApplication::starting(function ($artisan): void {
    $artisan->resolveCommands([
        SyncUiDocsNavigationCommand::class,
        SyncUiDocsGettingStartedCommand::class,
        SyncUiDocsHomeMainCommand::class,
    ]);
});
