<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\Updates\Client\Publishing\SigningKeyGenerator;

final class KeygenCommand extends Command
{
    protected $signature = 'publisher:updates:keygen';

    protected $description = 'Generate an Ed25519 release signing keypair (owner-side).';

    public function handle(SigningKeyGenerator $generator): int
    {
        $keys = $generator->generate();

        $this->warn('Store the signing key secretly. It is never shipped to consumers.');
        $this->newLine();
        $this->line('# Owner-only (Publisher / release machine .env):');
        $this->line('WEBBLOCKS_PUBLISHER_SIGNING_KEY='.$keys['signing_key']);
        $this->newLine();
        $this->line('# Pin in every product (publisher-client.signature.public_key):');
        $this->line('PUBLISHER_CLIENT_PUBLIC_KEY='.$keys['public_key']);

        return self::SUCCESS;
    }
}
