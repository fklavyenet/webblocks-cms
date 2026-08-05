<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContentRating;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class EngagementController extends Controller
{
  public function __construct(
    private readonly AdminLocaleResolver $localeResolver,
    private readonly CmsTranslator $translator,
  ) {}

  public function index(Request $request): View
  {
    $commentsReady = Schema::hasTable('wbcms_comment_entries');
    $ratingsReady = Schema::hasTable('wbcms_content_ratings');

    $commentsCount = $commentsReady
      ? (clone $this->scopeCommentsForUser(CommentEntry::query(), $request->user()))->count()
      : 0;
    $pendingCommentsCount = $commentsReady
      ? (clone $this->scopeCommentsForUser(CommentEntry::query(), $request->user()))->where('status', 'pending')->count()
      : 0;
    $ratingsCount = $ratingsReady
      ? (clone $this->scopeRatingsForUser(ContentRating::query(), $request->user()))->count()
      : 0;
    $averageRating = $ratingsReady && $ratingsCount > 0
      ? round((float) $this->scopeRatingsForUser(ContentRating::query(), $request->user())->avg('rating_value'), 1)
      : null;

    return view('webblocks-cms::admin.engagement.index', [
      'tableReady' => $commentsReady && $ratingsReady,
      'commentsCount' => $commentsCount,
      'pendingCommentsCount' => $pendingCommentsCount,
      'ratingsCount' => $ratingsCount,
      'averageRating' => $averageRating,
    ]);
  }

  public function comments(Request $request): View
  {
    if (! Schema::hasTable('wbcms_comment_entries')) {
      return view('webblocks-cms::admin.engagement.comments', [
        'comments' => new LengthAwarePaginator([], 0, AdminPagination::perPage()),
        'filters' => [
          'search' => '',
          'status' => '',
        ],
        'totalCount' => 0,
        'filteredCount' => 0,
        'statuses' => CommentEntry::statuses(),
        'tableReady' => false,
      ]);
    }

    $search = trim((string) $request->string('search'));
    $status = $request->string('status')->toString();

    if (! in_array($status, CommentEntry::statuses(), true)) {
      $status = '';
    }

    $baseQuery = $this->scopeCommentsForUser(CommentEntry::query(), $request->user());
    $filteredQuery = $this->scopeCommentsForUser(CommentEntry::query(), $request->user())
      ->when($search !== '', function (Builder $query) use ($search): void {
        $query->where(function (Builder $inner) use ($search): void {
          $inner->where('author_name', 'like', "%{$search}%")
            ->orWhere('body', 'like', "%{$search}%")
            ->orWhereHas('page.translations', fn (Builder $translationQuery) => $translationQuery
              ->where('name', 'like', "%{$search}%")
              ->orWhere('slug', 'like', "%{$search}%")
              ->orWhere('path', 'like', "%{$search}%"));
        });
      })
      ->when($status !== '', fn (Builder $query) => $query->where('status', $status));

    $comments = $filteredQuery
      ->with(['page.site', 'page.translations', 'block.blockType'])
      ->latest()
      ->paginate(AdminPagination::perPage())
      ->withQueryString();

    AdminPagination::redirectOutOfRange($comments, $request);

    return view('webblocks-cms::admin.engagement.comments', [
      'comments' => $comments,
      'filters' => [
        'search' => $search,
        'status' => $status,
      ],
      'totalCount' => (clone $baseQuery)->count(),
      'filteredCount' => (clone $filteredQuery)->count(),
      'statuses' => CommentEntry::statuses(),
      'tableReady' => true,
    ]);
  }

  public function ratings(Request $request): View
  {
    if (! Schema::hasTable('wbcms_content_ratings')) {
      return view('webblocks-cms::admin.engagement.ratings', [
        'ratings' => new LengthAwarePaginator([], 0, AdminPagination::perPage()),
        'filters' => ['search' => '', 'rating' => ''],
        'ratingOptions' => [],
        'totalCount' => 0,
        'filteredCount' => 0,
        'tableReady' => false,
      ]);
    }

    $search = trim((string) $request->string('search'));
    $rating = $request->string('rating')->toString();

    if ($rating !== '' && ! ctype_digit($rating)) {
      $rating = '';
    }

    $baseQuery = $this->scopeRatingsForUser(ContentRating::query(), $request->user());
    $maxRating = (int) (clone $baseQuery)->max('rating_max') ?: 5;
    $ratingOptions = range(1, max($maxRating, 1));

    $filteredQuery = $this->scopeRatingsForUser(ContentRating::query(), $request->user())
      ->when($search !== '', fn (Builder $query) => $query->whereHas('page.translations', fn (Builder $translationQuery) => $translationQuery
        ->where('name', 'like', "%{$search}%")
        ->orWhere('slug', 'like', "%{$search}%")
        ->orWhere('path', 'like', "%{$search}%")))
      ->when($rating !== '', fn (Builder $query) => $query->where('rating_value', (int) $rating));

    $ratings = (clone $filteredQuery)
      ->with(['page.site', 'page.translations', 'block.blockType'])
      ->latest()
      ->paginate(AdminPagination::perPage())
      ->withQueryString();

    AdminPagination::redirectOutOfRange($ratings, $request);

    return view('webblocks-cms::admin.engagement.ratings', [
      'ratings' => $ratings,
      'filters' => ['search' => $search, 'rating' => $rating],
      'ratingOptions' => $ratingOptions,
      'totalCount' => (clone $baseQuery)->count(),
      'filteredCount' => (clone $filteredQuery)->count(),
      'tableReady' => true,
    ]);
  }

  public function updateCommentStatus(Request $request, CommentEntry $commentEntry): RedirectResponse
  {
    $this->abortUnlessCommentAccess($request, $commentEntry);

    $validated = $request->validate([
      'status' => ['required', Rule::in(CommentEntry::statuses())],
    ]);

    $commentEntry->update([
      'status' => $validated['status'],
      'approved_at' => $validated['status'] === 'approved' ? now() : null,
      'approved_by_user_id' => $validated['status'] === 'approved' ? $request->user()?->id : null,
    ]);

    return redirect()->back()->with('status', $this->adminText('comment_status_updated'));
  }

  public function destroyComment(Request $request, CommentEntry $commentEntry): RedirectResponse
  {
    $this->abortUnlessCommentAccess($request, $commentEntry);
    $commentEntry->delete();

    return redirect()->route('admin.engagement.comments.index')->with('status', $this->adminText('comment_deleted'));
  }

  private function adminText(string $key): string
  {
    return $this->translator->admin('engagement.'.$key, $this->localeResolver->locale());
  }

  private function scopeCommentsForUser(Builder $query, User $user): Builder
  {
    if ($user->isSuperAdmin()) {
      return $query;
    }

    return $query->whereIn('site_id', $user->accessibleSiteIds());
  }

  private function scopeRatingsForUser(Builder $query, User $user): Builder
  {
    if ($user->isSuperAdmin()) {
      return $query;
    }

    return $query->whereIn('site_id', $user->accessibleSiteIds());
  }

  private function abortUnlessCommentAccess(Request $request, CommentEntry $commentEntry): void
  {
    $user = $request->user();

    abort_unless($user?->isSuperAdmin() || ($commentEntry->site_id && $user?->hasSiteAccess($commentEntry->site_id)), 403);
  }
}
