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
            ->expectsOutputToContain('Published block types: 43')
            ->expectsOutputToContain('| `header` | Header | `content` | `text` (title) | no | `resources/views/admin/blocks/types/header.blade.php` | `resources/views/pages/partials/blocks/header.blade.php` |')
            ->expectsOutputToContain('| `hero` | Hero | `content` | `text` (title, subtitle, content) | yes | `resources/views/admin/blocks/types/hero.blade.php` | `resources/views/pages/partials/blocks/hero.blade.php` |')
            ->expectsOutputToContain('| `image` | Image | `content` | `image` (title, subtitle) | no | `resources/views/admin/blocks/types/image.blade.php` | `resources/views/pages/partials/blocks/image.blade.php` |')
            ->expectsOutputToContain('| `sticky-navbar` | Navbar | `navigation` | shared/canonical | yes | `resources/views/admin/blocks/types/sticky-navbar.blade.php` | `resources/views/pages/partials/blocks/sticky-navbar.blade.php` |')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_can_emit_json_output(): void
    {
        $exitCode = Artisan::call('block-types:contracts-audit', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"published_count": 43', $output);
        $this->assertStringContainsString('"slug": "content_header"', $output);
        $this->assertStringContainsString('"slug": "feature-grid"', $output);
        $this->assertStringContainsString('"admin_form_source": "resources/views/admin/blocks/types/content_header.blade.php"', $output);
        $this->assertStringContainsString('"public_renderer_source": "resources/views/pages/partials/blocks/content_header.blade.php"', $output);
        $this->assertStringContainsString('"shared_settings_fields": [', $output);
        $this->assertStringContainsString('"renderer_root_contract": "Owns its public `<header>` root."', $output);
    }

    #[Test]
    public function it_reports_phase_three_contract_updates_for_target_blocks(): void
    {
        $exitCode = Artisan::call('block-types:contracts-audit', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"slug": "code"', $output);
        $this->assertStringContainsString('"translation_family": "text"', $output);
        $this->assertStringContainsString('"current_contract_status": "clear"', $output);
        $this->assertStringContainsString('"slug": "image"', $output);
        $this->assertStringContainsString('"slug": "gallery"', $output);
        $this->assertStringContainsString('"slug": "download"', $output);
        $this->assertStringContainsString('"slug": "file"', $output);
        $this->assertStringContainsString('"slug": "video"', $output);
        $this->assertStringContainsString('"slug": "audio"', $output);
        $this->assertStringContainsString('"slug": "table"', $output);
        $this->assertStringContainsString('"current_contract_status": "clear"', $output);
        $this->assertStringContainsString('"slug": "breadcrumb"', $output);
        $this->assertStringContainsString('"slug": "stat-card"', $output);
        $this->assertStringContainsString('"slug": "link-list"', $output);
        $this->assertStringContainsString('"slug": "hero"', $output);
        $this->assertStringContainsString('"slug": "columns"', $output);
        $this->assertStringContainsString('"slug": "column_item"', $output);
        $this->assertStringContainsString('"slug": "cta"', $output);
        $this->assertStringContainsString('"slug": "feature-grid"', $output);
        $this->assertStringContainsString('"slug": "feature-item"', $output);
        $this->assertStringContainsString('"slug": "sticky-navbar"', $output);
        $this->assertStringContainsString('"slug": "navbar-brand"', $output);
        $this->assertStringContainsString('"slug": "sidebar-brand"', $output);
        $this->assertStringContainsString('"slug": "sidebar-nav-group"', $output);
        $this->assertStringContainsString('"slug": "section"', $output);
        $this->assertStringContainsString('"slug": "container"', $output);
        $this->assertStringContainsString('"slug": "grid"', $output);
        $this->assertStringContainsString('"slug": "cluster"', $output);
        $this->assertStringContainsString('"slug": "card"', $output);
        $this->assertStringContainsString('"allowed_child_type_slugs": [', $output);
        $this->assertStringContainsString('"cluster"', $output);
        $this->assertStringContainsString('"button_link"', $output);
        $this->assertStringContainsString('"renderer_root_contract": "Owns its public `<section>` root."', $output);
        $this->assertStringContainsString('"renderer_root_contract": "Owns its public container `<div>` root."', $output);
        $this->assertStringContainsString('"renderer_root_contract": "Owns its public cluster `<div>` root."', $output);
        $this->assertStringContainsString('"renderer_root_contract": "Owns its public grid `<div>` root."', $output);
        $this->assertStringContainsString('"renderer_root_contract": "Owns its public card root element."', $output);
        $this->assertStringContainsString('"owns_public_root_helper": true', $output);
        $this->assertStringNotContainsString('Renderer clearly owns a root, but `Block::ownsPublicRoot()` does not currently include `sticky-navbar`.', $output);
        $this->assertStringNotContainsString('Public renderer can fall back to the site home URL, but the admin request currently requires a URL on default-locale edits.', $output);
        $this->assertStringNotContainsString('Logo-only accessibility handling is weaker than the current Navbar Brand contract.', $output);
        $this->assertStringNotContainsString('Nested group rendering does not fully reuse sidebar-nav-item helper behavior for child icon and active-state rules.', $output);
    }

    #[Test]
    public function markdown_audit_includes_expanded_layout_and_card_contract_details(): void
    {
        $this->artisan('block-types:contracts-audit')
            ->expectsOutputToContain('## `hero`')
            ->expectsOutputToContain('- Allowed child type slugs: button')
            ->expectsOutputToContain('## `section`')
            ->expectsOutputToContain('- Shared/settings fields: settings.layout_name; settings.spacing')
            ->expectsOutputToContain('## `card`')
            ->expectsOutputToContain('- Allowed child type slugs: cluster; button_link')
            ->expectsOutputToContain('## `content_header`')
            ->expectsOutputToContain('- Renderer root contract: Owns its public `<header>` root.')
            ->assertExitCode(0);
    }
}
