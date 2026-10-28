<?php

use WebBlocks\Cms\Tests\Support\BrowserFixtureServer;

require dirname(__DIR__, 2).'/vendor/autoload.php';

BrowserFixtureServer::run('dirty-overlay');
