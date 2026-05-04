@include('admin.blocks.types.partials.rich-text-editor', [
    'inputName' => "{$prefix}[content]",
    'inputId' => "block_{$index}_content",
    'value' => old("{$prefix}.content", $block->content),
])
