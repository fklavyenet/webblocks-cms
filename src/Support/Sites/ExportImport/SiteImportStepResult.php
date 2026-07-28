<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use WebBlocks\Cms\Models\SiteImport;

/**
 * What one step of a chunked import did, in the shape the progress modal polls.
 */
class SiteImportStepResult
{
  /**
   * @param  list<string>  $log  Lines this step appended, not the whole history.
   */
  public function __construct(
    public readonly string $status,
    public readonly ?string $phase,
    public readonly int $done,
    public readonly int $total,
    public readonly array $log = [],
    public readonly ?string $failureMessage = null,
  ) {}

  public static function fromImport(SiteImport $siteImport, array $log = []): self
  {
    return new self(
      $siteImport->status,
      $siteImport->resume_phase,
      $siteImport->progress_done,
      $siteImport->progress_total,
      $log,
      $siteImport->failure_message,
    );
  }

  public function isFinished(): bool
  {
    return $this->status === SiteImport::STATUS_COMPLETED;
  }

  public function isFailed(): bool
  {
    return $this->status === SiteImport::STATUS_FAILED;
  }

  public function percent(): int
  {
    if ($this->total < 1) {
      return $this->isFinished() ? 100 : 0;
    }

    return (int) min(100, floor(($this->done / $this->total) * 100));
  }

  /**
   * @return array<string, mixed>
   */
  public function toArray(): array
  {
    return [
      'status' => $this->status,
      'phase' => $this->phase,
      'done' => $this->done,
      'total' => $this->total,
      'percent' => $this->percent(),
      'finished' => $this->isFinished(),
      'failed' => $this->isFailed(),
      'failure_message' => $this->failureMessage,
      'log' => $this->log,
    ];
  }
}
