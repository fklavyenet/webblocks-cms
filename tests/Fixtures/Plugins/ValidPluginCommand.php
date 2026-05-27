<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Console\Command;

class ValidPluginCommand extends Command
{
  protected $signature = 'ecosystem-tools:sync';
}
