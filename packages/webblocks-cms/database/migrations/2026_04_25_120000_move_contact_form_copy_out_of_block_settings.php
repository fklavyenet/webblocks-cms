<?php

use App\Models\Locale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        if (! $defaultLocaleId) {
            return;
        }

        DB::table('blocks')
            ->where('type', 'contact_form')
            ->orderBy('id')
            ->get()
            ->each(function (object $block) use ($defaultLocaleId): void {
                $settings = json_decode((string) ($block->settings ?? ''), true);
                $settings = is_array($settings) ? $settings : [];

                $submitLabel = trim((string) ($settings['submit_label'] ?? ''));
                $successMessage = trim((string) ($settings['success_message'] ?? ''));

                if ($submitLabel === '' && $successMessage === '') {
                    return;
                }

                $translation = DB::table('block_contact_form_translations')
                    ->where('block_id', $block->id)
                    ->where('locale_id', $defaultLocaleId)
                    ->first();

                $payload = [
                    'title' => $translation?->title ?? $block->title,
                    'content' => $translation?->content ?? $block->content,
                    'submit_label' => trim((string) ($translation?->submit_label ?? '')) !== ''
                        ? $translation->submit_label
                        : ($submitLabel !== '' ? $submitLabel : null),
                    'success_message' => trim((string) ($translation?->success_message ?? '')) !== ''
                        ? $translation->success_message
                        : ($successMessage !== '' ? $successMessage : null),
                    'created_at' => $translation?->created_at ?? $block->created_at,
                    'updated_at' => $block->updated_at,
                ];

                DB::table('block_contact_form_translations')->updateOrInsert(
                    ['block_id' => $block->id, 'locale_id' => $defaultLocaleId],
                    $payload,
                );

                unset($settings['submit_label'], $settings['success_message']);

                DB::table('blocks')
                    ->where('id', $block->id)
                    ->update([
                        'settings' => $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES),
                        'updated_at' => $block->updated_at,
                    ]);
            });
    }

    public function down(): void
    {
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        if (! $defaultLocaleId) {
            return;
        }

        DB::table('blocks')
            ->where('type', 'contact_form')
            ->orderBy('id')
            ->get()
            ->each(function (object $block) use ($defaultLocaleId): void {
                $translation = DB::table('block_contact_form_translations')
                    ->where('block_id', $block->id)
                    ->where('locale_id', $defaultLocaleId)
                    ->first();

                if (! $translation) {
                    return;
                }

                $settings = json_decode((string) ($block->settings ?? ''), true);
                $settings = is_array($settings) ? $settings : [];

                if (trim((string) ($translation->submit_label ?? '')) !== '') {
                    $settings['submit_label'] = $translation->submit_label;
                }

                if (trim((string) ($translation->success_message ?? '')) !== '') {
                    $settings['success_message'] = $translation->success_message;
                }

                DB::table('blocks')
                    ->where('id', $block->id)
                    ->update([
                        'settings' => $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES),
                        'updated_at' => $block->updated_at,
                    ]);
            });
    }
};
