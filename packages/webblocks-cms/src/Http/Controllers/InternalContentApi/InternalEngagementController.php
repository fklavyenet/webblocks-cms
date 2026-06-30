<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContentRating;

class InternalEngagementController extends Controller
{
  public function comments(Request $request): JsonResponse
  {
    if (! Schema::hasTable('comment_entries')) {
      return $this->ok([
        'table_ready' => false,
        'comments' => [],
        'pagination' => $this->pagination(new LengthAwarePaginator([], 0, 25)),
        'summary' => [
          'total' => 0,
          'by_status' => [],
        ],
      ]);
    }

    $filters = $this->commentFilters($request);
    $query = CommentEntry::query()
      ->with(['site', 'page', 'block.blockType'])
      ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
      ->when($filters['site_id'] !== null, fn (Builder $query) => $query->where('site_id', $filters['site_id']))
      ->when($filters['page_id'] !== null, fn (Builder $query) => $query->where('page_id', $filters['page_id']))
      ->when($filters['block_id'] !== null, fn (Builder $query) => $query->where('block_id', $filters['block_id']))
      ->when($filters['search'] !== null, function (Builder $query) use ($filters): void {
        $query->where(function (Builder $inner) use ($filters): void {
          $inner->where('body', 'like', '%'.$filters['search'].'%')
            ->orWhere('author_name', 'like', '%'.$filters['search'].'%');
        });
      });

    $comments = (clone $query)
      ->latest()
      ->paginate($filters['per_page'])
      ->withQueryString();

    return $this->ok([
      'table_ready' => true,
      'filters' => $filters,
      'comments' => $comments->getCollection()
        ->map(fn (CommentEntry $comment): array => $this->comment($comment))
        ->values(),
      'pagination' => $this->pagination($comments),
      'summary' => [
        'total' => (clone $query)->count(),
        'by_status' => $this->commentStatusCounts($filters),
      ],
    ]);
  }

  public function updateCommentStatus(Request $request, int $commentEntry): JsonResponse
  {
    if (! Schema::hasTable('comment_entries')) {
      return response()->json([
        'ok' => false,
        'code' => 'engagement_schema_not_ready',
        'message' => 'Engagement comment tables are not ready. Run System Updates before moderating comments.',
      ], 503);
    }

    $validated = $request->validate([
      'status' => ['required', Rule::in(CommentEntry::statuses())],
    ]);

    $comment = CommentEntry::query()
      ->with(['site', 'page', 'block.blockType'])
      ->findOrFail($commentEntry);

    $comment->update([
      'status' => $validated['status'],
      'approved_at' => $validated['status'] === 'approved' ? now() : null,
      'approved_by_user_id' => null,
    ]);

    $comment->refresh()->load(['site', 'page', 'block.blockType']);

    return $this->ok([
      'comment' => $this->comment($comment),
    ]);
  }

  public function ratings(Request $request): JsonResponse
  {
    if (! Schema::hasTable('content_ratings')) {
      return $this->ok([
        'table_ready' => false,
        'ratings' => [],
        'pagination' => $this->pagination(new LengthAwarePaginator([], 0, 25)),
        'summary' => [
          'total' => 0,
          'average' => null,
          'by_value' => [],
        ],
      ]);
    }

    $filters = $this->ratingFilters($request);
    $query = ContentRating::query()
      ->with(['site', 'page', 'block.blockType'])
      ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
      ->when($filters['site_id'] !== null, fn (Builder $query) => $query->where('site_id', $filters['site_id']))
      ->when($filters['page_id'] !== null, fn (Builder $query) => $query->where('page_id', $filters['page_id']))
      ->when($filters['block_id'] !== null, fn (Builder $query) => $query->where('block_id', $filters['block_id']));

    $ratings = (clone $query)
      ->latest()
      ->paginate($filters['per_page'])
      ->withQueryString();
    $average = (clone $query)->avg('rating_value');

    return $this->ok([
      'table_ready' => true,
      'filters' => $filters,
      'ratings' => $ratings->getCollection()
        ->map(fn (ContentRating $rating): array => $this->rating($rating))
        ->values(),
      'pagination' => $this->pagination($ratings),
      'summary' => [
        'total' => (clone $query)->count(),
        'average' => $average !== null ? round((float) $average, 2) : null,
        'by_value' => $this->ratingValueCounts($filters),
      ],
    ]);
  }

  private function commentFilters(Request $request): array
  {
    $status = $request->query('status');
    $status = is_string($status) && in_array($status, CommentEntry::statuses(), true) ? $status : null;

    return [
      'status' => $status,
      'site_id' => $this->positiveInt($request->query('site_id')),
      'page_id' => $this->positiveInt($request->query('page_id')),
      'block_id' => $this->positiveInt($request->query('block_id')),
      'search' => $this->nullableString($request->query('search'), 120),
      'per_page' => $this->perPage($request),
    ];
  }

