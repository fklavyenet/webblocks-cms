<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicSearchIndex;
use App\Support\Search\PublicSearchIndexer;
use App\Support\Search\PublicSearchSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSearchController extends Controller
{
    public function __construct(
        private readonly PublicSearchSchema $schema,
        private readonly PublicSearchIndexer $indexer,
    ) {}

    public function index(): View
    {
        $ready = $this->schema->tableExists();

        return view('admin.system.search', [
            'searchIndexReady' => $ready,
            'totalRows' => $ready ? PublicSearchIndex::query()->count() : 0,
            'lastIndexedAt' => $ready ? PublicSearchIndex::query()->max('indexed_at') : null,
            'rowsBySite' => $ready
                ? PublicSearchIndex::query()
                    ->select('sites.name', 'sites.handle', DB::raw('count(*) as total'))
                    ->join('sites', 'sites.id', '=', 'public_search_index.site_id')
                    ->groupBy('sites.id', 'sites.name', 'sites.handle')
                    ->orderBy('sites.name')
                    ->get()
                : collect(),
            'rowsByLocale' => $ready
                ? PublicSearchIndex::query()
                    ->select('locales.name', 'locales.code', DB::raw('count(*) as total'))
                    ->join('locales', 'locales.id', '=', 'public_search_index.locale_id')
                    ->groupBy('locales.id', 'locales.name', 'locales.code')
                    ->orderBy('locales.name')
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
