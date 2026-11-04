<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;

class SystemUpdateAttention
{
  public function rows(): Collection
  {
    return app(UnfinishedSystemUpdates::class)->records()->map(function (array $item): array {
      $row = $item['record'];
      $item['liveness'] = app(SystemUpdateLiveness::class)->inspect($row);
      $item['from_version'] = $row->from_version ?? $row->expected_current_version;
      $item['target_version'] = $row->to_version ?? $row->target_version;
      $item['chain_steps'] = $row instanceof AdminUpdateOperation && $row->chain_protocol === 1 && Schema::hasTable('wbcms_update_chain_steps')
        ? UpdateChainStep::where('operation_id', $row->id)->orderBy('position')->get() : collect();
      $item['audits'] = Schema::hasTable('wbcms_update_reconciliations')
        ? DB::table('wbcms_update_reconciliations')->where('record_type', $item['kind'])->where('record_id', (string) $row->getKey())->orderByDesc('id')->get()
        : collect();

      return $item;
    });
  }
}
