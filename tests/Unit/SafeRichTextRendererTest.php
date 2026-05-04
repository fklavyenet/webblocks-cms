<?php

namespace Tests\Unit;

use App\Support\Formatting\SafeRichTextRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SafeRichTextRendererTest extends TestCase
{
    #[Test]
    public function it_allows_paragraphs(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<p>Text</p>')->toHtml();

        $this->assertSame('<p>Text</p>', $html);
    }

    #[Test]
    public function it_allows_inline_formatting_tags(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<p><strong>Bold</strong> <em>Italic</em> <code>Code</code></p>')->toHtml();

        $this->assertSame('<p><strong>Bold</strong> <em>Italic</em> <code>Code</code></p>', $html);
    }

    #[Test]
    public function it_allows_safe_links(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<p><a href="https://example.com">Docs</a></p>')->toHtml();

        $this->assertSame('<p><a href="https://example.com" rel="noopener noreferrer">Docs</a></p>', $html);
    }

    #[Test]
    public function it_strips_unsafe_link_href_while_preserving_link_text(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<p><a href="javascript:alert(1)">Bad link</a></p>')->toHtml();

        $this->assertSame('<p>Bad link</p>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    #[Test]
    public function it_strips_script_style_event_class_and_style_attributes(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<p class="x" style="color:red" onclick="alert(1)">Safe<script>alert(1)</script><span style="font-weight:bold"> text</span></p>')->toHtml();

        $this->assertSame('<p>Safe text</p>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('class=', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function it_strips_unsupported_tags(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<h2>Heading</h2><p>Keep</p><img src="/x.png"><iframe src="https://example.com"></iframe><table><tr><td>X</td></tr></table><button>Tap</button>')->toHtml();

        $this->assertSame('<p>Keep</p>', $html);
    }

    #[Test]
    public function it_allows_lists(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<ul><li>First</li><li>Second</li></ul><ol><li>One</li><li>Two</li></ol>')->toHtml();

        $this->assertSame('<ul><li>First</li><li>Second</li></ul><ol><li>One</li><li>Two</li></ol>', $html);
    }

    #[Test]
    public function it_preserves_nested_inline_formatting_inside_paragraphs_and_list_items(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<p><strong><em>Nested</em></strong> text</p><ul><li><a href="/docs"><code>Docs</code></a></li></ul>')->toHtml();

        $this->assertSame('<p><strong><em>Nested</em></strong> text</p><ul><li><a href="/docs"><code>Docs</code></a></li></ul>', $html);
    }

    #[Test]
    public function it_returns_empty_output_for_empty_or_unsafe_only_content(): void
    {
        $renderer = app(SafeRichTextRenderer::class);

        $this->assertSame('', $renderer->render(null)->toHtml());
        $this->assertSame('', $renderer->render('')->toHtml());
        $this->assertSame('', $renderer->render('<script>alert(1)</script><style>body{}</style><iframe src="https://example.com"></iframe>')->toHtml());
    }

    #[Test]
    public function it_does_not_treat_markdown_markers_as_formatting(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<p>Use **bold**, *italic*, `code`, and [docs](https://example.com).</p>')->toHtml();

        $this->assertSame('<p>Use **bold**, *italic*, `code`, and [docs](https://example.com).</p>', $html);
    }
}
