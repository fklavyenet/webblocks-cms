<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiPageBuildingGuideDocsTest extends TestCase
{
  #[Test]
  public function ai_page_building_guide_is_linked_from_documentation_surfaces(): void
  {
    $guide = file_get_contents(base_path('docs/ai-page-building-guide.md'));
    $docsIndex = file_get_contents(base_path('docs/index.md'));
    $readme = file_get_contents(base_path('README.md'));
    $changelog = file_get_contents(base_path('CHANGELOG.md'));
    $apiDiscovery = file_get_contents(base_path('docs/api-discovery.md'));

    $this->assertIsString($guide);
    $this->assertIsString($docsIndex);
    $this->assertIsString($readme);
    $this->assertIsString($changelog);
    $this->assertIsString($apiDiscovery);

    $this->assertStringContainsString('# AI Page Building Guide', $guide);
    $this->assertStringContainsString('GET /webadmin/api', $guide);
    $this->assertStringContainsString('GET /webadmin/api/content-contract', $guide);
    $this->assertStringContainsString('POST /webadmin/api/content/validate', $guide);
    $this->assertStringContainsString('POST /webadmin/api/content/apply', $guide);
    $this->assertStringContainsString('/webadmin/pages/{page}/preview', $guide);
    $this->assertStringContainsString('vendor/fklavyenet/webblocks-cms/docs/ai-page-building-guide.md', $guide);
    $this->assertStringContainsString('# API Discovery', $apiDiscovery);
    $this->assertStringContainsString('GET /webadmin/api/openapi.json', $apiDiscovery);
    $this->assertStringContainsString('[API Discovery](api-discovery.md)', $docsIndex);
    $this->assertStringContainsString('[API Discovery](docs/api-discovery.md)', $readme);
    $this->assertStringContainsString('[AI Page Building Guide](ai-page-building-guide.md)', $docsIndex);
    $this->assertStringContainsString('[AI Page Building Guide](docs/ai-page-building-guide.md)', $readme);
    $this->assertStringContainsString('/webadmin/api', $changelog);
  }
}
