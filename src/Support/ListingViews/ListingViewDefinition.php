<?php

namespace WebBlocks\Cms\Support\ListingViews;

use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class ListingViewDefinition
{
  public function defaults(string $listing): array
  {
    return $listing === 'pages'
      ? ['site' => 'all', 'search' => '', 'status' => '', 'sort' => 'created_at', 'direction' => 'desc']
      : ['folder_id' => '', 'search' => '', 'kind' => '', 'usage' => '', 'sort' => 'updated_at', 'direction' => 'desc', 'view' => 'list'];
  }

  public function columns(string $listing): array
  {
    return $listing === 'pages' ? ['id', 'view', 'blocks', 'status', 'updated'] : ['preview', 'folder', 'usage', 'updated'];
  }

  public function normalizeColumns(string $listing, mixed $value): array
  {
    $columns = is_string($value) ? (in_array($value, ['', 'none'], true) ? [] : explode(',', $value)) : $value;
    $this->ensure(is_array($columns) && count($columns) <= count($this->columns($listing)));
    $columns = array_values(array_filter($columns, fn ($column) => $column !== '' && $column !== null));
    foreach ($columns as $column) {
      $this->ensure(is_string($column) && in_array($column, $this->columns($listing), true));
    }

    $this->ensure(count(array_unique($columns)) === count($columns));

    return $columns;
  }

  public function normalize(string $listing, array $filters, $user): array
  {
    $defaults = $this->defaults($listing);
    $this->ensure(array_diff(array_keys($filters), array_keys($defaults)) === []);
    $values = $defaults;
    foreach ($filters as $key => $value) {
      $this->ensure(is_scalar($value) || $value === null);
      $values[$key] = trim((string) $value);
      $this->ensure(mb_strlen($values[$key]) <= ($key === 'search' ? 255 : 32));
    }
    $sorts = $listing === 'pages' ? ['created_at', 'updated_at', 'title', 'slug', 'status'] : ['created_at', 'updated_at', 'title', 'filename', 'kind', 'folder', 'usage'];
    $this->ensure(in_array($values['sort'], $sorts, true) && in_array($values['direction'], ['asc', 'desc'], true));
    if ($listing === 'pages') {
      $this->ensure(in_array($values['status'], ['', 'draft', 'in_review', 'published', 'archived'], true));
      if ($values['site'] !== 'all') {
        $site = ctype_digit($values['site']) ? Site::query()->find((int) $values['site']) : null;
        $this->ensure($site && app(CmsAuthorization::class)->allows($user, 'cms.pages.read', $site->id));
        $values['site'] = (string) $site->id;
      }
    } else {
      $this->ensure(in_array($values['kind'], ['', 'image', 'video', 'document', 'other'], true));
      $this->ensure(in_array($values['usage'], ['', 'used', 'unused'], true));
      $this->ensure(in_array($values['view'], ['list', 'grid'], true));
      if ($values['folder_id'] !== '') {
        $this->ensure(ctype_digit($values['folder_id']) && MediaFolder::query()->whereKey((int) $values['folder_id'])->exists());
        $values['folder_id'] = (string) (int) $values['folder_id'];
      }
    }

    return $values;
  }

  private function ensure(bool $condition): void
  {
    if (! $condition) {
      throw ValidationException::withMessages(['saved_view' => __('webblocks-cms::listing_views.invalid')]);
    }
  }
}
