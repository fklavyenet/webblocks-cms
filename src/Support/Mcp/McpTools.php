<?php

namespace WebBlocks\Cms\Support\Mcp;

use Illuminate\Support\Facades\Validator;
use WebBlocks\Cms\Actions\Mcp\UpdateDraftHeading;
use WebBlocks\Cms\Models\Locale;

final class McpTools
{
  private const READ = 'find_page';

  private const WRITE = 'update_draft_heading';

  private const PREVIEW = 'get_draft_preview';

  public function __construct(
    private readonly McpPages $pages,
    private readonly UpdateDraftHeading $updateHeading,
  ) {}

  public function descriptors(): array
  {
    $locale = ['type' => 'string', 'maxLength' => 35];
    $id = ['type' => 'integer', 'minimum' => 1];

    return [
      $this->descriptor(self::READ, 'read_description', ['locale' => $locale, 'page_id' => $id, 'path' => ['type' => 'string', 'maxLength' => 255]], ['locale'], [
        'oneOf' => [['required' => ['page_id']], ['required' => ['path']]],
      ]),
      $this->descriptor(self::WRITE, 'write_description', [
        'locale' => $locale,
        'page_id' => $id,
        'block_id' => $id,
        'version' => ['type' => 'string', 'pattern' => '^[0-9a-f]{64}$'],
        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
      ], ['locale', 'page_id', 'block_id', 'version', 'title']),
      $this->descriptor(self::PREVIEW, 'preview_description', ['locale' => $locale, 'page_id' => $id], ['locale', 'page_id']),
    ];
  }

  public function scope(string $name): ?string
  {
    return match ($name) {
      self::READ, self::PREVIEW => McpConfiguration::READ,
      self::WRITE => McpConfiguration::WRITE,
      default => null,
    };
  }

  public function call(string $name, array $args, array $grant): array
  {
    $input = $this->validate($name, $args);

    return match ($name) {
      self::READ => $this->pages->read($grant['site'], $input),
      self::PREVIEW => $this->pages->preview($grant['site'], $input),
      self::WRITE => $this->updateHeading->handle($grant['user'], $grant['site'], $input),
    };
  }

  private function validate(string $name, array $args): array
  {
    $rules = ['locale' => ['required', 'string', 'max:35']];
    $id = ['required', 'integer', 'min:1'];
    $rules += match ($name) {
      self::READ => [
        'page_id' => ['required_without:path', 'prohibits:path', 'integer', 'min:1'],
        'path' => ['required_without:page_id', 'string', 'max:255'],
      ],
      self::PREVIEW => ['page_id' => $id],
      self::WRITE => [
        'page_id' => $id,
        'block_id' => $id,
        'title' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
        'version' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
      ],
    };

    $validator = Validator::make($args, $rules);
    if (array_diff_key($args, $rules) !== [] || $validator->fails()) {
      throw new McpToolException('invalid_request');
    }
    $data = $validator->validated();
    $data['locale'] = Locale::normalizeCode($data['locale']) ?? throw new McpToolException('invalid_request');
    if (isset($data['title'])) {
      $data['title'] = trim($data['title']);
    }

    return $data;
  }

  private function descriptor(string $name, string $description, array $properties, array $required, array $constraints = []): array
  {
    $readOnly = $name !== self::WRITE;
    $scopes = $readOnly ? [McpConfiguration::READ] : [McpConfiguration::READ, McpConfiguration::WRITE];
    $security = [['type' => 'oauth2', 'scopes' => $scopes]];

    return [
      'name' => $name,
      'description' => __('webblocks-cms::mcp.'.$description),
      'inputSchema' => ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false] + $constraints,
      'annotations' => ['readOnlyHint' => $readOnly, 'destructiveHint' => ! $readOnly, 'openWorldHint' => false],
      'securitySchemes' => $security,
      '_meta' => ['securitySchemes' => $security],
    ];
  }
}
