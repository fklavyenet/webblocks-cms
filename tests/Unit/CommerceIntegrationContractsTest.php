<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Commerce\CommerceFulfillmentConsumer;
use WebBlocks\Cms\Support\Commerce\CommerceOfferProvider;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;

final class CommerceIntegrationContractsTest extends TestCase
{
    public function test_product_neutral_ports_use_the_existing_plugin_service_extension_point(): void
    {
        $definition = PluginDefinition::make('synthetic-sales-port')->services([
            CommerceOfferProvider::class => SyntheticOfferPort::class,
            CommerceFulfillmentConsumer::class => SyntheticFulfillmentPort::class,
        ]);

        $this->assertSame([
            CommerceOfferProvider::class => SyntheticOfferPort::class,
            CommerceFulfillmentConsumer::class => SyntheticFulfillmentPort::class,
        ], $definition->serviceDefinitions());
        $this->assertInstanceOf(CommerceOfferProvider::class, new SyntheticOfferPort);
        $this->assertInstanceOf(CommerceFulfillmentConsumer::class, new SyntheticFulfillmentPort);
    }
}

final class SyntheticOfferPort implements CommerceOfferProvider
{
    public function capabilities(): array
    {
    return [];
    }

    public function offers(?string $cursor = null): array
    {
    return ['offers' => [], 'next_cursor' => null];
    }

    public function quote(array $selection): array
    {
    return [];
    }

    public function prepare(array $command): array
    {
    return [];
    }

    public function intent(string $requestId): array
    {
    return [];
    }
}

final class SyntheticFulfillmentPort implements CommerceFulfillmentConsumer
{
    public function accept(array $event, array $fulfillment): array
    {
    return [];
    }

    public function status(string $fulfillmentRef): array
    {
    return [];
    }
}
