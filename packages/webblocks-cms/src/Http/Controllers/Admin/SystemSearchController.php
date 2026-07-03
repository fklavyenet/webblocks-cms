<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\Search\PublicSearchSchema;

class SystemSearchController extends Controller
{
  public function __construct(
    private readonly PublicSearchSchema $schema,
    private readonly PublicSearchIndexer $indexer,
  ) {}

  public function index(): View
  {
    $ready = $this->schema->tableExists();
    $indexTable = (new PublicSearchIndex)->getTable();
    $siteTable = (new Site)->getTable();
    $localeTable = (new Locale)->getTable();

    return view('webblocks-cms::admin.system.search', [
      'searchIndexReady' => $ready,
      'totalRows' => $ready ? PublicSearchIndex::query()->count() : 0,
      'lastIndexedAt' => $ready ? PublicSearchIndex::query()->max('indexed_at') : null,
      'rowsBySite' => $ready
        ? PublicSearchIndex::query()
          ->select($siteTable.'.name', $siteTable.'.domain', $siteTable.'.handle', DB::raw('count(*) as total'))
          ->join($siteTable, $siteTable.'.id', '=', $indexTable.'.site_id')
          ->groupBy($siteTable.'.id', $siteTable.'.name', $siteTable.'.domain', $siteTable.'.handle')
          ->orderBy($siteTable.'.name')
          ->get()
        : collect(),
      'rowsByLocale' => $ready
        ? PublicSearchIndex::query()
          ->select($localeTable.'.name', $localeTable.'.code', DB::raw('count(*) as total'))
          ->join($localeTable, $localeTable.'.id', '=', $indexTable.'.locale_id')
          ->groupBy($localeTable.'.id', $localeTable.'.name', $localeTable.'.code')
          ->orderBy($localeTable.'.name')
          ->get()
        : collect(),
    ]);
  }

  public function rebuild(): RedirectResponse
  {
    $result = $this->indexer->rebuild();

    return redirect()
      ->route('admin.system.search.index')
      ->with('status', 'Search index rebuilt successfully. Indexed '.$result->indexed.' row(s) and skipped '.$result->skipped.' page or locale scope(s).');
  }
}
