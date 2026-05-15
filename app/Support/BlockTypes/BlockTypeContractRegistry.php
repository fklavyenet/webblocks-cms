<?php

namespace App\Support\BlockTypes;

use App\Models\Block;
use App\Models\BlockType;
use App\Support\Blocks\BlockTranslationRegistry;
use App\Support\Blocks\CoreBlockTypeCatalogSyncer;

class BlockTypeContractRegistry
{
    public function __construct(
        private readonly CoreBlockTypeCatalogSyncer $syncer,
        private readonly BlockTranslationRegistry $translationRegistry,
    ) {}

    /**
     * @return array<int, BlockTypeContract>
     */
    public function publishedCoreContracts(): array
    {
        return collect($this->publishedCoreDefinitions())
            ->sortBy(fn (array $definition): string => sprintf(
                '%03d-%s',
                (int) ($definition['sort_order'] ?? 0),
                (string) ($definition['slug'] ?? ''),
            ))
            ->map(fn (array $definition): BlockTypeContract => $this->resolve($definition['slug']))
            ->values()
            ->all();
    }

    public function resolve(BlockType|string|null $blockType): BlockTypeContract
    {
        $model = $blockType instanceof BlockType ? $blockType : null;
        $slug = trim((string) ($model?->slug ?? $blockType ?? ''));
        $catalog = $this->catalogDefinitionFor($slug) ?? $this->fallbackCatalogDefinition($model, $slug);
        $documented = $slug !== '' && array_key_exists($slug, $this->documentedContracts()) && $this->catalogDefinitionFor($slug) !== null;

        if (! $documented) {
            return $this->undocumentedContract($catalog);
        }

        $contract = $this->documentedContracts()[$slug];
        $translationFamily = $this->translationRegistry->familyFor($slug);
        $block = $this->contractBlock($catalog);

        return new BlockTypeContract(
            slug: (string) ($catalog['slug'] ?? $slug),
            label: (string) ($catalog['name'] ?? $model?->name ?? str($slug)->replace(['-', '_'], ' ')->title()->toString()),
            category: (string) ($catalog['category'] ?? $model?->category ?? ''),
            status: (string) ($catalog['status'] ?? $model?->status ?? ''),
            sourceType: (string) ($catalog['source_type'] ?? $model?->source_type ?? 'static'),
            isSystem: (bool) ($catalog['is_system'] ?? $model?->is_system ?? false),
            isContainer: (bool) ($catalog['is_container'] ?? $model?->is_container ?? false),
            documented: true,
            translationFamily: $translationFamily,
            translationFamilyFields: $translationFamily ? $this->translationRegistry->translatedFieldMap($translationFamily) : [],
            adminFormSource: $this->viewPath('admin/blocks/types/'.$slug.'.blade.php'),
            adminFormFields: $contract['admin_form_fields'],
            translatableFields: $contract['translatable_fields'],
            sharedSettingsFields: $contract['shared_settings_fields'],
            storageFields: $contract['storage_fields'],
            mediaRelationshipFields: $contract['media_relationship_fields'],
            childContainerBehavior: $contract['child_container_behavior'],
            publicRendererSource: $this->viewPath('pages/partials/blocks/'.$slug.'.blade.php'),
            rendererRootContract: $contract['renderer_root_contract'],
            currentContractStatus: $contract['current_contract_status'],
            knownGaps: $contract['known_gaps'],
            supportsChildren: $block->canAcceptChildren(),
            allowedChildTypeSlugs: $block->allowedChildTypeSlugs(),
            ownsPublicRootHelper: $block->ownsPublicRoot(),
        );
    }

