<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Console\Command;

class InvalidPluginCommand extends Command
{
  protected $signature = 'cms:sync';
}
