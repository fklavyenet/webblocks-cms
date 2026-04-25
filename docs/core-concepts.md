
# Core Concepts

WebBlocks CMS is built on a strict structural model:

Page → Layout → Slots → Blocks

## Page

A page is the top-level content entity. It defines routing, site ownership, and layout selection.

Pages do not directly store content blocks.

## Layout

A layout defines structural composition. It determines which slots exist and how they are arranged.

Layouts are reusable and independent of content.

## Slot

Slots are named placement areas inside a layout.

Examples:
- header
- main
- sidebar
- footer

Slots define where blocks can be placed.

## Block

Blocks are the actual content units.

Examples:
- text
- image
- button
- navigation

Blocks are attached to slots, not directly to pages.

## Philosophy

- No hidden structure
- No template magic
- No JSON content blobs
- Everything is relational and explicit

This ensures predictability and maintainability.
