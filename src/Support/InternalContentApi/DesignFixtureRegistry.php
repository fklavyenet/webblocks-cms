<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

class DesignFixtureRegistry
{
  public function section(): array
  {
    $fixtures = collect(config('design_fixtures.fixtures', []))
      ->map(fn (array $fixture, string $handle): array => ['handle' => $handle] + $fixture)
      ->values()
      ->all();

    return [
      'status' => 'render_contract_ready_capture_pending',
      'viewports' => config('design_fixtures.viewports', []),
      'fixtures' => $fixtures,
      'capture_contract' => [
        'source' => 'Render the fixture tree through the normal CMS public renderer with WebBlocks UI and public.css loaded.',
        'required_modes' => ['light', 'dark'],
        'required_viewports' => ['desktop', 'mobile'],
        'required_states' => ['default'],
        'storage' => 'docs/visual-fixtures/{fixture}/{mode}-{viewport}.png',
        'approval' => 'Captured images become canonical only after human visual review.',
      ],
    ];
  }
}
