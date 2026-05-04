<?php

namespace Tests\Unit;

use App\Support\Formatting\SafeRichTextRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SafeRichTextRendererTest extends TestCase
{
    #[Test]
    public function it_renders_a_paragraph(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('This is a paragraph.')->toHtml();

        $this->assertSame('<p>This is a paragraph.</p>', $html);
    }

    #[Test]
    public function it_renders_two_paragraphs_when_separated_by_a_blank_line(): void
    {
        $html = app(SafeRichTextRenderer::class)->render("First paragraph.\n\nSecond paragraph.")->toHtml();

        $this->assertSame('<p>First paragraph.</p><p>Second paragraph.</p>', $html);
    }

    #[Test]
    public function it_renders_inline_code(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('Use `auto` mode.')->toHtml();

        $this->assertSame('<p>Use <code>auto</code> mode.</p>', $html);
    }

    #[Test]
    public function it_renders_bold_text(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('Use **bold** text.')->toHtml();

        $this->assertSame('<p>Use <strong>bold</strong> text.</p>', $html);
    }

    #[Test]
    public function it_renders_italic_text(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('Use *italic* text.')->toHtml();

        $this->assertSame('<p>Use <em>italic</em> text.</p>', $html);
    }

    #[Test]
    public function it_renders_safe_links(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('Read [the docs](https://example.com).')->toHtml();

        $this->assertSame('<p>Read <a href="https://example.com" rel="noopener noreferrer">the docs</a>.</p>', $html);
    }

    #[Test]
    public function it_does_not_render_unsafe_javascript_links(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('[bad](javascript:alert(1))')->toHtml();

        $this->assertSame('<p>[bad](javascript:alert(1))</p>', html_entity_decode($html, ENT_QUOTES));
        $this->assertStringNotContainsString('href="javascript:alert(1)"', $html);
    }

    #[Test]
    public function it_renders_a_bullet_list(): void
    {
        $html = app(SafeRichTextRenderer::class)->render("- First item\n- Second item")->toHtml();

        $this->assertSame('<ul><li>First item</li><li>Second item</li></ul>', $html);
    }

    #[Test]
    public function it_renders_an_ordered_list(): void
    {
        $html = app(SafeRichTextRenderer::class)->render("1. First item\n2. Second item")->toHtml();

        $this->assertSame('<ol><li>First item</li><li>Second item</li></ol>', $html);
    }

    #[Test]
    public function it_renders_inline_formatting_inside_list_items(): void
    {
        $html = app(SafeRichTextRenderer::class)->render("- Use `code`\n- Add **bold** text")->toHtml();

        $this->assertSame('<ul><li>Use <code>code</code></li><li>Add <strong>bold</strong> text</li></ul>', $html);
    }

    #[Test]
    public function it_escapes_script_tags(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('<script>alert(1)</script>')->toHtml();

        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
    }

    #[Test]
    public function it_escapes_html_inside_code_segments(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('`<b>x</b>`')->toHtml();

        $this->assertSame('<p><code>&lt;b&gt;x&lt;/b&gt;</code></p>', $html);
    }

    #[Test]
    public function it_leaves_unsupported_heading_syntax_as_plain_text(): void
    {
        $html = app(SafeRichTextRenderer::class)->render('# Heading')->toHtml();

        $this->assertSame('<p># Heading</p>', $html);
    }

    #[Test]
    public function it_returns_an_empty_string_for_null_or_empty_input(): void
    {
        $renderer = app(SafeRichTextRenderer::class);

        $this->assertSame('', $renderer->render(null)->toHtml());
        $this->assertSame('', $renderer->render('')->toHtml());
    }
}
