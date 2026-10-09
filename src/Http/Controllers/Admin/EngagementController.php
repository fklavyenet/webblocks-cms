<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use WebBlocks\Cms\Actions\Engagement\DeleteComment;
use WebBlocks\Cms\Actions\Engagement\ModerateComments;
use WebBlocks\Cms\Http\Requests\Admin\EngagementIndexRequest;
use WebBlocks\Cms\Http\Requests\Admin\ModerateCommentsRequest;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Policies\EngagementPolicy;
use WebBlocks\Cms\Queries\EngagementQuery;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class EngagementController extends Controller
{
  public function __construct(private readonly EngagementQuery $query, private readonly AdminLocaleResolver $localeResolver, private readonly CmsTranslator $translator) {}

  public function index(EngagementIndexRequest $request): View
  {
    $filters = array_intersect_key($request->filters(), array_flip(['site', 'sort']));
    $pages = $this->query->pageSummary($request->user(), $filters);
    AdminPagination::redirectOutOfRange($pages, $request);

    return view('webblocks-cms::admin.engagement.index', $this->query->overview($request->user(), $filters) + $this->options($request, $filters) + ['pageSummary' => $pages]);
  }

  public function comments(EngagementIndexRequest $request): View
  {
    $filters = $request->filters();
    $ready = Schema::hasTable('wbcms_comment_entries');
    $query = $ready ? $this->query->comments($request->user(), $filters) : null;
    $comments = $query ? (clone $query)->with(['site', 'page.site', 'page.translations', 'block.blockType'])->latest()->orderByDesc('id')->paginate(AdminPagination::perPage())->withQueryString() : $this->emptyPaginator();
    AdminPagination::redirectOutOfRange($comments, $request);

    return view('webblocks-cms::admin.engagement.comments', $this->options($request, $filters) + [
      'comments' => $comments, 'tableReady' => $ready, 'statuses' => CommentEntry::statuses(),
      'totalCount' => $ready ? $this->query->comments($request->user())->count() : 0, 'filteredCount' => $comments->total(),
      'commentStatuses' => $ready ? $this->query->comments($request->user(), array_diff_key($filters, ['status' => true]))->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all() : [],
    ]);
  }

  public function ratings(EngagementIndexRequest $request): View
  {
    $filters = $request->filters();
    $ready = Schema::hasTable('wbcms_content_ratings');
    $query = $ready ? $this->query->ratings($request->user(), $filters) : null;
    $ratings = $query ? (clone $query)->with(['site', 'page.site', 'page.translations', 'block.blockType'])->latest()->orderByDesc('id')->paginate(AdminPagination::perPage())->withQueryString() : $this->emptyPaginator();
    AdminPagination::redirectOutOfRange($ratings, $request);

    return view('webblocks-cms::admin.engagement.ratings', array_replace($this->query->overview($request->user(), $filters), $this->options($request, $filters), [
      'ratings' => $ratings, 'tableReady' => $ready, 'ratingOptions' => $ready ? range(1, max(1, min(255, (int) $this->query->ratings($request->user())->max('rating_max') ?: 5))) : [],
      'totalCount' => $ready ? $this->query->ratings($request->user())->count() : 0, 'filteredCount' => $ratings->total(),
    ]));
  }

  public function showComment(Request $request, CommentEntry $commentEntry): View
  {
    abort_unless(app(EngagementPolicy::class)->update($request->user(), $commentEntry), 403);

    return view('webblocks-cms::admin.engagement.comment', ['comment' => $commentEntry->load(['site', 'page.translations', 'block']), 'returnQuery' => http_build_query(array_intersect_key($request->query(), array_flip(['search', 'site', 'page_id', 'status', 'from', 'until', 'page', 'sort'])))]);
  }

  public function updateCommentStatus(ModerateCommentsRequest $request, CommentEntry $commentEntry): RedirectResponse
  {
    return $this->moderate($request, [$commentEntry->id]);
  }

  public function bulkCommentStatus(ModerateCommentsRequest $request): RedirectResponse
  {
    return $this->moderate($request, $request->validated('comment_ids'));
  }

  public function destroyComment(Request $request, CommentEntry $commentEntry): RedirectResponse
  {
    app(DeleteComment::class)->execute($request->user(), $commentEntry);

    return redirect()->route('admin.engagement.comments.index')->with('status', $this->adminText('comment_deleted'));
  }

  private function moderate(ModerateCommentsRequest $request, array $ids): RedirectResponse
  {
    app(ModerateComments::class)->execute($request->user(), $ids, $request->validated('status'));

    return redirect()->route('admin.engagement.comments.index', $request->returnFilters())->with('status', $this->adminText('comment_status_updated'));
  }

  private function options(EngagementIndexRequest $request, array $filters): array
  {
    return ['filters' => $filters, 'sites' => $this->query->sites($request->user()), 'pages' => $this->query->pages($request->user(), $filters)];
  }

  private function emptyPaginator(): LengthAwarePaginator
  {
    return new LengthAwarePaginator([], 0, AdminPagination::perPage());
  }

  private function adminText(string $key): string
  {
    return $this->translator->admin('engagement.'.$key, $this->localeResolver->locale());
  }
}
