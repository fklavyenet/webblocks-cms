<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\BlockType;
use App\Support\Blocks\CoreBlockTypeCatalogSyncer;
use Illuminate\Database\Seeder;
use RuntimeException;

class BlockTypeSeeder extends Seeder
{
    public function __construct(
        private readonly CoreBlockTypeCatalogSyncer $syncer,
    ) {}

    public function run(): void
    {
        $activeSlugs = $this->syncer->slugs();

        BlockType::query()
            ->whereNotIn('slug', $activeSlugs)
            ->update(['status' => 'draft']);

        $this->syncer->sync();

        $this->deleteLegacyHeadingBlockType();

        collect([
            'text' => 'Text',
            'callout' => 'Callout',
            'list' => 'List',
            'accordion' => 'Accordion',
            'tabs' => 'Tabs',
            'faq' => 'FAQ',
            'slider' => 'Slider',
            'map' => 'Map',
            'menu' => 'Menu',
            'pagination' => 'Pagination',
            'form' => 'Form',
            'button' => 'Button',
            'input' => 'Input',
            'textarea' => 'Textarea',
            'select' => 'Select',
            'checkbox-group' => 'Checkbox Group',
            'radio-group' => 'Radio Group',
            'submit' => 'Submit',
            'search' => 'Search',
            'product-card' => 'Product Card',
            'product-grid' => 'Product Grid',
            'pricing' => 'Pricing',
            'cart-summary' => 'Cart Summary',
            'checkout-summary' => 'Checkout Summary',
            'social-links' => 'Social Links',
            'share-buttons' => 'Share Buttons',
            'testimonial' => 'Testimonial',
            'comments' => 'Comments',
            'stats' => 'Stats',
            'metric-card' => 'Metric Card',
            'logo-cloud' => 'Logo Cloud',
            'timeline' => 'Timeline',
            'comparison' => 'Comparison',
            'team' => 'Team',
            'faq-list' => 'FAQ List',
            'split' => 'Split',
            'stack' => 'Stack',
            'card-group' => 'Card Group',
            'page-title' => 'Page Title',
            'page-content' => 'Page Content',
            'page-meta' => 'Page Meta',
            'navigation-auto' => 'Navigation Auto',
            'auth-form' => 'Auth Form',
            'cookie-notice' => 'Cookie Notice',
        ])->each(function (string $name, string $slug): void {
            BlockType::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'category' => 'legacy',
                    'source_type' => 'static',
                    'is_system' => false,
                    'is_container' => false,
                    'sort_order' => 100,
                    'status' => 'draft',
                ],
            );
        });
    }

    private function deleteLegacyHeadingBlockType(): void
    {
        $headingBlockType = BlockType::query()->where('slug', 'heading')->first();

        if (! $headingBlockType) {
            return;
        }

        $liveHeadingCount = Block::query()
            ->where(function ($query) use ($headingBlockType): void {
                $query->where('type', 'heading')
                    ->orWhere('block_type_id', $headingBlockType->id);
            })
            ->where('status', 'published')
            ->count();

        if ($liveHeadingCount > 0) {
            throw new RuntimeException('Cannot remove legacy block type [heading] because '.$liveHeadingCount.' live block(s) still reference it. Move those blocks to the canonical [header] type before running BlockTypeSeeder again.');
        }

        $headingBlockType->delete();
    }
}
