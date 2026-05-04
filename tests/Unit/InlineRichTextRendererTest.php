<?php

namespace Tests\Unit;

use App\Support\Formatting\InlineRichTextRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InlineRichTextRendererTest extends TestCase
{
    #[Test]
    public function it_renders_backtick_wrapped_text_as_inline_code(): void
    {
        $html = app(InlineRichTextRenderer::class)->render('Use `light`, `dark`, or `auto`.')->toHtml();

        $this->assertSame('Use <code>light</code>, <code>dark</code>, or <code>auto</code>.', $html);
    }

    #[Test]
    public function it_escapes_normal_text(): void
    {
        $html = app(InlineRichTextRenderer::class)->render('<strong>unsafe</strong> text')->toHtml();

        $this->assertSame('&lt;strong&gt;unsafe&lt;/strong&gt; text', $html);
    }

    #[Test]
    public function it_escapes_script_tags_while_still_rendering_inline_code(): void
    {
        $html = app(InlineRichTextRenderer::class)->render('<script>alert(1)</script> `x`')->toHtml();

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt; <code>x</code>', $html);
    }

    #[Test]
    public function it_escapes_html_inside_code_segments(): void
    {
        $html = app(InlineRichTextRenderer::class)->render('Use `<b>x</b>`')->toHtml();

        $this->assertSame('Use <code>&lt;b&gt;x&lt;/b&gt;</code>', $html);
    }

    #[Test]
    public function it_leaves_unmatched_backticks_as_escaped_text(): void
    {
        $html = app(InlineRichTextRenderer::class)->render('Use `auto safely')->toHtml();

        $this->assertSame('Use `auto safely', $html);
    }

    #[Test]
    public function it_returns_empty_string_for_null_or_empty_input(): void
    {
        $renderer = app(InlineRichTextRenderer::class);

        $this->assertSame('', $renderer->render(null)->toHtml());
        $this->assertSame('', $renderer->render('')->toHtml());
    }
}
