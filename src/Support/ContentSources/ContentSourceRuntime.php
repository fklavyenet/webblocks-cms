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
      return app($policyClass)->allows($context);
    } catch (Throwable $exception) {
      report($exception);

      return false;
    }
  }

  public function remember(ContentSourceDefinition $source, ContentSourceContext $context, array $arguments, callable $resolver): mixed
  {
    if ($source->cacheSeconds() === 0 || $context->preview) {
      return $resolver();
    }

    $version = (int) Cache::get($this->versionKey($source->handle()), 1);
    $key = 'webblocks-cms:content-source:'.hash('sha256', serialize([
      $source->handle(), $version, $context->site?->getKey(), $context->page?->getKey(), $context->locale, $arguments,
    ]));

    return Cache::remember($key, $source->cacheSeconds(), $resolver);
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
