<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SitePromotionApplyRequest;
use App\Http\Requests\Admin\SitePromotionDryRunRequest;
use App\Models\Site;
use App\Support\SitePromotion\SitePromotionApplier;
use App\Support\SitePromotion\SitePromotionOptions;
use App\Support\SitePromotion\SitePromotionPackageInspector;
use App\Support\SitePromotion\SitePromotionPlanner;
use App\Support\SitePromotion\SitePromotionPlanStore;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class SitePromotionController extends Controller
{
    public function __construct(
        private readonly SitePromotionPackageInspector $packageInspector,
        private readonly SitePromotionPlanner $planner,
        private readonly SitePromotionPlanStore $planStore,
        private readonly SitePromotionApplier $applier,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function index(): View
    {
        $this->authorization->abortUnlessSystem(request()->user());
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        $plan = $this->planStore->load((string) request()->query('plan'));
        $preselectedTargetSiteId = $this->resolvePreselectedTargetSiteId();

        return view('admin.sites.promote', [
            'sites' => Site::query()->primaryFirst()->orderBy('name')->get(),
            'plan' => $plan,
            'preselectedTargetSiteId' => $preselectedTargetSiteId,
            'storedPackages' => collect(Storage::disk(SitePromotionPackageInspector::DISK)->allFiles('uploads'))
                ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
                ->sort()
                ->values(),
        ]);
    }

    public function dryRun(SitePromotionDryRunRequest $request): RedirectResponse
    {
        try {
            $archivePath = $request->hasFile('archive')
                ? $this->packageInspector->inspectUpload($request->file('archive'))->archivePath
                : (string) $request->string('archive_path');

            $plan = $this->planner->plan($archivePath, SitePromotionOptions::fromArray($request->validated()));

            return redirect()
                ->route('admin.sites.promote', ['plan' => $plan->token])
                ->with('status', 'Site Promotion dry run completed. Review the plan before apply.');
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['site_promotion' => $exception->getMessage()]);
        }
    }

    public function apply(SitePromotionApplyRequest $request): RedirectResponse
    {
        try {
            $result = $this->applier->apply((string) $request->string('plan_token'), $request->user()?->id);

            return redirect()
                ->route('admin.sites.promote', ['plan' => $result->plan->token])
                ->with('status', 'Site Promotion applied successfully. Safety backup #'.($result->safetyBackup?->id ?? '-').' created and search rebuilt for '.$result->targetSite->handle.'.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['site_promotion' => $exception->getMessage()]);
        }
    }

    private function resolvePreselectedTargetSiteId(): ?int
    {
        $targetSiteId = (int) request()->integer('target_site_id');

        if ($targetSiteId < 1) {
            return null;
        }

        $allowedSite = $this->authorization
            ->scopeSitesForUser(Site::query(), request()->user())
            ->whereKey($targetSiteId)
            ->value('id');

        return $allowedSite ? (int) $allowedSite : null;
    }
}
