<?php

namespace WebBlocks\Cms\Support\Mcp;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use stdClass;
use WebBlocks\Cms\Actions\Mcp\CreatePageDraft;
use WebBlocks\Cms\Actions\Mcp\EditDraftStructure;
use WebBlocks\Cms\Actions\Mcp\EditDraftTranslation;
use WebBlocks\Cms\Actions\Mcp\UpdateDraftBlock;
use WebBlocks\Cms\Actions\Mcp\UploadMedia;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Media\MediaUploadRules;
use WebBlocks\Cms\Support\Pages\PageTranslationPayload;

final class McpTools
{
  private const READ = 'find_page';

  private const SEARCH = 'search_pages';

  private const WRITE = 'update_draft_heading';

  private const PREVIEW = 'get_draft_preview';

  private const CONTENT = 'get_page_content';

  private const EDIT = 'update_draft_block';

  private const MEDIA = 'find_media';

  private const CREATE = 'create_page_draft';

  private const STRUCTURE = 'get_draft_structure';

  private const EDIT_STRUCTURE = 'edit_draft_structure';

  private const TRANSLATION = 'get_draft_translation';

  private const EDIT_TRANSLATION = 'update_draft_translation';

  public const UPLOAD = 'upload_media';

  private const UPLOAD_COPY_LIMIT = 2000;

  public function __construct(
    private readonly McpPages $pages,
    private readonly UpdateDraftBlock $updateBlock,
    private readonly McpMedia $media,
    private readonly CreatePageDraft $createDraft,
    private readonly EditDraftStructure $structure,
    private readonly EditDraftTranslation $translation,
    private readonly UploadMedia $upload,
  ) {}

