@include('admin.blocks.types.partials.rich-text-editor', [
    'inputName' => "{$prefix}[content]",
    'inputId' => "block_{$index}_content",
    'surfaceId' => "block_{$index}_content_surface",
    'value' => old("{$prefix}.content", $block->content),
])
