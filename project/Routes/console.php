<?php

use Illuminate\Console\Application as ArtisanApplication;
use Project\Console\Commands\SyncUiDocsGettingStartedCommand;
use Project\Console\Commands\SyncUiDocsHomeMainCommand;
use Project\Console\Commands\SyncUiDocsNavigationCommand;
use Project\Console\Commands\SetupWebBlocksUiDocsSiteCommand;
use Project\Console\Commands\WebBlocksUiImportCommand;

require_once base_path('project/Console/Commands/SyncUiDocsNavigationCommand.php');
require_once base_path('project/Console/Commands/SyncUiDocsGettingStartedCommand.php');
require_once base_path('project/Console/Commands/SyncUiDocsHomeMainCommand.php');
require_once base_path('project/Console/Commands/SetupWebBlocksUiDocsSiteCommand.php');
require_once base_path('project/Console/Commands/WebBlocksUiImportCommand.php');

ArtisanApplication::starting(function ($artisan): void {
    $artisan->resolveCommands([
        SyncUiDocsNavigationCommand::class,
        SyncUiDocsGettingStartedCommand::class,
        SyncUiDocsHomeMainCommand::class,
        SetupWebBlocksUiDocsSiteCommand::class,
        WebBlocksUiImportCommand::class,
    ]);
});
