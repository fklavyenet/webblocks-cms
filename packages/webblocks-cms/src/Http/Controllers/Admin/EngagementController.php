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

class EngagementController extends Controller
{
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
            ->orWhereHas('page', fn (Builder $pageQuery) => $pageQuery->where('title', 'like', "%{$search}%"));
        });
      })
      ->when($status !== '', fn (Builder $query) => $query->where('status', $status));

    return view('webblocks-cms::admin.engagement.comments', [
      'comments' => $filteredQuery
        ->with(['page.site', 'block.blockType'])
        ->latest()
        ->paginate(AdminPagination::perPage())
        ->withQueryString(),
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
        'totalCount' => 0,
        'tableReady' => false,
      ]);
    }

    $baseQuery = $this->scopeRatingsForUser(ContentRating::query(), $request->user());
    $ratings = $this->scopeRatingsForUser(ContentRating::query(), $request->user())
      ->with(['page.site', 'block.blockType'])
      ->latest()
      ->paginate(AdminPagination::perPage())
      ->withQueryString();

    return view('webblocks-cms::admin.engagement.ratings', [
      'ratings' => $ratings,
      'totalCount' => (clone $baseQuery)->count(),
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

    return redirect()->back()->with('status', 'Comment status updated.');
  }

  public function destroyComment(Request $request, CommentEntry $commentEntry): RedirectResponse
  {
    $this->abortUnlessCommentAccess($request, $commentEntry);
    $commentEntry->delete();

    return redirect()->route('admin.engagement.comments.index')->with('status', 'Comment deleted.');
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