  private function ratingFilters(Request $request): array
  {
    $status = $this->nullableString($request->query('status'), 40);

    return [
      'status' => $status,
      'site_id' => $this->positiveInt($request->query('site_id')),
      'page_id' => $this->positiveInt($request->query('page_id')),
      'block_id' => $this->positiveInt($request->query('block_id')),
      'per_page' => $this->perPage($request),
    ];
  }

  private function comment(CommentEntry $comment): array
  {
    return [
      'id' => $comment->id,
      'status' => $comment->status,
      'author_name' => $comment->author_name,
      'body' => $comment->body,
      'spam_score' => $comment->spam_score,
      'spam_reasons' => $comment->spamReasonLabels(),
      'source_url' => $comment->source_url,
      'source_path' => $comment->sourcePath(),
      'site' => $comment->site ? [
        'id' => $comment->site->id,
        'handle' => $comment->site->handle,
        'name' => $comment->site->name,
      ] : null,
      'page' => $comment->page ? [
        'id' => $comment->page->id,
        'title' => $comment->page->title,
      ] : null,
      'block' => $comment->block ? [
        'id' => $comment->block->id,
        'type' => $comment->block->typeSlug(),
      ] : null,
      'approved_at' => $comment->approved_at?->toIso8601String(),
      'created_at' => $comment->created_at?->toIso8601String(),
      'updated_at' => $comment->updated_at?->toIso8601String(),
    ];
  }

  private function rating(ContentRating $rating): array
  {
    return [
      'id' => $rating->id,
      'rating_value' => $rating->rating_value,
      'rating_max' => $rating->rating_max,
      'status' => $rating->status,
      'source_url' => $rating->source_url,
      'source_path' => $this->sourcePath($rating->source_url),
      'site' => $rating->site ? [
        'id' => $rating->site->id,
        'handle' => $rating->site->handle,
        'name' => $rating->site->name,
      ] : null,
      'page' => $rating->page ? [
        'id' => $rating->page->id,
        'title' => $rating->page->title,
      ] : null,
      'block' => $rating->block ? [
        'id' => $rating->block->id,
        'type' => $rating->block->typeSlug(),
      ] : null,
      'created_at' => $rating->created_at?->toIso8601String(),
      'updated_at' => $rating->updated_at?->toIso8601String(),
    ];
  }

  private function commentStatusCounts(array $filters): array
  {
    $query = CommentEntry::query()
      ->when($filters['site_id'] !== null, fn (Builder $query) => $query->where('site_id', $filters['site_id']))
      ->when($filters['page_id'] !== null, fn (Builder $query) => $query->where('page_id', $filters['page_id']))
      ->when($filters['block_id'] !== null, fn (Builder $query) => $query->where('block_id', $filters['block_id']));

    return $query->selectRaw('status, count(*) as aggregate')
      ->groupBy('status')
      ->pluck('aggregate', 'status')
      ->map(fn ($count): int => (int) $count)
      ->all();
  }

  private function ratingValueCounts(array $filters): array
  {
    $query = ContentRating::query()
      ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
      ->when($filters['site_id'] !== null, fn (Builder $query) => $query->where('site_id', $filters['site_id']))
      ->when($filters['page_id'] !== null, fn (Builder $query) => $query->where('page_id', $filters['page_id']))
      ->when($filters['block_id'] !== null, fn (Builder $query) => $query->where('block_id', $filters['block_id']));

    return $query->selectRaw('rating_value, count(*) as aggregate')
      ->groupBy('rating_value')
      ->orderBy('rating_value')
      ->pluck('aggregate', 'rating_value')
      ->map(fn ($count): int => (int) $count)
      ->all();
  }

  private function pagination(LengthAwarePaginator $paginator): array
  {
    return [
      'current_page' => $paginator->currentPage(),
      'per_page' => $paginator->perPage(),
      'total' => $paginator->total(),
      'last_page' => $paginator->lastPage(),
    ];
  }

  private function positiveInt(mixed $value): ?int
  {
    if (! is_numeric($value)) {
      return null;
    }

    $value = (int) $value;

    return $value > 0 ? $value : null;
  }

  private function nullableString(mixed $value, int $maxLength): ?string
  {
    if (! is_string($value)) {
      return null;
    }

    $value = trim($value);

    return $value !== '' ? mb_substr($value, 0, $maxLength) : null;
  }

  private function perPage(Request $request): int
  {
    $perPage = (int) $request->query('per_page', 25);

    return min(max($perPage, 1), 100);
  }

  private function sourcePath(?string $sourceUrl): string
  {
    if (! $sourceUrl) {
      return '-';
    }

    $path = parse_url($sourceUrl, PHP_URL_PATH);

    return is_string($path) && trim($path) !== '' ? $path : '-';
  }

  private function ok(array $payload): JsonResponse
  {
    return response()->json(['ok' => true] + $payload);
  }
}