    private function undocumentedContract(array $catalog): BlockTypeContract
    {
        return new BlockTypeContract(
            slug: (string) ($catalog['slug'] ?? ''),
            label: (string) ($catalog['name'] ?? 'Unknown Block Type'),
            category: (string) ($catalog['category'] ?? ''),
            status: (string) ($catalog['status'] ?? ''),
            sourceType: (string) ($catalog['source_type'] ?? 'static'),
            isSystem: (bool) ($catalog['is_system'] ?? false),
            isContainer: (bool) ($catalog['is_container'] ?? false),
            documented: false,
            translationFamily: null,
            translationFamilyFields: [],
            adminFormSource: null,
            adminFormFields: [],
            translatableFields: [],
            sharedSettingsFields: [],
            storageFields: [],
            mediaRelationshipFields: [],
            childContainerBehavior: [],
            publicRendererSource: null,
            rendererRootContract: 'None documented.',
            currentContractStatus: 'not documented',
            knownGaps: [],
            supportsChildren: false,
            allowedChildTypeSlugs: null,
            ownsPublicRootHelper: false,
            undocumentedMessage: 'No shipped contract is documented for this block type yet.',
        );
    }

    private function contractBlock(array $catalog): Block
    {
        $blockType = new BlockType([
            'name' => $catalog['name'] ?? null,
            'slug' => $catalog['slug'] ?? null,
            'category' => $catalog['category'] ?? null,
            'source_type' => $catalog['source_type'] ?? null,
            'is_system' => $catalog['is_system'] ?? false,
            'is_container' => $catalog['is_container'] ?? false,
            'sort_order' => $catalog['sort_order'] ?? 0,
            'status' => $catalog['status'] ?? null,
        ]);

        $block = new Block(['type' => $catalog['slug'] ?? null]);
        $block->setRelation('blockType', $blockType);

        return $block;
    }

    private function viewPath(string $relativePath): ?string
    {
        return is_file(resource_path('views/'.$relativePath))
          ? 'resources/views/'.$relativePath
          : null;
    }

    private function catalogDefinitionFor(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        return $this->publishedCoreDefinitions()[$slug] ?? null;
    }

