<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Tests\TestCase;

class MediaSearchTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function api_literal_mode_is_opt_in_and_existing_patterns_keep_their_results(): void
  {
    $titles = ['Offer!%_2026', 'Offer!winter_2026', 'Offer!winterXY2026', 'Offer!2026'];
    $ids = [];
    foreach ($titles as $index => $title) {
      $file = 'offer-'.$index.'.png';
      $ids[] = Media::create(['disk' => 'public', 'path' => $file, 'filename' => $file, 'title' => $title, 'kind' => 'image'])->id;
    }
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic media reader', capabilities: ['media.read'])->plainToken;
    $this->withToken($token);
    $request = fn (array $query) => $this->getJson(route('internal-content-api.media.index', $query));

    foreach (['Offer!%_2026' => [$ids[2], $ids[1], $ids[0]], 'Offer!_2026' => [], 'Offer!winter_2026' => [$ids[1]], 'Offer!%2026' => array_reverse($ids)] as $term => $expected) {
      $this->assertSame($expected, array_column($request(['search' => $term])->assertOk()->json('media'), 'id'));
    }
    $pattern = ['search' => 'Offer!%_2026'];
    $this->assertSame([$ids[2], $ids[1], $ids[0]], array_column($request($pattern + ['search_mode' => 'pattern'])->assertOk()->json('media'), 'id'));
    $this->assertSame([$ids[0]], array_column($request($pattern + ['search_mode' => 'literal'])->assertOk()->json('media'), 'id'));
    $request($pattern + ['search_mode' => 'unknown'])->assertUnprocessable()->assertJsonPath('errors.0.path', 'search_mode');
    $request($pattern + ['search_mode' => ['literal']])->assertUnprocessable()->assertJsonPath('errors.0.path', 'search_mode');
  }
}
