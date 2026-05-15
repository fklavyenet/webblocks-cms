<?php

namespace App\Support\BlockTypes;

class BlockTypeContract
{
    /**
     * @param  array<int, string>  $translationFamilyFields
     * @param  array<int, string>  $adminFormFields
     * @param  array<int, string>  $translatableFields
     * @param  array<int, string>  $sharedSettingsFields
     * @param  array<int, string>  $storageFields
     * @param  array<int, string>  $mediaRelationshipFields
     * @param  array<int, string>  $childContainerBehavior
     * @param  array<int, string>  $knownGaps
     * @param  array<int, string>|null  $allowedChildTypeSlugs
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $category,
        public readonly string $status,
        public readonly string $sourceType,
        public readonly bool $isSystem,
        public readonly bool $isContainer,
        public readonly bool $documented,
        public readonly ?string $translationFamily,
        public readonly array $translationFamilyFields,
        public readonly ?string $adminFormSource,
        public readonly array $adminFormFields,
        public readonly array $translatableFields,
        public readonly array $sharedSettingsFields,
        public readonly array $storageFields,
        public readonly array $mediaRelationshipFields,
        public readonly array $childContainerBehavior,
        public readonly ?string $publicRendererSource,
        public readonly string $rendererRootContract,
        public readonly string $currentContractStatus,
        public readonly array $knownGaps,
        public readonly bool $supportsChildren,
        public readonly ?array $allowedChildTypeSlugs,
        public readonly bool $ownsPublicRootHelper,
        public readonly ?string $undocumentedMessage = null,
    ) {}

    public function toAuditArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'category' => $this->category,
            'status' => $this->status,
            'source_type' => $this->sourceType,
            'is_system' => $this->isSystem,
            'is_container' => $this->isContainer,
            'documented' => $this->documented,
            'translation_family' => $this->translationFamily,
            'translation_family_fields' => $this->translationFamilyFields,
            'admin_form_fields' => $this->adminFormFields,
            'translatable_fields' => $this->translatableFields,
            'shared_settings_fields' => $this->sharedSettingsFields,
            'storage_fields' => $this->storageFields,
            'media_relationship_fields' => $this->mediaRelationshipFields,
            'child_container_behavior' => $this->childContainerBehavior,
            'admin_form_source' => $this->adminFormSource,
            'public_renderer_source' => $this->publicRendererSource,
            'renderer_root_contract' => $this->rendererRootContract,
            'supports_children' => $this->supportsChildren,
            'allowed_child_type_slugs' => $this->allowedChildTypeSlugs,
            'owns_public_root_helper' => $this->ownsPublicRootHelper,
            'current_contract_status' => $this->currentContractStatus,
            'known_gaps' => $this->knownGaps,
            'undocumented_message' => $this->undocumentedMessage,
        ];
    }
}
