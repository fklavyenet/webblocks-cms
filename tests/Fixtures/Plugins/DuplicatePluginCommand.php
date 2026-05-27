<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Console\Command;

class DuplicatePluginCommand extends Command
{
  protected $signature = 'ecosystem-tools:sync';
}
