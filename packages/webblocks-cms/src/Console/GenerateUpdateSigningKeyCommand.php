<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;

class GenerateUpdateSigningKeyCommand extends Command
{
  protected $signature = 'webblocks:updates:keygen';

  protected $description = 'Generate an Ed25519 key pair for signing WebBlocks CMS update releases';

  public function handle(): int
  {
    $keyPair = sodium_crypto_sign_keypair();
    $secret = base64_encode(sodium_crypto_sign_secretkey($keyPair));
    $public = base64_encode(sodium_crypto_sign_publickey($keyPair));

    $this->info('Generated an Ed25519 update-signing key pair.');
    $this->newLine();

    $this->warn('Keep the SECRET key private. Only the maintainer that publishes releases needs it:');
    $this->line('  WEBBLOCKS_PUBLISHER_SIGNING_KEY='.$secret);
    $this->newLine();

    $this->line('Pin the PUBLIC key so installs verify signed releases. Set the env var, or set');
    $this->line('ReleaseDefaults::UPDATE_PUBLIC_KEY so the key ships in the CMS code:');
    $this->line('  WEBBLOCKS_UPDATE_PUBLIC_KEY='.$public);
    $this->newLine();

    $this->line('Never commit the secret key and never set it on an installed site.');

    return self::SUCCESS;
  }
}
