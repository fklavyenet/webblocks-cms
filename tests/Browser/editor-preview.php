<?php

use WebBlocks\Cms\Tests\Support\BrowserFixtureServer;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// php -S 127.0.0.1:8089 tests/Browser/editor-preview.php
BrowserFixtureServer::run('editor-preview');
