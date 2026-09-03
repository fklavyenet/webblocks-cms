<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\PersonalApiTokenNetworkPolicy;

class PersonalApiTokenNetworkPolicyTest extends TestCase
{
  #[Test]
  public function it_accepts_exact_addresses_and_ipv4_or_ipv6_cidr_networks(): void
  {
    $policy = new PersonalApiTokenNetworkPolicy;

    foreach (['203.0.113.7', '203.0.113.0/24', '2001:db8::/32'] as $range) {
      $this->assertTrue($policy->valid($range));
    }

    foreach (['203.0.113.999', '203.0.113.0/40', '2001:db8::/129', 'invalid'] as $range) {
      $this->assertFalse($policy->valid($range));
    }
  }

  #[Test]
  public function it_allows_any_network_when_unset_and_enforces_configured_ranges(): void
  {
    $policy = new PersonalApiTokenNetworkPolicy;

    $this->assertTrue($policy->allows(new CmsApiToken(['allowed_ip_ranges' => []]), '198.51.100.3'));

    $token = new CmsApiToken(['allowed_ip_ranges' => ['203.0.113.0/24', '2001:db8::7']]);

    $this->assertTrue($policy->allows($token, '203.0.113.99'));
    $this->assertTrue($policy->allows($token, '2001:db8::7'));
    $this->assertFalse($policy->allows($token, '198.51.100.3'));
  }
}
