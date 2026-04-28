<?php

namespace Database\Seeders;

use App\Models\BlockType;
use Illuminate\Database\Seeder;

class BlockTypeSeeder extends Seeder
{
    public function run(): void
    {
        $activeSlugs = ['header', 'plain_text'];

        BlockType::query()
            ->whereNotIn('slug', $activeSlugs)
            ->update(['status' => 'draft']);

        collect([
            [
                'name' => 'Header',
                'slug' => 'header',
                'category' => 'content',
                'description' => 'Primitive translated heading text with a shared heading level.',
                'source_type' => 'static',
                'is_system' => false,
                'is_container' => false,
                'sort_order' => 1,
                'status' => 'published',
            ],
            [
                'name' => 'Plain Text',
                'slug' => 'plain_text',
                'category' => 'content',
                'description' => 'Primitive translated paragraph text rendered as a plain paragraph element.',
                'source_type' => 'static',
                'is_system' => false,
                'is_container' => false,
                'sort_order' => 2,
                'status' => 'published',
            ],
        ])->each(fn (array $item) => BlockType::query()->updateOrCreate(['slug' => $item['slug']], $item));

        collect([
            'heading' => 'Heading',
            'text' => 'Text',
            'rich-text' => 'Rich Text',
            'quote' => 'Quote',
            'callout' => 'Callout',
            'code' => 'Code',
            'list' => 'List',
            'table' => 'Table',
            'accordion' => 'Accordion',
            'tabs' => 'Tabs',
            'faq' => 'FAQ',
            'image' => 'Image',
            'gallery' => 'Gallery',
            'slider' => 'Slider',
            'video' => 'Video',
            'audio' => 'Audio',
            'file' => 'File',
            'map' => 'Map',
            'menu' => 'Menu',
            'breadcrumb' => 'Breadcrumb',
            'pagination' => 'Pagination',
            'toc' => 'TOC',
            'form' => 'Form',
            'button' => 'Button',
            'input' => 'Input',
            'textarea' => 'Textarea',
            'select' => 'Select',
            'checkbox-group' => 'Checkbox Group',
            'radio-group' => 'Radio Group',
            'submit' => 'Submit',
            'search' => 'Search',
            'contact_form' => 'Contact Form',
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
            'feature-grid' => 'Feature Grid',
            'feature-item' => 'Feature Item',
            'comparison' => 'Comparison',
            'team' => 'Team',
            'faq-list' => 'FAQ List',
            'html' => 'HTML',
            'section' => 'Section',
            'hero' => 'Hero',
            'cta' => 'CTA',
            'container' => 'Container',
            'columns' => 'Columns',
            'column_item' => 'Column Item',
            'link-list' => 'Link List',
            'link-list-item' => 'Link List Item',
            'split' => 'Split',
            'stack' => 'Stack',
            'grid' => 'Grid',
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
}
