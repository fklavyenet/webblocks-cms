<?php

use App\Models\Block;
use App\Models\Locale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_text_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->foreignId('locale_id')->constrained('locales')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['block_id', 'locale_id']);
        });

        Schema::create('block_button_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->foreignId('locale_id')->constrained('locales')->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();

            $table->unique(['block_id', 'locale_id']);
        });

        Schema::create('block_image_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->foreignId('locale_id')->constrained('locales')->cascadeOnDelete();
            $table->string('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->timestamps();

            $table->unique(['block_id', 'locale_id']);
        });

        Schema::create('block_contact_form_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->foreignId('locale_id')->constrained('locales')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->string('submit_label')->nullable();
            $table->text('success_message')->nullable();
            $table->timestamps();

            $table->unique(['block_id', 'locale_id']);
        });

        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        if (! $defaultLocaleId) {
            return;
        }

        $textTypes = ['heading', 'text', 'rich-text', 'html', 'section', 'columns', 'column_item', 'callout', 'quote', 'faq', 'tabs'];

        Block::query()->orderBy('id')->get()->each(function (Block $block) use ($defaultLocaleId, $textTypes): void {
            $type = $block->typeSlug();

            if (in_array($type, $textTypes, true)) {
                DB::table('block_text_translations')->updateOrInsert(
                    ['block_id' => $block->id, 'locale_id' => $defaultLocaleId],
                    [
                        'title' => $block->getRawOriginal('title'),
                        'subtitle' => $block->getRawOriginal('subtitle'),
                        'content' => $block->getRawOriginal('content'),
                        'created_at' => $block->created_at,
                        'updated_at' => $block->updated_at,
                    ],
                );

                return;
            }

            if ($type === 'button') {
                DB::table('block_button_translations')->updateOrInsert(
                    ['block_id' => $block->id, 'locale_id' => $defaultLocaleId],
                    [
                        'title' => $block->getRawOriginal('title') ?: 'Open link',
                        'created_at' => $block->created_at,
                        'updated_at' => $block->updated_at,
                    ],
                );

                return;
            }

            if ($type === 'image') {
                DB::table('block_image_translations')->updateOrInsert(
                    ['block_id' => $block->id, 'locale_id' => $defaultLocaleId],
                    [
                        'caption' => $block->getRawOriginal('title'),
                        'alt_text' => $block->getRawOriginal('subtitle'),
                        'created_at' => $block->created_at,
                        'updated_at' => $block->updated_at,
                    ],
                );

                return;
            }

            if ($type === 'contact_form') {
                $settings = is_array($block->settings)
                    ? $block->settings
                    : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);

                DB::table('block_contact_form_translations')->updateOrInsert(
                    ['block_id' => $block->id, 'locale_id' => $defaultLocaleId],
                    [
                        'title' => $block->getRawOriginal('title'),
                        'content' => $block->getRawOriginal('content'),
                        'submit_label' => $settings['submit_label'] ?? 'Send message',
                        'success_message' => $settings['success_message'] ?? config('contact.success_message'),
                        'created_at' => $block->created_at,
                        'updated_at' => $block->updated_at,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_contact_form_translations');
        Schema::dropIfExists('block_image_translations');
        Schema::dropIfExists('block_button_translations');
        Schema::dropIfExists('block_text_translations');
    }
};
