
# Blocks

Blocks represent content units.

## Structure

Each block type has its own database table.

Examples:
- block_text_translations
- block_image_translations

## Shared vs Translated Fields

- Shared: structure, placement, asset references
- Translated: content fields such as text, labels

## Philosophy

- No JSON storage
- Each block type is explicit
- Schema reflects real content structure
