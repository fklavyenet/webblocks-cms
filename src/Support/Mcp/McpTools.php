<?php

namespace WebBlocks\Cms\Support\Mcp;

use Illuminate\Support\Facades\Validator;
use stdClass;
use WebBlocks\Cms\Actions\Mcp\CreatePageDraft;
use WebBlocks\Cms\Actions\Mcp\UpdateDraftBlock;
use WebBlocks\Cms\Models\Locale;

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

  public function __construct(
    private readonly McpPages $pages,
    private readonly UpdateDraftBlock $updateBlock,
    private readonly McpMedia $media,
    private readonly CreatePageDraft $createDraft,
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
    ];
  }

  public function scope(string $name): ?string
  {
    return match ($name) {
      self::READ, self::SEARCH, self::PREVIEW => McpConfiguration::READ,
      self::WRITE => McpConfiguration::WRITE,
      self::CREATE => McpConfiguration::CREATE,
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
      self::WRITE => $this->updateBlock->handle($grant['user'], $grant['site'], $input, headingOnly: true),
      self::CONTENT => $this->pages->content($grant['user'], $grant['site'], $input),
      self::EDIT => $this->updateBlock->handle($grant['user'], $grant['site'], $input),
      self::MEDIA => $this->media->find($grant['user'], $grant['site'], $input),
    };
  }

  private function validate(string $name, array $args): array
  {
    $rules = $name === self::MEDIA ? [] : ['locale' => ['required', 'string', 'max:35']];
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
      self::PREVIEW => ['page_id' => $id],
      self::WRITE => [
        'page_id' => $id,
        'block_id' => $id,
        'title' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
        'version' => $version,
      ],
      self::CONTENT => ['page_id' => $id, 'page' => $page],
      self::MEDIA => ['q' => ['sometimes', 'required', 'string', 'max:120'], 'page' => $page],
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
    if (array_diff_key($args, $rules) !== [] || $validator->fails()) {
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

  private function descriptor(string $name, string $description, array $properties, array $required, array $constraints = []): array
  {
    $readOnly = ! in_array($name, [self::WRITE, self::EDIT, self::CREATE], true);
    $scope = $this->scope($name);
    $scopes = $scope === McpConfiguration::READ ? [$scope] : [McpConfiguration::READ, $scope];
    $security = [['type' => 'oauth2', 'scopes' => $scopes]];

    return [
      'name' => $name,
      'description' => __('webblocks-cms::mcp.'.$description),
      'inputSchema' => ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false] + $constraints,
      'annotations' => ['readOnlyHint' => $readOnly, 'destructiveHint' => ! $readOnly && $name !== self::CREATE, 'openWorldHint' => false]
        + ($name === self::CREATE ? ['idempotentHint' => true] : []),
      'securitySchemes' => $security,
      '_meta' => ['securitySchemes' => $security],
    ];
  }
}
