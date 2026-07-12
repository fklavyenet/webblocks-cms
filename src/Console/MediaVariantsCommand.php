<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Media\MediaTransformService;

class MediaVariantsCommand extends Command
{
  protected $signature = 'webblocks:media-variants:regenerate
    {--media= : Regenerate one Media id}
    {--prune : Safely remove obsolete fingerprint directories}';

  protected $description = 'Regenerate or prune WebBlocks CMS image variants';

  public function __construct(private readonly MediaTransformService $transforms)
  {
    parent::__construct();
  }

  public function handle(): int
  {
    $counts = ['generated' => 0, 'reused' => 0, 'skipped' => 0, 'fallback' => 0, 'failed' => 0, 'pruned' => 0];
    $query = Media::query()->where('kind', Media::KIND_IMAGE)->orderBy('id');

    if ($this->option('media')) {
      $query->whereKey((int) $this->option('media'));
    }

    if (! $query->exists()) {
      $this->error('No eligible image media matched the request.');

      return self::FAILURE;
    }

    $query->chunkById(50, function ($media) use (&$counts): void {
      foreach ($media as $item) {
        if ($this->option('prune')) {
          $counts['pruned'] += $this->transforms->prune($item);

          continue;
        }

        foreach ($this->transforms->regenerate($item) as $key => $value) {
          $counts[$key] += $value;
        }
      }
    });

    $this->line(collect($counts)->map(fn ($value, $key) => $key.'='.$value)->implode(' '));

    return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
  }
}
