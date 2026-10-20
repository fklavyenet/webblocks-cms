<?php

namespace WebBlocks\Cms\Actions\ListingViews;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Policies\ListingViewPolicy;
use WebBlocks\Cms\Queries\ListingViews\ListingViewQuery;
use WebBlocks\Cms\Support\ListingViews\ListingViewDefinition;

final class WriteListingView
{
  public function save($user, string $listing, array $data, ?int $id = null): int
  {
    return DB::transaction(function () use ($user, $listing, $data, $id): int {
      DB::table('users')->where('id', $user->getAuthIdentifier())->lockForUpdate()->first();
      abort_unless(app(ListingViewPolicy::class)->listing($user->fresh(), $listing), 403);
      if ($id) {
        app(ListingViewQuery::class)->owned($user, $listing, $id);
      }
      $query = DB::table('wbcms_listing_views')->where('user_id', $user->getAuthIdentifier())->where('listing', $listing);
      if ((clone $query)->where('name', $data['name'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists() || (! $id && (clone $query)->count() >= 50)) {
        throw ValidationException::withMessages(['name' => __('webblocks-cms::listing_views.name_unavailable')]);
      }
      $definition = app(ListingViewDefinition::class);
      if ($data['operation'] === 'save') {
        $filters = $definition->normalize($listing, $data['filters'], $user);
        $columns = $definition->normalizeColumns($listing, $data['columns'] ?? '');
      }
      if (! $id) {
        abort_unless($data['operation'] === 'save', 422);
        $id = DB::table('wbcms_listing_views')->insertGetId(['user_id' => $user->getAuthIdentifier(), 'listing' => $listing, 'name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
      } else {
        DB::table('wbcms_listing_views')->where('id', $id)->update(['name' => $data['name'], 'updated_at' => now()]);
      }
      if ($data['operation'] === 'save') {
        DB::table('wbcms_listing_view_filters')->where('view_id', $id)->delete();
        DB::table('wbcms_listing_view_columns')->where('view_id', $id)->delete();
        foreach ($filters as $key => $value) {
          DB::table('wbcms_listing_view_filters')->insert(['view_id' => $id, 'filter_name' => $key, 'filter_value' => $value]);
        }
        foreach ($columns as $position => $column) {
          DB::table('wbcms_listing_view_columns')->insert(['view_id' => $id, 'position' => $position, 'column_name' => $column]);
        }
      }

      return $id;
    });
  }

  public function delete($user, string $listing, int $id): void
  {
    DB::transaction(function () use ($user, $listing, $id): void {
      DB::table('users')->where('id', $user->getAuthIdentifier())->lockForUpdate()->first();
      abort_unless(app(ListingViewPolicy::class)->listing($user->fresh(), $listing), 403);
      app(ListingViewQuery::class)->owned($user, $listing, $id);
      DB::table('wbcms_listing_views')->where('id', $id)->delete();
    });
  }
}