  public function descriptors(): array
  {
    $locale = ['type' => 'string', 'maxLength' => 35];
    $id = ['type' => 'integer', 'minimum' => 1];

    $version = ['type' => 'string', 'pattern' => '^[0-9a-f]{64}$'];
    $page = ['type' => 'integer', 'minimum' => 1, 'maximum' => McpPages::MAX_PAGE_NUMBER];
    $text = ['type' => 'string', 'maxLength' => 255];
    $copy = [
      'title' => $text,
      'subtitle' => $text,
      'eyebrow' => $text,
      'caption' => $text,
      'alt_text' => $text,
      'content' => ['type' => 'string', 'maxLength' => 8000],
    ];

    $slot = ['type' => 'string', 'minLength' => 1, 'maxLength' => 100];
    $parent = ['type' => ['integer', 'null'], 'minimum' => 1];

    $translation = array_fill_keys(PageTranslationPayload::IDENTITY_FIELDS, $text);
    foreach (PageTranslationPayload::TEXT_FIELDS as $field => $limit) {
      $translation[$field] = ['type' => ['string', 'null'], 'maxLength' => $limit];
    }
    $translation['og_image_media_id'] = ['type' => ['integer', 'null'], 'minimum' => 1];

    return [
      $this->descriptor(self::READ, 'read_description', ['locale' => $locale, 'page_id' => $id, 'path' => ['type' => 'string', 'maxLength' => 255]], ['locale'], [
        'oneOf' => [['required' => ['page_id']], ['required' => ['path']]],
      ]),
      $this->descriptor(self::WRITE, 'write_description', [
        'locale' => $locale,
        'page_id' => $id,
        'block_id' => $id,
        'version' => $version,
        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
      ], ['locale', 'page_id', 'block_id', 'version', 'title']),
      $this->descriptor(self::PREVIEW, 'preview_description', ['locale' => $locale, 'page_id' => $id], ['locale', 'page_id']),
      $this->descriptor(self::CONTENT, 'content_description', [
        'locale' => $locale, 'page_id' => $id, 'page' => $page,
      ], ['locale', 'page_id']),
      $this->descriptor(self::EDIT, 'edit_description', [
        'locale' => $locale,
        'page_id' => $id,
        'block_id' => $id,
        'version' => $version,
        'text' => ['type' => 'object', 'properties' => $copy, 'minProperties' => 1, 'additionalProperties' => false],
        'media_id' => $id,
      ], ['locale', 'page_id', 'block_id', 'version'], [
        'anyOf' => [['required' => ['text']], ['required' => ['media_id']]],
      ]),
      $this->descriptor(self::SEARCH, 'search_description', [
        'locale' => $locale,
        'q' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
        'page' => $page,
      ], ['locale', 'q']),
      $this->descriptor(self::MEDIA, 'media_description', [
        'q' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120], 'page' => $page,
      ], []),
      $this->descriptor(self::CREATE, 'create_draft_description', [
        'locale' => $locale,
        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
        'path' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
        'layout' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100, 'default' => 'default'],
      ], ['locale', 'title', 'path']),
      $this->descriptor(self::STRUCTURE, 'structure_description', [
        'locale' => $locale, 'page_id' => $id, 'page' => $page,
      ], ['locale', 'page_id']),
      $this->descriptor(self::EDIT_STRUCTURE, 'edit_structure_description', [
        'locale' => $locale,
        'page_id' => $id,
        'version' => $version,
        'operation' => ['type' => 'string', 'enum' => ['prepare', 'add', 'move', 'reorder']],
        'slots' => ['type' => 'array', 'items' => $slot, 'minItems' => 1, 'maxItems' => 32, 'uniqueItems' => true],
        'slot' => $slot,
        'parent_id' => $parent,
        'type' => ['type' => 'string', 'enum' => EditDraftStructure::ADD_TYPES],
        'text' => ['type' => 'object', 'properties' => $copy, 'additionalProperties' => false],
        'block_id' => $id,
        'block_ids' => ['type' => 'array', 'items' => $id, 'minItems' => 1, 'maxItems' => 500, 'uniqueItems' => true],
      ], ['locale', 'page_id', 'version', 'operation'], [
        'oneOf' => [
          ['properties' => ['operation' => ['const' => 'prepare']], 'required' => ['slots']],
          ['properties' => ['operation' => ['const' => 'add']], 'required' => ['slot', 'type']],
          ['properties' => ['operation' => ['const' => 'move']], 'required' => ['slot', 'block_id']],
          ['properties' => ['operation' => ['const' => 'reorder']], 'required' => ['slot', 'block_ids']],
        ],
      ]),
      $this->descriptor(self::TRANSLATION, 'translation_description', [
        'locale' => $locale, 'page_id' => $id,
      ], ['locale', 'page_id']),
      $this->descriptor(self::EDIT_TRANSLATION, 'edit_translation_description', [
        'locale' => $locale, 'page_id' => $id, 'version' => $version,
      ] + $translation, ['locale', 'page_id', 'version']),
      $this->descriptor(self::UPLOAD, 'upload_description', [
        'filename' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
        'content_base64' => ['type' => 'string', 'contentEncoding' => 'base64', 'minLength' => 4, 'maxLength' => UploadMedia::MAX_BASE64_LENGTH],
        'title' => $text,
        'alt_text' => $text,
        'caption' => ['type' => 'string', 'maxLength' => self::UPLOAD_COPY_LIMIT],
        'description' => ['type' => 'string', 'maxLength' => self::UPLOAD_COPY_LIMIT],
      ], ['filename', 'content_base64']),
    ];
  }

  public function scope(string $name): ?string
  {
    return match ($name) {
      self::READ, self::SEARCH, self::PREVIEW => McpConfiguration::READ,
      self::WRITE => McpConfiguration::WRITE,
      self::CREATE => McpConfiguration::CREATE,
      self::STRUCTURE, self::EDIT_STRUCTURE => McpConfiguration::STRUCTURE,
      self::TRANSLATION, self::EDIT_TRANSLATION => McpConfiguration::TRANSLATION,
      self::UPLOAD => McpConfiguration::UPLOAD,
      self::CONTENT, self::EDIT, self::MEDIA => McpConfiguration::CONTENT,
      default => null,
    };
  }

  public function call(string $name, array $args, array $grant): array
  {
    $input = $this->validate($name, $args);

    return match ($name) {
      self::READ => $this->pages->read($grant['site'], $input),
      self::SEARCH => $this->pages->search($grant['site'], $input),
      self::PREVIEW => $this->pages->preview($grant['site'], $input),
      self::CREATE => $this->createDraft->handle($grant['user'], $grant['site'], $input),
      self::STRUCTURE => $this->structure->read($grant['site'], $input),
      self::EDIT_STRUCTURE => $this->structure->handle($grant['user'], $grant['site'], $input),
      self::TRANSLATION => $this->translation->read($grant['site'], $input),
      self::EDIT_TRANSLATION => $this->translation->handle($grant['user'], $grant['site'], $input),
      self::WRITE => $this->updateBlock->handle($grant['user'], $grant['site'], $input, headingOnly: true),
      self::CONTENT => $this->pages->content($grant['user'], $grant['site'], $input),
      self::EDIT => $this->updateBlock->handle($grant['user'], $grant['site'], $input),
      self::MEDIA => $this->media->find($grant['user'], $grant['site'], $input),
      self::UPLOAD => $this->upload->handle($grant['user'], $grant['site'], $input),
    };
  }

  private function validate(string $name, array $args): array
  {
    $rules = in_array($name, [self::MEDIA, self::UPLOAD], true) ? [] : ['locale' => ['required', 'string', 'max:35']];
    $id = ['required', 'integer', 'min:1'];
    $version = ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'];
    $page = ['sometimes', 'integer', 'min:1', 'max:'.McpPages::MAX_PAGE_NUMBER];
    $shortText = ['sometimes', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'];
    $rules += match ($name) {
      self::READ => [
        'page_id' => ['required_without:path', 'prohibits:path', 'integer', 'min:1'],
        'path' => ['required_without:page_id', 'string', 'max:255'],
      ],
      self::CREATE => [
        'title' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
        'path' => ['required', 'string', 'max:255'],
        'layout' => ['sometimes', 'required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:[-_][a-z0-9]+)*\z/'],
      ],
      self::SEARCH => ['q' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x1F\x7F]/'], 'page' => $page],
      self::PREVIEW, self::TRANSLATION => ['page_id' => $id],
      self::EDIT_TRANSLATION => ['page_id' => $id, 'version' => $version] + $this->translationRules(),
      self::WRITE => [
        'page_id' => $id,
        'block_id' => $id,
        'title' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
        'version' => $version,
      ],
      self::CONTENT, self::STRUCTURE => ['page_id' => $id, 'page' => $page],
      self::EDIT_STRUCTURE => ['page_id' => $id, 'version' => $version] + $this->structureRules($args['operation'] ?? null),
      self::MEDIA => ['q' => ['sometimes', 'required', 'string', 'max:120'], 'page' => $page],
      self::UPLOAD => [
        'filename' => ['required', 'string', 'max:255', 'not_regex:~[/:\\\\\x00-\x1F\x7F]~'],
        'content_base64' => ['required', 'string', 'max:'.UploadMedia::MAX_BASE64_LENGTH],
        'caption' => [...MediaUploadRules::metadata()['caption'], 'max:'.self::UPLOAD_COPY_LIMIT],
        'description' => [...MediaUploadRules::metadata()['description'], 'max:'.self::UPLOAD_COPY_LIMIT],
      ] + MediaUploadRules::metadata(),
      self::EDIT => [
        'page_id' => $id,
        'block_id' => $id,
        'version' => $version,
        'text' => ['required_without:media_id', 'array:title,subtitle,eyebrow,caption,alt_text,content', 'min:1'],
        'text.title' => $shortText,
        'text.subtitle' => $shortText,
        'text.eyebrow' => $shortText,
        'text.caption' => $shortText,
        'text.alt_text' => $shortText,
        'text.content' => ['sometimes', 'string', 'max:8000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/'],
        'media_id' => ['required_without:text', 'integer', 'min:1'],
      ],
    };

    if (($args['text'] ?? null) instanceof stdClass) {
      $args['text'] = (array) $args['text'];
    }
    $validator = Validator::make($args, $rules);
    $topLevelRules = array_filter($rules, fn (string $key) => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY);
    if (array_diff_key($args, $topLevelRules) !== [] || $validator->fails()) {
      throw new McpToolException('invalid_request');
    }
    $data = $validator->validated();
    if (isset($data['locale'])) {
      $data['locale'] = Locale::normalizeCode($data['locale']) ?? throw new McpToolException('invalid_request');
    }
    if (isset($data['title'])) {
      $data['title'] = trim($data['title']);
    }

    return $data;
  }

  private function structureRules(mixed $operation): array
  {
    $slot = ['required', 'string', 'max:100'];
    $placement = ['slot' => $slot, 'parent_id' => ['nullable', 'integer', 'min:1']];
    $shortText = ['sometimes', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'];
    $rules = match ($operation) {
      'prepare' => ['slots' => ['required', 'array', 'list', 'min:1', 'max:32'], 'slots.*' => [...$slot, 'distinct']],
      'add' => $placement + [
        'type' => ['required', 'string', Rule::in(EditDraftStructure::ADD_TYPES)],
        'text' => ['sometimes', 'array:title,subtitle,eyebrow,caption,alt_text,content'],
        'text.title' => $shortText,
        'text.subtitle' => $shortText,
        'text.eyebrow' => $shortText,
        'text.caption' => $shortText,
        'text.alt_text' => $shortText,
        'text.content' => ['sometimes', 'string', 'max:8000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/'],
      ],
      'move' => $placement + ['block_id' => ['required', 'integer', 'min:1']],
      'reorder' => $placement + [
        'block_ids' => ['required', 'array', 'list', 'min:1', 'max:500'],
        'block_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
      ],
      default => throw new McpToolException('invalid_request'),
    };

    return ['operation' => ['required', 'string']] + $rules;
  }

  private function translationRules(): array
  {
    $rules = array_fill_keys(PageTranslationPayload::IDENTITY_FIELDS, ['sometimes', 'required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/']);
    foreach (PageTranslationPayload::TEXT_FIELDS as $field => $limit) {
      $rules[$field] = ['sometimes', 'nullable', 'string', 'max:'.$limit];
    }
    $rules['og_image_media_id'] = ['sometimes', 'nullable', 'integer', 'min:1'];

    return $rules;
  }

  private function descriptor(string $name, string $description, array $properties, array $required, array $constraints = []): array
  {
    $readOnly = ! in_array($name, [self::WRITE, self::EDIT, self::CREATE, self::EDIT_STRUCTURE, self::EDIT_TRANSLATION, self::UPLOAD], true);
    $scope = $this->scope($name);
    $scopes = $scope === McpConfiguration::READ ? [$scope] : [McpConfiguration::READ, $scope];
    $security = [['type' => 'oauth2', 'scopes' => $scopes]];

    return [
      'name' => $name,
      'description' => __('webblocks-cms::mcp.'.$description),
      'inputSchema' => ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false] + $constraints,
      'annotations' => ['readOnlyHint' => $readOnly, 'destructiveHint' => ! $readOnly && ! in_array($name, [self::CREATE, self::UPLOAD], true), 'openWorldHint' => false]
        + match ($name) {
          self::CREATE => ['idempotentHint' => true],
          self::UPLOAD => ['idempotentHint' => false],
          default => [],
        },
      'securitySchemes' => $security,
      '_meta' => ['securitySchemes' => $security],
    ];
  }
}
