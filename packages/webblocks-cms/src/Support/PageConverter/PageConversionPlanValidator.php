<?php

namespace WebBlocks\Cms\Support\PageConverter;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageConversionPlanValidator
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly PageLayoutManager $pageLayouts,
  ) {}

  /**
   * @return array<string, string>
   */
  public function validate(array $plan, User $user): array
  {
    $errors = [];
    $target = $plan['target'] ?? null;
    $blocks = $plan['blocks'] ?? null;

    if (($plan['version'] ?? null) !== PageConversionPlanSerializer::VERSION) {
      $errors['plan_payload'] = 'The submitted conversion plan version is not supported.';
    }

    if (! is_array($target)) {
      $errors['plan_payload'] = 'The submitted conversion plan is missing target details.';

      return $errors;
    }

    $siteId = (int) ($target['site_id'] ?? 0);
    $localeId = (int) ($target['locale_id'] ?? 0);
    $pageLayout = Page::normalizePublicShellHandle((string) ($target['page_layout'] ?? ''));
    $pageTitle = trim((string) ($target['page_title'] ?? ''));
    $pagePath = trim((string) ($target['page_path'] ?? ''));
    $conversionProfile = (string) ($target['conversion_profile'] ?? '');
    $site = $siteId > 0 ? Site::query()->find($siteId) : null;

    if (! $site) {
      $errors['plan_payload'] = 'The selected target site is no longer available.';
    } else {
      try {
        $this->authorization->abortUnlessSiteAccess($user, $site);
      } catch (HttpException) {
        $errors['plan_payload'] = 'You do not have permission to create drafts for the selected site.';
      }

      if ($localeId < 1 || ! $site->hasEnabledLocale($localeId)) {
        $errors['plan_payload'] = 'Choose an enabled locale for the selected site before creating a draft.';
      }
    }

    if ($pageLayout === '' || ! in_array($pageLayout, $this->pageLayouts->activeHandles(), true)) {
      $errors['plan_payload'] = 'The selected page layout is no longer active or usable.';
    }

    if ($pageTitle === '' || mb_strlen($pageTitle) > 255) {
      $errors['plan_payload'] = 'The submitted conversion plan has an invalid page title.';
    }

    if (! $this->validPath($pagePath)) {
      $errors['plan_payload'] = 'The submitted conversion plan has an invalid page path.';
    } elseif ($site && $localeId > 0 && $this->pathExists($siteId, $localeId, $pagePath)) {
      $errors['plan_payload'] = 'A page already exists at the selected path for this site and locale.';
    }

    if (! in_array($conversionProfile, PageConverterProfile::values(), true)) {
      $errors['plan_payload'] = 'The submitted conversion plan has an invalid conversion profile.';
    }

    if (! is_array($blocks)) {
      $errors['plan_payload'] = 'The submitted conversion plan is missing block suggestions.';
    } else {
      $this->validateBlocks($blocks, $errors);
    }

    return $errors;
  }

  public function validPath(string $path): bool
  {
    if ($path === '' || mb_strlen($path) > 255) {
      return false;
    }

    if (preg_match('/^\/?[A-Za-z0-9][A-Za-z0-9\/_-]*$/', $path) !== 1) {
      return false;
    }

    return ! str_contains($path, '..')
      && ! str_contains($path, '//')
      && ! str_contains($path, '\\')
      && ! str_contains($path, ':');
  }

  /**
   * @param  array<int, mixed>  $blocks
   * @param  array<string, string>  $errors
   */
  private function validateBlocks(array $blocks, array &$errors): void
  {
    $keys = [];

    foreach (array_values($blocks) as $index => $block) {
      if (! is_array($block)) {
        $errors['plan_payload'] = 'The submitted conversion plan has an invalid block suggestion.';

        return;
      }

      $key = (string) ($block['key'] ?? '');
      $order = (int) ($block['order'] ?? 0);
      $slug = (string) ($block['block_slug'] ?? $block['block_type'] ?? '');
      $confidence = (int) ($block['confidence'] ?? -1);

      if ($key === '' || isset($keys[$key])) {
        $errors['plan_payload'] = 'The submitted conversion plan has duplicate or missing block keys.';

        return;
      }

      $keys[$key] = true;

      if ($order !== $index + 1 || $slug === '' || $confidence < 0 || $confidence > 100) {
        $errors['plan_payload'] = 'The submitted conversion plan has an invalid block suggestion.';

        return;
      }

      if (! $this->blockSlugIsUsable($slug)) {
        $errors['plan_payload'] = 'The submitted conversion plan references an unavailable block type ['.$slug.'].';

        return;
      }

      foreach (['translated_fields', 'shared_fields', 'warnings', 'fallback_flags', 'source_fragment'] as $field) {
        if (! array_key_exists($field, $block) || ! is_array($block[$field])) {
          $errors['plan_payload'] = 'The submitted conversion plan has incomplete block suggestion data.';

          return;
        }
      }

      if ($slug === 'accordion_item' && ! $this->validAccordionItemPayload($block)) {
        $errors['plan_payload'] = 'The submitted conversion plan has invalid accordion item data.';

        return;
      }
    }

    $blocksByKey = collect($blocks)
      ->filter(fn ($block): bool => is_array($block))
      ->keyBy(fn (array $block): string => (string) ($block['key'] ?? ''));

    foreach (array_values($blocks) as $block) {
      $parentKey = $block['parent_key'] ?? null;

      if ($parentKey !== null && ! isset($keys[(string) $parentKey])) {
        $errors['plan_payload'] = 'The submitted conversion plan references a missing parent block.';

        return;
      }

      if (($block['block_slug'] ?? $block['block_type'] ?? null) === 'accordion_item') {
        $parent = $blocksByKey->get((string) $parentKey);

        if (! is_array($parent) || (string) ($parent['block_slug'] ?? $parent['block_type'] ?? '') !== 'accordion') {
          $errors['plan_payload'] = 'The submitted conversion plan has invalid accordion item parent data.';

          return;
        }
      }
    }
  }

  private function pathExists(int $siteId, int $localeId, string $path): bool
  {
    $slug = trim($path, '/');
    $publicPath = PageTranslation::pathFromSlug($slug);

    return PageTranslation::query()
      ->where('site_id', $siteId)
      ->where('locale_id', $localeId)
      ->where(fn ($query) => $query
        ->where('slug', $slug)
        ->orWhere('path', $publicPath))
      ->exists();
  }

  private function blockSlugIsUsable(string $slug): bool
  {
    if (in_array($slug, ['accordion', 'accordion_item'], true)) {
      return true;
    }

    if (in_array($slug, PageConversionDraftCreator::supportedBlockSlugs(), true)) {
      $createdSlug = match ($slug) {
        'list' => 'rich-text',
        'callout' => 'alert',
        'accordion_item' => 'faq',
        default => $slug,
      };

      return BlockType::query()
        ->where('slug', $createdSlug)
        ->where('status', 'published')
        ->exists();
    }

    if (in_array($slug, PageConversionDraftCreator::skippedBlockSlugs(), true)) {
      $blockType = BlockType::query()->where('slug', $slug)->first();

      return ! $blockType || $blockType->status === 'published';
    }

    return false;
  }

  private function validAccordionItemPayload(array $block): bool
  {
    if (($block['parent_key'] ?? null) === null || trim((string) $block['parent_key']) === '') {
      return false;
    }

    $translated = is_array($block['translated_fields'] ?? null) ? $block['translated_fields'] : [];
    $title = trim((string) ($translated['title'] ?? $translated['label'] ?? ''));
    $content = trim((string) ($translated['content'] ?? $translated['body'] ?? ''));

    return $title !== '' && $content !== '';
  }
}
