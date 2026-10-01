<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalInventoryController;

$consumer = $argv[1] ?? throw new RuntimeException('Consumer path required.');
require $consumer.'/vendor/autoload.php';
$app = require $consumer.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$package = $consumer.'/vendor/fklavyenet/webblocks-cms';
$contract = $package.'/resources/contracts/inventory.md';
$response = $app->make(InternalInventoryController::class)->show();
$payload = $response->getData(true);

if (is_dir($package.'/docs') || ! is_file($package.'/LICENSE')
  || $response->getStatusCode() !== 200
  || ($payload['inventory']['document'] ?? null) !== 'resources/contracts/inventory.md'
  || ($payload['inventory']['checksum_sha256'] ?? null) !== hash_file('sha256', $contract)) {
  throw new RuntimeException('Installed CMS must serve its product inventory without documentation sources.');
}

echo 'Installed consumer inventory works without the docs repository.'.PHP_EOL;
