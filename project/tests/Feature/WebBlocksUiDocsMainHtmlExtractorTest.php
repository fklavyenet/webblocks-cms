<?php

namespace Project\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Project\Support\UiDocs\WebBlocksUiDocsMainHtmlExtractor;
use RuntimeException;
use Tests\TestCase;

class WebBlocksUiDocsMainHtmlExtractorTest extends TestCase
{
    #[Test]
    public function extractor_keeps_main_content_and_rewrites_docs_links(): void
    {
        $extractor = app(WebBlocksUiDocsMainHtmlExtractor::class);

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
  <header class="wb-navbar">Header</header>
  <main class="wb-dashboard-main">
    <div class="wb-content-shell wb-docs-main">
      <section>
        <a href="utilities.html">Utilities</a>
        <a href="../playground/">Playground</a>
        <img src="../assets/example.png" alt="Example">
        <script>alert(1)</script>
      </section>
    </div>
  </main>
  <div id="wb-overlay-root">overlay</div>
</body>
</html>
HTML;

        $fragment = $extractor->extract($html, 'https://ui.webblocksui.com/docs/patterns.html');

        $this->assertStringContainsString('href="/p/utilities"', $fragment);
        $this->assertStringContainsString('href="../playground/"', $fragment);
        $this->assertStringContainsString('src="https://ui.webblocksui.com/assets/example.png"', $fragment);
        $this->assertStringNotContainsString('wb-navbar', $fragment);
        $this->assertStringNotContainsString('<script', $fragment);
        $this->assertStringContainsString('wb-overlay-root', $fragment);
        $this->assertSame(1, substr_count($fragment, 'id="wb-overlay-root"'));
    }

    #[Test]
    public function extractor_throws_when_no_main_content_can_be_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not extract docs main HTML from source page.');

        app(WebBlocksUiDocsMainHtmlExtractor::class)->extract('<html><body><div>No main</div></body></html>', 'https://ui.webblocksui.com/docs/test.html');
    }
}
