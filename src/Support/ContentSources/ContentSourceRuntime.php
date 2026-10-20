<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Support\Facades\Cache;
use Throwable;

class ContentSourceRuntime
{
  public function allows(ContentSourceDefinition $source, ContentSourceContext $context): bool
  {
    $policyClass = $source->accessPolicyClass();

    if ($policyClass === null) {
      return true;
    }

    try {
      $allowed = app($policyClass)->allows($context);
      if (! $allowed) {
        $this->previewIssue('denied');
      }

      return $allowed;
    } catch (Throwable $exception) {
      report($exception);
      $this->previewIssue('unavailable');

      return false;
    }
  }

  public function remember(ContentSourceDefinition $source, ContentSourceContext $context, array $arguments, callable $resolver): mixed
  {
    if ($context->preview && request()->attributes->get('cms_editor_preview')) {
      // Only this explicit render request: never cache private drafts between requests/users.
      $key = hash('sha256', serialize([$source->handle(), $context->site?->id, $context->page?->id, $context->locale, $arguments]));
      $memo = request()->attributes->get('cms_editor_preview_memo', []);
      if (! array_key_exists($key, $memo)) {
        if (count($memo) >= 64) {
          $this->previewIssue('limit');
          throw new \UnexpectedValueException('Preview resolution limit exceeded.');
        }
        // Reserve before invoking providers, including nested resolutions.
        $memo[$key] = new \UnexpectedValueException('Recursive preview resolution.');
        request()->attributes->set('cms_editor_preview_memo', $memo);
        try {
          $result = $resolver();
        } catch (Throwable $exception) {
          $this->previewIssue('unavailable');
          $result = $exception;
        }
        $memo = request()->attributes->get('cms_editor_preview_memo', []);
        $memo[$key] = $result;
        request()->attributes->set('cms_editor_preview_memo', $memo);
      }
      if ($memo[$key] instanceof Throwable) {
        throw $memo[$key];
      }

      return $memo[$key];
    }
    if ($source->cacheSeconds() === 0 || $context->preview) {
      return $resolver();
    }

    $version = (int) Cache::get($this->versionKey($source->handle()), 1);
    $key = 'webblocks-cms:content-source:'.hash('sha256', serialize([
      $source->handle(), $version, $context->site?->getKey(), $context->page?->getKey(), $context->locale, $arguments,
    ]));

    return Cache::remember($key, $source->cacheSeconds(), $resolver);
  }

  public function previewIssue(string $issue): void
  {
    if (request()->attributes->get('cms_editor_preview')) {
      $issues = request()->attributes->get('cms_editor_preview_issues', []);
      $issues[] = $issue;
      request()->attributes->set('cms_editor_preview_issues', array_values(array_unique($issues)));
    }
  }

  public function invalidate(ContentSourceDefinition|string $source): void
  {
    $handle = $source instanceof ContentSourceDefinition ? $source->handle() : trim($source);

    if ($handle !== '') {
      $key = $this->versionKey($handle);
      Cache::forever($key, (int) Cache::get($key, 1) + 1);
    }
  }

  private function versionKey(string $handle): string
  {
    return 'webblocks-cms:content-source-version:'.hash('sha256', $handle);
  }
}
