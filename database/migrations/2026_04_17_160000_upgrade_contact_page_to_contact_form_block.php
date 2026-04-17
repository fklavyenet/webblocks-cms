<?php

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SlotType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $page = Page::query()->where('slug', 'contact')->first();
        $slotType = SlotType::query()->where('slug', 'main')->first();
        $blockType = BlockType::query()->where('slug', 'contact_form')->first();

        if (! $page || ! $slotType || ! $blockType) {
            return;
        }

        PageSlot::query()->firstOrCreate(
            ['page_id' => $page->id, 'slot_type_id' => $slotType->id],
            ['sort_order' => 1],
        );

        $legacyTypes = ['form', 'input', 'select', 'checkbox-group', 'radio-group', 'textarea', 'submit'];
        $legacyBlockIds = Block::query()
            ->where('page_id', $page->id)
            ->whereIn('type', $legacyTypes)
            ->pluck('id');

        if ($legacyBlockIds->isNotEmpty()) {
            Block::query()->whereIn('parent_id', $legacyBlockIds)->delete();
            Block::query()->whereIn('id', $legacyBlockIds)->delete();
        }

        Block::query()->updateOrCreate(
            [
                'page_id' => $page->id,
                'block_type_id' => $blockType->id,
                'slot_type_id' => $slotType->id,
                'type' => 'contact_form',
            ],
            [
                'parent_id' => null,
                'source_type' => $blockType->source_type ?? 'form',
                'slot' => 'main',
                'sort_order' => 1,
                'title' => 'Contact us',
                'subtitle' => null,
                'content' => 'Tell us what you are planning and we will route your message to the right editorial or implementation contact.',
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => json_encode([
                    'submit_label' => 'Send message',
                    'success_message' => 'Thanks for your message. We will get back to you soon.',
                    'recipient_email' => null,
                    'send_email_notification' => true,
                    'store_submissions' => true,
                ], JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => false,
            ],
        );
    }

    public function down(): void
    {
        $page = Page::query()->where('slug', 'contact')->first();

        if (! $page) {
            return;
        }

        Block::query()
            ->where('page_id', $page->id)
            ->where('type', 'contact_form')
            ->delete();
    }
};
