<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

class InternalApiResponseMetadata
{
  public function links(): array
  {
    return [
      'api_discovery_url' => '/webadmin/api',
      'openapi_url' => '/webadmin/api/openapi.json',
      'documentation_url' => '/webadmin/api/ai-guide',
      'example_url' => '/webadmin/api/examples/contact-page',
    ];
  }

  public function merge(array $payload): array
  {
    return [
      ...$payload,
      ...$this->links(),
    ];
  }
}