    private function fallbackCatalogDefinition(?BlockType $blockType, string $slug): array
    {
        return [
            'name' => $blockType?->name ?? str($slug !== '' ? $slug : 'block-type')->replace(['-', '_'], ' ')->title()->toString(),
            'slug' => $slug,
            'category' => $blockType?->category ?? '',
            'source_type' => $blockType?->source_type ?? 'static',
            'is_system' => (bool) ($blockType?->is_system ?? false),
            'is_container' => (bool) ($blockType?->is_container ?? false),
            'sort_order' => (int) ($blockType?->sort_order ?? 0),
            'status' => $blockType?->status ?? '',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function publishedCoreDefinitions(): array
    {
        return collect($this->syncer->definitions())
            ->filter(fn (array $definition): bool => ($definition['status'] ?? null) === 'published')
            ->keyBy('slug')
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function documentedContracts(): array
    {
        return [
            'header' => [
                'admin_form_fields' => ['Text', 'Level', 'Anchor ID'],
                'translatable_fields' => ['title'],
                'shared_settings_fields' => ['variant', 'settings.alignment', 'settings.anchor'],
                'storage_fields' => [
                    'Translated heading text lives in block text translation rows.',
                    'Shared heading level stays on the block variant column.',
                    'Shared anchor is resolved from settings.anchor with legacy url fallback compatibility.',
                ],
                'media_relationship_fields' => ['Same-page TOC blocks consume anchored Header blocks by relationship, not by direct foreign key.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public root heading element (`<h1>` through `<h6>`).',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'plain_text' => [
                'admin_form_fields' => ['Text'],
                'translatable_fields' => ['content'],
                'shared_settings_fields' => ['settings.alignment'],
                'storage_fields' => [
                    'Translated paragraph copy lives in block text translation rows.',
                    'Shared alignment stays in block settings.',
                ],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public root paragraph element.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'rich-text' => [
                'admin_form_fields' => ['Content'],
                'translatable_fields' => ['content'],
                'shared_settings_fields' => [],
                'storage_fields' => ['Translated rich text content lives in block text translation rows.'],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its `.wb-rich-text` public root when translated content is present.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'code' => [
                'admin_form_fields' => ['Title', 'Filename / Language Label', 'Code', 'Syntax Language'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['settings.language'],
                'storage_fields' => [
                    'Translated code title, label, and snippet body live in block text translation rows.',
                    'Shared syntax language stays in block settings.',
                ],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its public `<pre><code>` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'button_link' => [
                'admin_form_fields' => ['Label', 'URL', 'Target'],
                'translatable_fields' => ['title'],
                'shared_settings_fields' => ['settings.url', 'settings.target', 'variant'],
                'storage_fields' => [
                    'Translated button label lives in block text translation rows.',
                    'Shared URL, target, and variant stay on shared block fields or settings.',
                ],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public button-link anchor root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'card' => [
                'admin_form_fields' => ['Media', 'Image placement', 'Image alignment', 'Image aspect', 'Eyebrow / Label', 'Title', 'Subtitle', 'Description', 'Action label', 'Image alt text', 'Image caption'],
                'translatable_fields' => ['eyebrow', 'title', 'subtitle', 'content', 'meta', 'image_alt', 'image_caption'],
                'shared_settings_fields' => ['media_id', 'settings.url', 'settings.target', 'settings.variant', 'settings.image_position', 'settings.image_align', 'settings.image_aspect'],
                'storage_fields' => [
                    'Translated card copy lives in block text translation rows.',
                    'Translated card image alt text and caption live in block image translation rows.',
                    'Shared selected image stays on the canonical block media_id column.',
                    'Shared URL, target, variant, image placement, image alignment, and image aspect stay in shared settings.',
                    'Legacy single-action fallback still uses translated meta content.',
                ],
                'media_relationship_fields' => ['Primary card image is owned through the direct block media_id relation. Nested child footer actions remain block relationships rather than media rows.'],
                'child_container_behavior' => ['Container-capable. Allowed child types are `cluster` and `button_link`.'],
                'renderer_root_contract' => 'Owns its public card root element.',
                'current_contract_status' => 'transitional',
                'known_gaps' => ['Current contract intentionally keeps both nested child actions and the older single-action fallback path while preserving no-image rendering for existing cards. Left or right split-media card layouts are still intentionally out of scope for this stacked-card contract.'],
            ],
            'stat-card' => [
                'admin_form_fields' => ['Label', 'Value', 'Description', 'Optional URL'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['canonical url'],
                'storage_fields' => [
                    'Translated label, value, and description live in block text translation rows.',
                    'Optional URL stays on the canonical block url column.',
                ],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public stat-card root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'image' => [
                'admin_form_fields' => ['Media', 'Alt text', 'Link URL', 'Caption'],
                'translatable_fields' => ['title', 'subtitle'],
                'shared_settings_fields' => ['media_id', 'url'],
                'storage_fields' => [
                    'Translated caption and alt text live in block image translation rows.',
                    'Shared media ownership stays on the canonical block media_id column.',
                    'Shared link URL stays on the canonical block url column.',
                ],
                'media_relationship_fields' => ['Primary image media is owned through the direct block media_id relation.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its semantic `<figure>` root when an image source exists, with optional linked image output.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'gallery' => [
                'admin_form_fields' => ['Gallery title', 'Description', 'Gallery media'],
                'translatable_fields' => ['title', 'subtitle'],
                'shared_settings_fields' => ['ordered block_media gallery_item relations'],
                'storage_fields' => [
                    'Translated gallery title and description live in block text translation rows.',
                    'Ordered gallery media ownership stays in block_media rows with the gallery_item role.',
                    'Legacy settings-based gallery item fallbacks remain readable for older saved content.',
                ],
                'media_relationship_fields' => ['Gallery media is ordered through block_media.gallery_item rows; the public renderer also preserves older settings fallback items when needed.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its gallery wrapper root and registers one matching viewer modal under the shared `#wb-overlay-root` contract.',
                'current_contract_status' => 'transitional',
                'known_gaps' => ['Public rendering still preserves legacy settings-based gallery items when no canonical block_media rows exist.'],
            ],
            'download' => [
                'admin_form_fields' => ['Download label', 'Helper text', 'Document media', 'Variant'],
                'translatable_fields' => ['title', 'subtitle'],
                'shared_settings_fields' => ['media_id', 'variant'],
                'storage_fields' => [
                    'Translated download label and helper text live in block text translation rows.',
                    'Shared media ownership stays on the canonical block media_id column.',
                    'Shared button styling stays on the block variant column.',
                ],
                'media_relationship_fields' => ['Download media is owned through the direct block media_id relation.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its CTA wrapper root and renders nothing when no usable media source exists.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'file' => [
                'admin_form_fields' => ['File title', 'External file URL', 'Supporting copy', 'Media file'],
                'translatable_fields' => ['title', 'content'],
                'shared_settings_fields' => ['media_id', 'url'],
                'storage_fields' => [
                    'Translated title and supporting copy live in block text translation rows.',
                    'Shared media ownership stays on the canonical block media_id column.',
                    'Shared external URL stays on the canonical block url column.',
                ],
                'media_relationship_fields' => ['File media is owned through the direct block media_id relation, with external URL fallback support.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its file card root and avoids empty action links when neither media nor URL is usable.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'video' => [
                'admin_form_fields' => ['Video title', 'External video URL', 'Supporting copy', 'Hosted video'],
                'translatable_fields' => ['title', 'content'],
                'shared_settings_fields' => ['media_id', 'url'],
                'storage_fields' => [
                    'Translated title and supporting copy live in block text translation rows.',
                    'Shared uploaded media ownership stays on the canonical block media_id column.',
                    'Shared external video URL stays on the canonical block url column.',
                ],
                'media_relationship_fields' => ['Video media is owned through the direct block media_id relation, with external URL fallback support for safe provider handling.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its video card root and renders native `<video controls>` for hosted media, safe embeds for known providers, or an external link fallback.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'audio' => [
                'admin_form_fields' => ['Audio title', 'External audio URL', 'Supporting copy', 'Hosted audio'],
                'translatable_fields' => ['title', 'content'],
                'shared_settings_fields' => ['media_id', 'url'],
                'storage_fields' => [
                    'Translated title and supporting copy live in block text translation rows.',
                    'Shared uploaded media ownership stays on the canonical block media_id column.',
                    'Shared external audio URL stays on the canonical block url column.',
                ],
                'media_relationship_fields' => ['Audio media is owned through the direct block media_id relation, with external URL fallback support.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its audio card root and renders native `<audio controls>` only when a usable source exists.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'table' => [
                'admin_form_fields' => ['Table Title', 'Table Style', 'Table Rows'],
                'translatable_fields' => ['title', 'content'],
                'shared_settings_fields' => ['variant'],
                'storage_fields' => [
                    'Translated table title and row payload live in block text translation rows.',
                    'Renderer still checks a legacy `settings.rows` fallback path that the core admin form does not currently write.',
                ],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its public table wrapper root.',
                'current_contract_status' => 'mostly clear',
                'known_gaps' => [
                    'Renderer still supports a legacy settings fallback path for rows.',
                ],
            ],
            'quote' => [
                'admin_form_fields' => ['Style', 'Quote', 'Author', 'Source'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['variant', 'canonical title', 'canonical subtitle', 'canonical content'],
                'storage_fields' => ['Current quote content is stored canonically on the block row.'],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its public quote or testimonial root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'section' => [
                'admin_form_fields' => ['Admin label', 'Spacing'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['settings.layout_name', 'settings.spacing'],
                'storage_fields' => ['Shared layout-wrapper settings stay in block settings.'],
                'media_relationship_fields' => ['Child blocks are the primary relationship.'],
                'child_container_behavior' => ['Container-capable. Child types are not explicitly restricted in the current helper.'],
                'renderer_root_contract' => 'Owns its public `<section>` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'container' => [
                'admin_form_fields' => ['Admin label', 'Width', 'Flow'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['settings.layout_name', 'settings.width', 'settings.flow'],
                'storage_fields' => ['Shared width and flow settings stay in block settings.'],
                'media_relationship_fields' => ['Child blocks are the primary relationship.'],
                'child_container_behavior' => ['Container-capable. Child types are not explicitly restricted in the current helper.'],
                'renderer_root_contract' => 'Owns its public container `<div>` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => ['Legacy default flow still falls back to stacked rendering when unset.'],
            ],
            'cluster' => [
                'admin_form_fields' => ['Admin label', 'Gap', 'Justify', 'Align', 'Wrap', 'Width'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['settings.layout_name', 'settings.gap', 'settings.alignment', 'settings.items_alignment', 'settings.wrap', 'settings.width'],
                'storage_fields' => ['Shared cluster layout settings stay in block settings.'],
                'media_relationship_fields' => ['Child blocks are the primary relationship.'],
                'child_container_behavior' => ['Container-capable. Child types are not explicitly restricted in the current helper.'],
                'renderer_root_contract' => 'Owns its public cluster `<div>` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'grid' => [
                'admin_form_fields' => ['Admin label', 'Columns', 'Gap'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['settings.layout_name', 'settings.columns', 'settings.gap'],
                'storage_fields' => ['Shared grid layout settings stay in block settings.'],
                'media_relationship_fields' => ['Child blocks are the primary relationship.'],
                'child_container_behavior' => ['Container-capable. Child types are not explicitly restricted in the current helper.'],
                'renderer_root_contract' => 'Owns its public grid `<div>` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'content_header' => [
                'admin_form_fields' => ['Title', 'Title level', 'Intro text', 'Meta items'],
                'translatable_fields' => ['title', 'subtitle', 'meta'],
                'shared_settings_fields' => ['variant', 'settings.alignment'],
                'storage_fields' => [
                    'Translated title, intro, and meta copy live in block text translation rows.',
                    'Shared title level and alignment stay on shared fields or settings.',
                ],
                'media_relationship_fields' => ['Structured meta items are stored as content data rather than as media relationships.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public `<header>` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'hero' => [
                'admin_form_fields' => ['Eyebrow / Label', 'Title', 'Subtitle / Intro', 'Primary CTA', 'Secondary CTA', 'Variant', 'Layout', 'Title tag'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['variant', 'settings.layout', 'settings.title_tag'],
                'storage_fields' => [
                    'Translated eyebrow, title, and intro copy live in block text translation rows using subtitle as the visible eyebrow field.',
                    'Shared variant plus layout and title-tag settings stay on shared block fields or settings.',
                    'Managed CTA labels are translated on child button rows while CTA URLs remain shared on those child blocks.',
                ],
                'media_relationship_fields' => ['Managed CTA buttons are stored as child block relationships.'],
                'child_container_behavior' => ['Container-capable. Allowed child type is `button` for managed CTA actions.'],
                'renderer_root_contract' => 'Owns its public promo `<section>` root.',
                'current_contract_status' => 'transitional',
                'known_gaps' => ['Public renderer still preserves legacy settings fallbacks for eyebrow, title, and body copy when canonical translated fields are empty.'],
            ],
            'columns' => [
                'admin_form_fields' => ['Columns Title', 'Columns Subtitle', 'Columns Variant', 'Intro Text', 'Column Items'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['variant'],
                'storage_fields' => [
                    'Translated intro title, subtitle, and copy live in block text translation rows.',
                    'Shared presentation variant stays on the block variant column.',
                    'Child column items are stored as nested block relationships.',
                ],
                'media_relationship_fields' => ['Child `column_item` blocks are the primary relationship.'],
                'child_container_behavior' => ['Container-capable. Allowed child type is `column_item`.'],
                'renderer_root_contract' => 'Owns its public structured-content `<section>` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'column_item' => [
                'admin_form_fields' => ['Column Title', 'Optional Link', 'Column Text'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['url'],
                'storage_fields' => [
                    'Translated item title, optional value/subtitle, and content live in block text translation rows.',
                    'Shared optional link URL stays on the canonical block url field.',
                ],
                'media_relationship_fields' => ['Parent `columns` block is the primary relationship context.'],
                'child_container_behavior' => ['Not a container in the current contract. Intended to render through parent-driven columns variants.'],
                'renderer_root_contract' => 'Renderer owns the item root it emits for the active parent-driven variant when rendered directly.',
                'current_contract_status' => 'clear',
                'known_gaps' => ['Current stats-style presentation still reuses subtitle as the visible value because there is no dedicated numeric value field.'],
            ],
            'feature-grid' => [
                'admin_form_fields' => ['Feature Grid Title', 'Feature Grid Subtitle', 'Intro Text', 'Feature Items'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => [],
                'storage_fields' => [
                    'Translated intro title, subtitle, and copy live in block text translation rows.',
                    'Child feature items are stored as nested block relationships.',
                ],
                'media_relationship_fields' => ['Child `feature-item` rows are primary, while legacy `column_item` children remain renderable for compatibility.'],
                'child_container_behavior' => ['Container-capable. Allowed child types are `feature-item` and legacy-compatible `column_item`.'],
                'renderer_root_contract' => 'Delegates public rendering through the Columns cards pattern and currently relies on the generic public wrapper rather than a dedicated feature-grid root.',
                'current_contract_status' => 'transitional',
                'known_gaps' => ['Feature Grid remains a first-class compatibility alias over the Columns cards presentation path.'],
            ],
            'feature-item' => [
                'admin_form_fields' => ['Feature Title', 'Optional Link', 'Feature Text'],
                'translatable_fields' => ['title', 'content'],
                'shared_settings_fields' => ['url'],
                'storage_fields' => [
                    'Translated feature title and content live in block text translation rows.',
                    'Shared optional link URL stays on the canonical block url field.',
                ],
                'media_relationship_fields' => ['Parent `feature-grid` block is the primary relationship context.'],
                'child_container_behavior' => ['Not a container in the current contract. Public rendering delegates to the shared column-item cards presentation.'],
                'renderer_root_contract' => 'Delegates public rendering to the shared column-item cards variant and does not own a dedicated standalone root contract.',
                'current_contract_status' => 'transitional',
                'known_gaps' => ['Feature Item currently shares the same public cards shell as Column Item instead of owning distinct feature-specific markup.'],
            ],
            'alert' => [
                'admin_form_fields' => ['Title', 'Content', 'Variant'],
                'translatable_fields' => ['title', 'content'],
                'shared_settings_fields' => ['settings.variant'],
                'storage_fields' => [
                    'Translated alert copy lives in block text translation rows.',
                    'Shared variant stays in block settings.',
                ],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public alert root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'cta' => [
                'admin_form_fields' => ['Eyebrow / Label', 'Heading', 'Body Copy', 'Primary CTA', 'Secondary CTA', 'Variant'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['variant'],
                'storage_fields' => [
                    'Translated eyebrow, heading, and body copy live in block text translation rows using subtitle as the visible eyebrow field.',
                    'Shared promo variant stays on the block variant column.',
                    'Managed CTA labels are translated on child button rows while CTA URLs remain shared on those child blocks.',
                ],
                'media_relationship_fields' => ['Managed CTA buttons are stored as child block relationships.'],
                'child_container_behavior' => ['Container-capable. Allowed child type is `button` for managed CTA actions.'],
                'renderer_root_contract' => 'Owns its public promo `<section>` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'link-list' => [
                'admin_form_fields' => ['Intro title', 'Intro subtitle', 'Intro content', 'Link list items'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => [],
                'storage_fields' => [
                    'Translated intro copy lives in block text translation rows.',
                    'Child link-list-item rows are stored as nested block relationships.',
                ],
                'media_relationship_fields' => ['Child `link-list-item` blocks are the primary relationship.'],
                'child_container_behavior' => ['Container-capable. Allowed child type is `link-list-item`.'],
                'renderer_root_contract' => 'Owns its public `.wb-link-list` root when published child rows exist.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'link-list-item' => [
                'admin_form_fields' => ['Title', 'URL', 'Meta', 'Description'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['url'],
                'storage_fields' => [
                    'Translated row copy lives in block text translation rows.',
                    'Shared URL stays on the canonical block url field.',
                ],
                'media_relationship_fields' => ['Parent `link-list` block is the primary relationship context.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public row-link root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'toc' => [
                'admin_form_fields' => ['Optional title'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['canonical title'],
                'storage_fields' => ['TOC title is stored canonically on the block row.'],
                'media_relationship_fields' => ['Same-page published Header blocks with valid anchors are discovered at render time.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns its generated public TOC wrapper when headings exist.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'breadcrumb' => [
                'admin_form_fields' => ['Home label', 'Current page item'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['settings.home_label', 'settings.include_current'],
                'storage_fields' => ['Shared breadcrumb display settings are intended to live in block settings.'],
                'media_relationship_fields' => ['Current page, site, locale, and home-page translation context are resolved at render time.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public `<nav class="wb-breadcrumb">` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'header-actions' => [
                'admin_form_fields' => ['Show mode toggle', 'Show accent toggle', 'Show search'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['settings.show_mode_toggle', 'settings.show_accent_toggle', 'settings.show_search'],
                'storage_fields' => ['Shared header action toggles stay in block settings.'],
                'media_relationship_fields' => ['Search route and public WebBlocks UI behavior are consumed at render time.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its inner public action cluster only; it does not own the outer header shell.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'sticky-navbar' => [
                'admin_form_fields' => ['Admin label', 'Position'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['settings.layout_name', 'settings.sticky_mode'],
                'storage_fields' => ['Shared navbar wrapper settings stay in block settings.'],
                'media_relationship_fields' => ['Nested navbar blocks are stored as child block relationships.'],
                'child_container_behavior' => ['Container-capable. Allowed child types are `container`, `cluster`, `header`, `plain_text`, `rich-text`, `button_link`, `navbar-brand`, `navbar-navigation`, `header-actions`, and `search-form`.'],
                'renderer_root_contract' => 'Public renderer owns the outer `<nav class="wb-navbar">` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'navbar-brand' => [
                'admin_form_fields' => ['Logo', 'Brand Title', 'URL', 'Subtitle', 'Accessible Label', 'Target'],
                'translatable_fields' => ['title', 'subtitle'],
                'shared_settings_fields' => ['settings.url', 'settings.target', 'settings.aria_label'],
                'storage_fields' => [
                    'Translated title and subtitle live in block text translation rows.',
                    'Shared URL, target, accessible label, and logo media stay on shared block fields or settings.',
                ],
                'media_relationship_fields' => ['Logo media is owned through the block media_id relation.'],
                'child_container_behavior' => ['Not a container in the current contract. Intended to live somewhere inside a Navbar tree.'],
                'renderer_root_contract' => 'Owns the inner brand-link root only; it does not own the outer navbar shell.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'navbar-navigation' => [
                'admin_form_fields' => ['Menu', 'ARIA label'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['title', 'settings.menu_key'],
                'storage_fields' => ['Shared menu binding and shared ARIA label are stored on canonical block fields or settings.'],
                'media_relationship_fields' => ['Navigation structure is resolved from shared NavigationItem rows.'],
                'child_container_behavior' => ['Not a container in the current contract. Intended to live somewhere inside a Navbar tree.'],
                'renderer_root_contract' => 'Owns the inner navbar-navigation wrapper only.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'sidebar-brand' => [
                'admin_form_fields' => ['Logo', 'Title', 'URL', 'Subtitle', 'Accessible Label', 'Target'],
                'translatable_fields' => ['title', 'subtitle'],
                'shared_settings_fields' => ['settings.url', 'settings.target', 'settings.aria_label'],
                'storage_fields' => [
                    'Translated title and subtitle live in block text translation rows.',
                    'Shared URL, target, accessible label, and logo media stay on shared block fields or settings.',
                ],
                'media_relationship_fields' => ['Logo media is owned through the block media_id relation.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns the inner sidebar-brand link root only.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'sidebar-navigation' => [
                'admin_form_fields' => ['Menu', 'ARIA label', 'Admin label', 'Show icons', 'Active matching'],
                'translatable_fields' => ['title'],
                'shared_settings_fields' => ['settings.menu_key', 'settings.layout_name', 'settings.show_icons', 'settings.active_matching'],
                'storage_fields' => [
                    'Translated visible label lives in block text translation rows.',
                    'Shared menu-mode and manual-navigation settings stay in block settings.',
                ],
                'media_relationship_fields' => ['Can resolve CMS NavigationItem trees or manual child blocks depending on settings and children.'],
                'child_container_behavior' => ['Container-capable. Allowed child types are `sidebar-nav-item` and `sidebar-nav-group`.'],
                'renderer_root_contract' => 'Owns its public `<nav class="wb-sidebar-nav">` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'sidebar-nav-item' => [
                'admin_form_fields' => ['Title', 'URL', 'Icon', 'Active mode', 'Target', 'Manual active'],
                'translatable_fields' => ['title'],
                'shared_settings_fields' => ['settings.url', 'settings.target', 'settings.icon', 'settings.active_mode', 'settings.manual_active'],
                'storage_fields' => [
                    'Translated visible label lives in block text translation rows.',
                    'Shared link, icon, and active-state settings stay in block settings.',
                ],
                'media_relationship_fields' => ['Icon uses a shared icon-catalog slug rather than media ownership.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public sidebar-link root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'sidebar-nav-group' => [
                'admin_form_fields' => ['Title', 'Icon', 'Admin label', 'Initially open'],
                'translatable_fields' => ['title'],
                'shared_settings_fields' => ['settings.icon', 'settings.initially_open', 'settings.layout_name'],
                'storage_fields' => [
                    'Translated group label lives in block text translation rows.',
                    'Shared icon, open-state, and admin-label settings stay in block settings.',
                ],
                'media_relationship_fields' => ['Child sidebar-nav-item blocks are the primary relationship.'],
                'child_container_behavior' => ['Container-capable. Allowed child type is `sidebar-nav-item`.'],
                'renderer_root_contract' => 'Owns its public `.wb-nav-group` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'search-form' => [
                'admin_form_fields' => ['Accessible label', 'Placeholder', 'Button label', 'Button variant', 'Show button'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['variant', 'settings.show_button'],
                'storage_fields' => [
                    'Translated label, button label, and placeholder text live in block text translation rows.',
                    'Shared button visibility and variant stay on shared block fields or settings.',
                ],
                'media_relationship_fields' => ['Search route, site, and locale context are resolved at render time.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its public `<form role="search">` root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'sidebar-footer' => [
                'admin_form_fields' => ['Title', 'Variant', 'Content', 'Footer text'],
                'translatable_fields' => ['title', 'subtitle', 'content'],
                'shared_settings_fields' => ['settings.variant'],
                'storage_fields' => [
                    'Translated footer copy lives in block text translation rows.',
                    'Shared variant stays in block settings.',
                ],
                'media_relationship_fields' => ['Not applicable.'],
                'child_container_behavior' => ['Not a container in the current contract.'],
                'renderer_root_contract' => 'Owns its inner sidebar-footer block root.',
                'current_contract_status' => 'clear',
                'known_gaps' => [],
            ],
            'html' => [
                'admin_form_fields' => ['Trusted HTML content'],
                'translatable_fields' => [],
                'shared_settings_fields' => ['canonical content'],
                'storage_fields' => ['Trusted HTML content is currently stored canonically on the block row.'],
                'media_relationship_fields' => ['Trusted HTML can push shared overlay and body-end fragments through public registries at render time.'],
                'child_container_behavior' => ['Not a container. Historical child rows may still exist, but new child placement is not supported and the public renderer ignores child trees.'],
                'renderer_root_contract' => 'Owns a wrapper `<div>` around trusted markup and can also emit out-of-band overlay or body-end content.',
                'current_contract_status' => 'mostly clear',
                'known_gaps' => ['Trusted markup can also affect shared overlay or body-end output beyond the visible root.'],
            ],
        ];
    }
}
