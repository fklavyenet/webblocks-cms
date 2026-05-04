@include('admin.blocks.types.partials.rich-text-editor', [
    'translationNotice' => isset($activeLocale) && $block->supportsTranslations() ? 'Rich text content is translated per locale.' : null,
    'inputName' => 'content',
    'inputId' => 'content',
    'value' => old('content', $block->content),
])
