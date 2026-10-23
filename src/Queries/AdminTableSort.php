<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Database\Eloquent\Builder;
use WebBlocks\Cms\Http\Requests\Admin\AdminTableSortRequest;

final class AdminTableSort
{
  private const TABLES = [
    'pages' => [['created_at', 'updated_at', 'title', 'slug', 'status'], 'created_at', 'desc'],
    'media' => [['created_at', 'updated_at', 'title', 'filename', 'kind', 'folder', 'usage'], 'updated_at', 'desc'],
    'shared-slots' => [['updated_at', 'name', 'handle', 'slot_name', 'public_shell'], 'updated_at', 'desc'],
    'blocks' => [['id', 'sort_order', 'status'], 'id', 'desc'],
    'automatic-pages' => [['name', 'source_handle', 'base_path', 'status'], '', 'desc'],
    'sites' => [['name', 'handle', 'pages_count', 'is_primary'], 'is_primary', 'desc'],
    'domains' => [['name', 'site_domains_count'], '', 'asc'],
    'site-domains' => [['domain', 'is_primary', 'redirect_to_primary', 'status'], 'is_primary', 'desc'],
    'locales' => [['code', 'name', 'is_enabled'], '', 'asc'],
    'page-layouts' => [['name', 'handle', 'body_class', 'is_active', 'sort_order'], 'sort_order', 'asc'],
    'slot-types' => [['name', 'slug', 'axis', 'sort_order', 'status'], 'sort_order', 'asc'],
    'block-types' => [['name', 'category', 'status'], '', 'asc'],
    'icons' => [['label', 'source', 'is_active', 'sort_order'], 'is_active', 'desc'],
    'embedded-applications' => [['name', 'handle', 'render_mode', 'is_enabled'], 'name', 'asc'],
    'users' => [['name', 'email', 'is_active', 'last_login_at'], '', 'asc'],
    'roles' => [['name', 'is_protected', 'assignment_count'], 'is_protected', 'desc', 'roles_page'],
    'api-tokens' => [['name', 'created_at', 'last_used_at'], '', 'desc'],
    'personal-api-tokens' => [['name', 'expires_at', 'last_used_at'], '', 'desc'],
    'engagement' => [['pending' => 'pending_count', 'votes' => 'vote_count', 'average' => 'average_rating', 'activity' => 'last_activity'], 'pending', 'desc'],
    'comments' => [['status', 'spam_score', 'created_at'], 'created_at', 'desc'],
    'ratings' => [['rating_value', 'status', 'created_at', 'updated_at'], 'created_at', 'desc'],
    'backups' => [['created_at', 'archive_filename', 'status', 'archive_size_bytes'], 'created_at', 'desc'],
    'exports' => [['created_at', 'includes_media', 'archive_size_bytes', 'status'], 'created_at', 'desc', 'exports_page', 'exports_'],
    'imports' => [['created_at', 'source_archive_name', 'status'], 'created_at', 'desc', 'imports_page', 'imports_'],
  ];

  public readonly string $key;

  public readonly string $direction;

  private readonly bool $explicit;

  private function __construct(public readonly string $listing)
  {
    $definition = self::TABLES[$listing];
    [$this->key, $this->direction, $this->explicit] = app(AdminTableSortRequest::class)->selection(
      array_keys($this->columns()), $this->sortParam(), $this->directionParam(), $definition[1], $definition[2],
    );
  }

  public static function for(string $listing): self
  {
    return new self($listing);
  }

  public function columns(): array
  {
    $columns = self::TABLES[$this->listing][0];

    return array_is_list($columns) ? array_combine($columns, $columns) : $columns;
  }

  public function pageParam(): string
  {
    return self::TABLES[$this->listing][3] ?? 'page';
  }

  public function sortParam(): string
  {
    return (self::TABLES[$this->listing][4] ?? '').'sort';
  }

  public function directionParam(): string
  {
    return (self::TABLES[$this->listing][4] ?? '').'direction';
  }

  public function apply(Builder $query): Builder
  {
    if ($this->explicit) {
      $column = $this->columns()[$this->key];
      $query->reorder()->orderBy($column, $this->direction);
    }

    return $query->orderBy($query->getModel()->getQualifiedKeyName());
  }

  public function nextDirection(string $key): string
  {
    return $this->key === $key && $this->direction === 'asc' ? 'desc' : 'asc';
  }

  public function filterFields(): array
  {
    return $this->explicit ? [$this->sortParam() => $this->key, $this->directionParam() => $this->direction] : [];
  }

  public function url(string $key): string
  {
    return request()->url().'?'.http_build_query([
      ...request()->query(), $this->sortParam() => $key, $this->directionParam() => $this->nextDirection($key), $this->pageParam() => 1,
    ]);
  }
}
