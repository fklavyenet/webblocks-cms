<?php

namespace WebBlocks\Cms\Support\Pages;

use WebBlocks\Cms\Models\Block;

/**
 * Normalized `page-list` block settings.
 *
 * Every reader — the public renderer, the admin form, the request validator,
 * and the Internal Content API patch policy — goes through this class, so an
 * unknown or malformed stored value degrades to the documented default in
 * exactly one place instead of once per call site.
 */
class PageListSettings
{
  public const SCOPE_PAGE_TYPE = 'page_type';

  public const SCOPE_PATH_PREFIX = 'path_prefix';

  public const SCOPE_SUBTREE_OF_CURRENT = 'subtree_of_current';

  public const SORT_PUBLISHED_DESC = 'published_desc';

  public const SORT_PUBLISHED_ASC = 'published_asc';

  public const SORT_TITLE_ASC = 'title_asc';

  public const SORT_PATH_ASC = 'path_asc';

  public const LAYOUT_CARDS = 'cards';

  public const LAYOUT_LINKS = 'links';

  public const LIMIT_MIN = 1;

  public const LIMIT_MAX = 48;

  public const LIMIT_DEFAULT = 12;

  private function __construct(
    public readonly string $scope,
    public readonly ?string $pageType,
    public readonly ?string $pathPrefix,
    public readonly string $sort,
    public readonly int $limit,
    public readonly string $layout,
    public readonly string $columns,
    public readonly bool $showThumbnail,
    public readonly bool $showDescription,
    public readonly bool $excludeCurrent,
    public readonly bool $clickableCard,
  ) {}

  public static function fromBlock(Block $block): self
  {
    return self::fromArray([
      'scope' => $block->setting('scope'),
      'page_type' => $block->setting('page_type'),
      'path_prefix' => $block->setting('path_prefix'),
      'sort' => $block->setting('sort'),
      'limit' => $block->setting('limit'),
      'layout' => $block->setting('layout'),
      'columns' => $block->setting('columns'),
      'show_thumbnail' => $block->setting('show_thumbnail'),
      'show_description' => $block->setting('show_description'),
      'exclude_current' => $block->setting('exclude_current'),
      'clickable_card' => $block->setting('clickable_card'),
    ]);
  }

  /**
   * @param  array<string, mixed>  $settings
   */
  public static function fromArray(array $settings): self
  {
    return new self(
      scope: self::enum($settings['scope'] ?? null, self::scopes(), self::SCOPE_PAGE_TYPE),
      pageType: self::text($settings['page_type'] ?? null),
      pathPrefix: self::normalizePathPrefix($settings['path_prefix'] ?? null),
      sort: self::enum($settings['sort'] ?? null, self::sorts(), self::SORT_PUBLISHED_DESC),
      limit: self::limit($settings['limit'] ?? null),
      layout: self::enum($settings['layout'] ?? null, self::layouts(), self::LAYOUT_CARDS),
      columns: self::enum($settings['columns'] ?? null, self::columnOptions(), '3'),
      showThumbnail: self::bool($settings['show_thumbnail'] ?? null, true),
      showDescription: self::bool($settings['show_description'] ?? null, true),
      excludeCurrent: self::bool($settings['exclude_current'] ?? null, true),
      clickableCard: self::bool($settings['clickable_card'] ?? null, false),
    );
  }

  /**
   * The stored settings payload, with every key present and normalized.
   *
   * @return array<string, mixed>
   */
  public function toArray(): array
  {
    return [
      'scope' => $this->scope,
      'page_type' => $this->pageType,
      'path_prefix' => $this->pathPrefix,
      'sort' => $this->sort,
      'limit' => $this->limit,
      'layout' => $this->layout,
      'columns' => $this->columns,
      'show_thumbnail' => $this->showThumbnail,
      'show_description' => $this->showDescription,
      'exclude_current' => $this->excludeCurrent,
      'clickable_card' => $this->clickableCard,
    ];
  }

  public function rendersCards(): bool
  {
    return $this->layout === self::LAYOUT_CARDS;
  }

  public function gridColumnsClass(): string
  {
    return match ($this->columns) {
      '2' => 'wb-grid-2',
      '4' => 'wb-grid-4',
      default => 'wb-grid-3',
    };
  }

  /**
   * @return list<string>
   */
  public static function scopes(): array
  {
    return [self::SCOPE_PAGE_TYPE, self::SCOPE_PATH_PREFIX, self::SCOPE_SUBTREE_OF_CURRENT];
  }

  /**
   * @return list<string>
   */
  public static function sorts(): array
  {
    return [self::SORT_PUBLISHED_DESC, self::SORT_PUBLISHED_ASC, self::SORT_TITLE_ASC, self::SORT_PATH_ASC];
  }

  /**
   * @return list<string>
   */
  public static function layouts(): array
  {
    return [self::LAYOUT_CARDS, self::LAYOUT_LINKS];
  }

  /**
   * @return list<string>
   */
  public static function columnOptions(): array
  {
    return ['2', '3', '4'];
  }

  /**
   * A stored prefix is normalized to a leading slash and no trailing slash, so
   * `guides/`, `/guides` and `/guides/` all describe the same subtree. The site
   * root is not a usable prefix — it would select every published page — so it
   * normalizes away to null, and a path-scoped block with a null prefix renders
   * nothing rather than the whole site.
   */
  public static function normalizePathPrefix(mixed $value): ?string
  {
    $prefix = trim((string) ($value ?? ''));

    if ($prefix === '') {
      return null;
    }

    $prefix = '/'.trim($prefix, '/');

    return $prefix === '/' ? null : $prefix;
  }

  /**
   * @param  list<string>  $allowed
   */
  private static function enum(mixed $value, array $allowed, string $default): string
  {
    $normalized = trim((string) ($value ?? ''));

    return in_array($normalized, $allowed, true) ? $normalized : $default;
  }

  private static function text(mixed $value): ?string
  {
    $text = trim((string) ($value ?? ''));

    return $text !== '' ? $text : null;
  }

  private static function limit(mixed $value): int
  {
    if (! is_numeric($value)) {
      return self::LIMIT_DEFAULT;
    }

    return max(self::LIMIT_MIN, min(self::LIMIT_MAX, (int) $value));
  }

  private static function bool(mixed $value, bool $default): bool
  {
    if ($value === null) {
      return $default;
    }

    $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

    return $normalized ?? $default;
  }
}
