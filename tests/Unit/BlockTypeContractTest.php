<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContract;

class BlockTypeContractTest extends TestCase
{
    #[Test]
    public function it_preserves_constructor_data_in_audit_array_output(): void
    {
        $contract = new BlockTypeContract(
            slug: 'hero',
            label: 'Hero',
            category: 'marketing',
            status: 'published',
            sourceType: 'static',
            isSystem: true,
            isContainer: true,
            documented: true,
            translationFamily: 'text',
            translationFamilyFields: ['title', 'content'],
            adminFormSource: 'resources/views/admin/blocks/types/hero.blade.php',
            adminFormFields: ['Title', 'Content'],
            translatableFields: ['title', 'content'],
            sharedSettingsFields: ['variant'],
            storageFields: ['Translated content lives in rows.'],
            mediaRelationshipFields: ['Child buttons are related.'],
            childContainerBehavior: ['Container-capable.'],
            publicRendererSource: 'resources/views/pages/partials/blocks/hero.blade.php',
            rendererRootContract: 'Owns its public section root.',
            currentContractStatus: 'clear',
            knownGaps: ['None'],
            supportsChildren: true,
            allowedChildTypeSlugs: ['button'],
            ownsPublicRootHelper: true,
            undocumentedMessage: null,
        );

        $this->assertSame([
            'slug' => 'hero',
            'label' => 'Hero',
            'category' => 'marketing',
            'status' => 'published',
            'source_type' => 'static',
            'is_system' => true,
            'is_container' => true,
            'documented' => true,
            'translation_family' => 'text',
            'translation_family_fields' => ['title', 'content'],
            'admin_form_fields' => ['Title', 'Content'],
            'translatable_fields' => ['title', 'content'],
            'shared_settings_fields' => ['variant'],
            'storage_fields' => ['Translated content lives in rows.'],
            'media_relationship_fields' => ['Child buttons are related.'],
            'child_container_behavior' => ['Container-capable.'],
            'admin_form_source' => 'resources/views/admin/blocks/types/hero.blade.php',
            'public_renderer_source' => 'resources/views/pages/partials/blocks/hero.blade.php',
            'renderer_root_contract' => 'Owns its public section root.',
            'supports_children' => true,
            'allowed_child_type_slugs' => ['button'],
            'owns_public_root_helper' => true,
            'current_contract_status' => 'clear',
            'known_gaps' => ['None'],
            'undocumented_message' => null,
        ], $contract->toAuditArray());
    }
}
