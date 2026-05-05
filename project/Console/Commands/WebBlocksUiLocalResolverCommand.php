<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Project\Support\UiDocs\SetupWebBlocksUiDocsSite;
use Project\Support\UiDocs\WebBlocksUiLocalResolver;

class WebBlocksUiLocalResolverCommand extends Command
{
    protected $signature = 'project:webblocksui-local-resolver';

    protected $description = 'Configure the local DDEV resolver for the WebBlocks UI docs preview host';

    public function __construct(private readonly WebBlocksUiLocalResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->resolver->ensure();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Local resolver approach: DDEV additional_hostnames override');
        $this->line('Resolver config file: '.WebBlocksUiLocalResolver::CONFIG_PATH);
        $this->line('Configured local host: '.SetupWebBlocksUiDocsSite::localDdevDomain());

        if ($result['changed']) {
            $this->info('Resolver config updated.');
            $this->warn('Run ddev restart to apply the new local host alias.');
        } else {
            $this->info('Resolver config already up to date.');
        }

        return self::SUCCESS;
    }
}
