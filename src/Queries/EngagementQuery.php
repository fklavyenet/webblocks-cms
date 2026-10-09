<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContentRating;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class EngagementQuery
{
  public function sites($user)
  {
    return app(AdminAuthorization::class)->scopeSitesForUser(Site::query(), $user)->orderBy('name')->get();
  }

  public function pages($user, array $filters)
  {
    return Page::query()->whereIn('site_id', $this->sites($user)->modelKeys())
      ->when($filters['site'] ?? '', fn ($query, $site) => $query->where('site_id', $site))->with('translations')->orderBy('id')->get();
  }

  public function comments($user, array $filters = []): Builder
  {
    return $this->filter($this->scope(CommentEntry::query(), $user), $filters)
      ->when($filters['status'] ?? '', fn ($query, $status) => $query->where('status', $status));
  }

  public function ratings($user, array $filters = []): Builder
  {
    return $this->filter($this->scope(ContentRating::query(), $user), $filters)
      ->when($filters['status'] ?? '', fn ($query, $status) => $query->where('status', $status))
      ->when($filters['rating'] ?? '', fn ($query, $rating) => $query->where('rating_value', $rating));
  }

  public function overview($user, array $filters): array
  {
    $commentsReady = Schema::hasTable('wbcms_comment_entries');
    $ratingsReady = Schema::hasTable('wbcms_content_ratings');
    $comments = $commentsReady ? $this->comments($user, $filters) : null;
    $ratings = $ratingsReady ? $this->ratings($user, $filters) : null;
    $active = $ratings ? (clone $ratings)->where('status', 'active')->where('rating_max', 5) : null;
    $pending = $comments ? (clone $comments)->where('status', 'pending') : null;
    $distribution = $active ? (clone $active)->selectRaw('rating_value, count(*) as aggregate')->groupBy('rating_value')->pluck('aggregate', 'rating_value')->all() : [];

    return [
      'tableReady' => $commentsReady && $ratingsReady,
      'commentsCount' => $comments ? (clone $comments)->count() : 0,
      'pendingCommentsCount' => $pending ? (clone $pending)->count() : 0,
      'oldestPending' => $pending ? (clone $pending)->min('created_at') : null,
      'commentStatuses' => $comments ? (clone $comments)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all() : [],
      'ratingsCount' => $ratings ? (clone $ratings)->count() : 0,
      'activeRatingsCount' => $active ? (clone $active)->count() : 0,
      'averageRating' => $active && (clone $active)->exists() ? round((float) (clone $active)->avg('rating_value'), 1) : null,
      'distribution' => $distribution,
      'comments7' => $comments ? (clone $comments)->where('created_at', '>=', now()->subDays(7))->count() : 0,
      'comments30' => $comments ? (clone $comments)->where('created_at', '>=', now()->subDays(30))->count() : 0,
      'ratings7' => $active ? (clone $active)->where('created_at', '>=', now()->subDays(7))->count() : 0,
      'ratings30' => $active ? (clone $active)->where('created_at', '>=', now()->subDays(30))->count() : 0,
    ];
  }

  public function pageSummary($user, array $filters)
  {
    if (! Schema::hasTable('wbcms_comment_entries') || ! Schema::hasTable('wbcms_content_ratings')) {
      return new LengthAwarePaginator([], 0, AdminPagination::perPage());
    }
    $comments = $this->comments($user, $filters)->selectRaw("page_id, count(*) as comment_count, sum(case when status = 'pending' then 1 else 0 end) as pending_count, max(created_at) as comment_activity")->groupBy('page_id');
    $ratings = $this->ratings($user, $filters)->where('status', 'active')->where('rating_max', 5)->selectRaw('page_id, count(*) as vote_count, avg(rating_value) as average_rating, max(updated_at) as rating_activity')->groupBy('page_id');
    $table = (new Page)->getTable();
    $sort = match ($filters['sort'] ?? 'pending') {
      'votes' => 'vote_count', 'average' => 'average_rating', 'activity' => 'last_activity', default => 'pending_count',
    };

    return Page::query()->whereIn($table.'.site_id', $this->sites($user)->modelKeys())
      ->leftJoinSub($comments, 'feedback_comments', fn ($join) => $join->on('feedback_comments.page_id', '=', $table.'.id'))
      ->leftJoinSub($ratings, 'feedback_ratings', fn ($join) => $join->on('feedback_ratings.page_id', '=', $table.'.id'))
      ->where(fn ($query) => $query->whereNotNull('comment_count')->orWhereNotNull('vote_count'))
      ->select($table.'.*')->selectRaw("coalesce(comment_count, 0) as comment_count, coalesce(pending_count, 0) as pending_count, coalesce(vote_count, 0) as vote_count, average_rating, case when coalesce(comment_activity, '') > coalesce(rating_activity, '') then comment_activity else rating_activity end as last_activity")
      ->with(['site', 'translations'])->orderByDesc($sort)->orderBy($table.'.id')->paginate(AdminPagination::perPage())->withQueryString();
  }

  private function scope(Builder $query, $user): Builder
  {
    return $query->whereIn('site_id', $this->sites($user)->modelKeys());
  }

  private function filter(Builder $query, array $filters): Builder
  {
    $isComment = $query->getModel() instanceof CommentEntry;

    return $query->when($filters['site'] ?? '', fn ($query, $site) => $query->where('site_id', $site))
      ->when($filters['page_id'] ?? '', fn ($query, $page) => $query->where('page_id', $page))
      ->when($filters['from'] ?? '', fn ($query, $date) => $query->where('created_at', '>=', $date.' 00:00:00'))
      ->when($filters['until'] ?? '', fn ($query, $date) => $query->where('created_at', '<', Carbon::parse($date)->addDay()->format('Y-m-d').' 00:00:00'))
      ->when($filters['search'] ?? '', fn ($query, $search) => $query->where(function ($query) use ($search, $isComment): void {
        $query->whereHas('page.translations', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('path', 'like', "%{$search}%"));
        if ($isComment) {
          $query->orWhere('author_name', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%");
        }
      }));
  }
}
