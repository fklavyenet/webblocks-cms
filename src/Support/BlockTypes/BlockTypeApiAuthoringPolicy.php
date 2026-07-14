<?php

namespace WebBlocks\Cms\Support\BlockTypes;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;

/**
 * Single source of truth for which block types the Internal Content API may
 * author.
 *
 * `html` is a reviewed human-only escape hatch: operators keep creating and
 * editing it in the CMS admin and existing published blocks keep rendering,
 * but no API mutation may create, update, replace, move, reorder, clone,
 * promote, publish, or delete one. No token capability overrides this — it is
 * a product policy, not a permission.
 *
 * Every API surface must consult this class instead of repeating the rule.
 */
class BlockTypeApiAuthoringPolicy
{
  public const ERROR_CODE = 'block_type_not_api_writable';

  public const AUTHORING_HUMAN_ONLY = 'human_only';

  public const AUTHORING_API_AND_HUMAN = 'api_and_human';

  /**
   * Block type slugs that only a human operator may author.
   *
   * @var list<string>
   */
  private const HUMAN_ONLY_TYPES = ['html'];

  /**
   * @return list<string>
   */
  public function humanOnlyTypes(): array
  {
    return self::HUMAN_ONLY_TYPES;
  }

  public function isApiWritable(?string $slug): bool
  {
    return ! in_array($this->normalize($slug), self::HUMAN_ONLY_TYPES, true);
  }

  /**
   * Read access is never restricted: tools may inspect existing HTML blocks.
   */
  public function isApiReadable(?string $slug): bool
  {
    return true;
  }

  public function authoringMode(?string $slug): string
  {
    return $this->isApiWritable($slug) ? self::AUTHORING_API_AND_HUMAN : self::AUTHORING_HUMAN_ONLY;
  }

  public function restrictionFor(?string $slug): ?string
  {
    if ($this->isApiWritable($slug)) {
      return null;
    }

    return 'The html block is a reviewed human-only escape hatch. It stays editable in the CMS admin and existing published blocks keep rendering, but the Internal Content API cannot create, update, move, publish, or delete it. Build the design with structured blocks, or report a capability gap instead of falling back to raw HTML.';
  }

  /**
   * Machine-readable authoring contract for discovery and content-contract
   * responses.
   *
   * @return array<string, mixed>
   */
  public function contractFor(?string $slug): array
  {
    $writable = $this->isApiWritable($slug);

    $contract = [
      'api_readable' => $this->isApiReadable($slug),
      'api_writable' => $writable,
      'authoring' => $this->authoringMode($slug),
    ];

    if (! $writable) {
      $contract['api_write_error_code'] = self::ERROR_CODE;
      $contract['api_write_restriction'] = $this->restrictionFor($slug);
    }

    return $contract;
  }

  public function message(?string $slug): string
  {
    return 'The '.$this->normalize($slug).' block type cannot be created, changed, moved, published, or deleted through the Internal Content API.';
  }

  /**
   * Normalizer-style error entry. Carries the stable code so a plan result can
   * surface it as the top-level error code.
   *
   * @return array{path: string, message: string, code: string}
   */
  public function error(string $path, ?string $slug = 'html'): array
  {
    return [
      'path' => $path,
      'message' => $this->message($slug).' '.$this->restrictionFor($slug),
      'code' => self::ERROR_CODE,
    ];
  }

  /**
   * @param  array<int, array<string, mixed>>  $errors
   */
  public function codeFromErrors(array $errors): ?string
  {
    foreach ($errors as $error) {
      if (is_array($error) && ($error['code'] ?? null) === self::ERROR_CODE) {
        return self::ERROR_CODE;
      }
    }

    return null;
  }

  /**
   * Controller-style rejection using the shared API error envelope.
   */
  public function rejectionResponse(string $path, ?string $slug = 'html'): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => self::ERROR_CODE,
      'message' => $this->message($slug),
      'warnings' => [],
      'errors' => [$this->error($path, $slug)],
    ], 422);
  }

  /**
   * True when any block in the affected scope is human-only, so an operation
   * that would move, publish, or remove that scope must be rejected before it
   * writes anything.
   *
   * @param  iterable<mixed>  $blocks  Block models or type slugs
   */
  public function scopeHasHumanOnlyBlock(iterable $blocks): bool
  {
    foreach ($blocks as $block) {
      $slug = $block instanceof Block ? $block->typeSlug() : $block;

      if (! $this->isApiWritable(is_string($slug) ? $slug : null)) {
        return true;
      }
    }

    return false;
  }

  /**
   * True when any of the given block ids, or any of their descendants, is a
   * human-only block.
   *
   * @param  iterable<int>  $blockIds
   */
  public function blockIdsScopeHasHumanOnlyBlock(iterable $blockIds): bool
  {
    $ids = collect($blockIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

    if ($ids->isEmpty()) {
      return false;
    }

    return $this->descendantScope($ids)
      ->contains(fn (Block $block): bool => ! $this->isApiWritable($block->typeSlug()));
  }

  /**
   * The given blocks plus every descendant, so subtree operations see nested
   * human-only blocks.
   *
   * @param  Collection<int, int>  $ids
   * @return Collection<int, Block>
   */
  private function descendantScope(Collection $ids): Collection
  {
    $scope = collect();
    $pending = $ids;

    while ($pending->isNotEmpty()) {
      $blocks = Block::query()->whereIn('id', $pending->all())->get(['id', 'type', 'block_type_id', 'parent_id']);

      if ($blocks->isEmpty()) {
        break;
      }

      $scope = $scope->concat($blocks);

      $pending = Block::query()
        ->whereIn('parent_id', $blocks->pluck('id')->all())
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->reject(fn (int $id): bool => $scope->contains('id', $id))
        ->values();
    }

    return $scope;
  }

  private function normalize(?string $slug): string
  {
    return strtolower(trim((string) $slug));
  }
}
