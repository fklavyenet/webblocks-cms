<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MediaVariantCardStructureTest extends TestCase
{
  #[Test]
  public function image_variants_use_equal_preview_cards_with_structured_regions(): void
  {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/admin/media/edit.blade.php');
    $css = file_get_contents($root.'/public/cms/css/admin.css');

    $this->assertStringContainsString('class="wb-card-body wb-grid-auto wb-grid-auto-sm wb-gap-3"', $view);
    $this->assertStringContainsString('class="wb-card wb-card-muted wb-media-variant-card"', $view);
    $this->assertMatchesRegularExpression('/wb-media-variant-card[\s\S]*wb-card-header[\s\S]*wb-card-body wb-media-variant-preview[\s\S]*wb-card-footer/', $view);
    $this->assertStringContainsString("str(\$variant['name'])->headline()", $view);
    $this->assertStringContainsString("\$adminText('not_generated_yet')", $view);
    $this->assertStringNotContainsString('.wb-media-variant-grid', $css);
    $this->assertMatchesRegularExpression('/\.wb-media-variant-preview\s*\{[^}]*height:\s*12rem;/s', $css);
    $this->assertMatchesRegularExpression('/\.wb-media-variant-preview img\s*\{[^}]*object-fit:\s*contain;/s', $css);
  }
}
