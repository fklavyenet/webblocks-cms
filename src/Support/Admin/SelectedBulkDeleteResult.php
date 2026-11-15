<?php

namespace WebBlocks\Cms\Support\Admin;

use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class SelectedBulkDeleteResult
{
  public function __construct(
  private readonly string $singularLabel,
  private readonly string $pluralLabel,
  private readonly array $deletedIds,
  private readonly array $failed,
  ) {}

  public function deletedCount(): int
  {
  return count($this->deletedIds);
  }

  public function failedCount(): int
  {
  return count($this->failed);
  }

  public function hasFailures(): bool
  {
  return $this->failedCount() > 0;
  }

  public function message(): string
  {
  if ($this->deletedCount() === 0 && $this->hasFailures()) {
      return __('webblocks-cms::admin-feedback.bulk.none_deleted', ['entity' => $this->entity($this->pluralLabel)]);
  }

  $message = __('webblocks-cms::admin-feedback.bulk.deleted', [
      'count' => $this->deletedCount(),
      'entity' => $this->entity($this->deletedCount() === 1 ? $this->singularLabel : $this->pluralLabel),
  ]);

  return $this->hasFailures()
      ? $message.' '.__('webblocks-cms::admin-feedback.bulk.failed_count', ['count' => $this->failedCount()])
      : $message;
  }

  public function failureMessages(): array
  {
  return array_values(array_map(
      fn (array $failure): string => __('webblocks-cms::admin-feedback.bulk.failure', [
          'entity' => Str::ucfirst($this->entity($this->singularLabel)),
          'id' => $failure['id'],
          'reason' => $failure['message'],
      ]),
      $this->failed,
  ));
  }

  private function entity(string $label): string
  {
  return app(CmsTranslator::class)->getOrDefault('admin-feedback.bulk_entities.'.str_replace(' ', '_', $label), $label, app()->getLocale());
  }
}
