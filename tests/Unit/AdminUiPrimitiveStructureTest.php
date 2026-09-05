<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdminUiPrimitiveStructureTest extends TestCase
{
    #[Test]
    public function table_content_is_contained_by_card_bodies(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'resources/views/admin/embedded-applications/index.blade.php',
            'resources/views/admin/system/information.blade.php',
        ] as $relativePath) {
            $view = file_get_contents($root.'/'.$relativePath);

            $this->assertMatchesRegularExpression(
                '/wb-card-body[\s\S]*wb-table-wrap[\s\S]*wb-table/',
                $view,
                $relativePath,
            );
        }
    }

    #[Test]
    public function metadata_tables_use_the_key_value_column_proportions(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'resources/views/admin/contact-messages/show.blade.php',
            'resources/views/admin/dashboard.blade.php',
            'resources/views/admin/pages/partials/details-modal.blade.php',
            'resources/views/admin/system/information.blade.php',
            'resources/views/admin/system/plugins/show.blade.php',
            'resources/views/admin/system/settings.blade.php',
        ] as $relativePath) {
            $view = file_get_contents($root.'/'.$relativePath);

            $this->assertStringContainsString('wb-table-key', $view, $relativePath);
        }
    }

    #[Test]
    public function card_accordions_expose_a_chevron_in_their_summary(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'resources/views/admin/locales/form.blade.php',
            'resources/views/admin/page-layout-slots/_form.blade.php',
            'resources/views/admin/plugins/catalog/show.blade.php',
            'resources/views/admin/system/plugins/show.blade.php',
            'resources/views/pages/partials/blocks/fallback.blade.php',
        ] as $relativePath) {
            $view = file_get_contents($root.'/'.$relativePath);

            preg_match_all('/<details\b[^>]*wb-card[^>]*>([\s\S]*?)<\/details>/', $view, $accordions);
            $this->assertNotEmpty($accordions[1], $relativePath);

            foreach ($accordions[1] as $accordion) {
                $this->assertMatchesRegularExpression(
                    '/<summary\b[^>]*wb-card-header[^>]*>[\s\S]*?wb-icon-chevron-down[\s\S]*?<\/summary>/',
                    $accordion,
                    $relativePath,
                );
            }
        }
    }
}
