<?php

namespace Tests\Unit;

use App\Models\Block;
use App\Models\BlockTextTranslation;
use App\Support\Blocks\BlockAdminSummary;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockAdminSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSiteLocaleSeeder::class);
    }

    #[Test]
    public function it_strips_html_from_rich_text_summaries(): void
    {
        $block = $this->textBlock('rich-text', '<p>Hello <strong>world</strong></p>');

        $this->assertSame('Hello world', app(BlockAdminSummary::class)->label($block));
    }

    #[Test]
    public function it_decodes_entities_and_collapses_whitespace(): void
    {
        $block = $this->textBlock('plain_text', "Alpha&nbsp;&amp;&nbsp;Beta\n\tGamma");

        $this->assertSame('Alpha & Beta Gamma', app(BlockAdminSummary::class)->label($block));
    }

    #[Test]
    public function it_truncates_long_content(): void
    {
        $block = $this->textBlock('plain_text', str_repeat('Alpha Beta ', 20));
        $label = app(BlockAdminSummary::class)->label($block, 40);

        $this->assertSame(40, mb_strlen($label));
        $this->assertStringEndsWith('...', $label);
    }

    #[Test]
    public function it_handles_empty_content_safely(): void
    {
        $block = $this->textBlock('plain_text', '   ');
        $presented = app(BlockAdminSummary::class)->present($block);

        $this->assertSame('Plain Text', $presented['label']);
        $this->assertNull($presented['summary']);
    }

    #[Test]
    public function it_formats_code_summary_with_language_and_first_line(): void
    {
        $block = new Block([
            'type' => 'code',
            'content' => "<div class=\"hero\">\n    Example\n</div>",
            'settings' => ['language' => 'html'],
        ]);
        $block->setRelation('children', new EloquentCollection);

        $this->assertSame('HTML | <div class="hero">', app(BlockAdminSummary::class)->label($block));
        $this->assertNull(app(BlockAdminSummary::class)->summary($block));
    }

    #[Test]
    public function compact_summary_rules_remain_unchanged_for_rich_text_blocks(): void
    {
        $block = $this->textBlock('rich-text', '<p>Hello <strong>world</strong></p>');

        $presented = app(BlockAdminSummary::class)->present($block);

        $this->assertSame('Hello world', $presented['label']);
        $this->assertNull($presented['summary']);
    }

    private function textBlock(string $type, ?string $content = null, ?string $title = null, ?string $subtitle = null): Block
    {
        $block = new Block([
            'type' => $type,
            'title' => null,
            'subtitle' => null,
            'content' => null,
        ]);

        $block->setRelation('children', new EloquentCollection);
        $block->setRelation('textTranslations', new EloquentCollection([
            new BlockTextTranslation([
                'locale_id' => 1,
                'title' => $title,
                'subtitle' => $subtitle,
                'content' => $content,
            ]),
        ]));

        return $block;
    }
}
