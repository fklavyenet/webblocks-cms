<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockTypeContractsAuditCommandTest extends TestCase
{
    #[Test]
    public function it_reports_published_block_contracts_in_markdown(): void
    {
        $this->artisan('block-types:contracts-audit')
            ->expectsOutputToContain('Published block types: 30')
            ->expectsOutputToContain('| `header` | Header | `content` | `text` (title, eyebrow, subtitle, content, meta) | no | `resources/views/admin/blocks/types/header.blade.php` | `resources/views/pages/partials/blocks/header.blade.php` |')
            ->expectsOutputToContain('| `sticky-navbar` | Navbar | `navigation` | shared/canonical | yes | `resources/views/admin/blocks/types/sticky-navbar.blade.php` | `resources/views/pages/partials/blocks/sticky-navbar.blade.php` |')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_can_emit_json_output(): void
    {
        $exitCode = Artisan::call('block-types:contracts-audit', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"published_count": 30', $output);
        $this->assertStringContainsString('"slug": "content_header"', $output);
        $this->assertStringContainsString('"admin_form_source": "resources/views/admin/blocks/types/content_header.blade.php"', $output);
        $this->assertStringContainsString('"public_renderer_source": "resources/views/pages/partials/blocks/content_header.blade.php"', $output);
    }
}
