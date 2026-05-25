<?php

namespace WebBlocks\Cms\Support\Sites;

use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;

class SiteDomainManager
{
  public function __construct(
    private readonly DatabaseManager $db,
    private readonly SiteDomainNormalizer $normalizer,
  ) {}

  public function addDomain(Site $site, string $domain, bool $isPrimary = false, bool $redirectToPrimary = false, string $status = SiteDomain::STATUS_ACTIVE): SiteDomain
  {
    $normalizedDomain = $this->normalizer->normalize($domain);

    if ($normalizedDomain === null) {
      throw ValidationException::withMessages([
        'domain' => 'Enter a valid host without protocol or path.',
      ]);
    }

    return $this->db->transaction(function () use ($site, $normalizedDomain, $isPrimary, $redirectToPrimary, $status): SiteDomain {
      $existing = SiteDomain::query()->where('domain', $normalizedDomain)->first();

      if ($existing && (int) $existing->site_id !== (int) $site->id) {
        throw ValidationException::withMessages([
          'domain' => 'That domain is already assigned to another site.',
        ]);
      }

      $siteDomain = $existing
              ? tap($existing)->update([
                  'redirect_to_primary' => $redirectToPrimary,
                  'status' => $status,
              ])
              : $site->siteDomains()->create([
                  'domain' => $normalizedDomain,
                  'is_primary' => false,
                  'redirect_to_primary' => $redirectToPrimary,
                  'status' => $status,
              ]);

      if ($isPrimary || $site->primaryDomain() === null) {
        $site->markDomainAsPrimary($siteDomain->fresh());

        return $siteDomain->fresh();
      }

      $site->unsetRelation('siteDomains');

      return $siteDomain->fresh();
    });
  }

  public function updateDomain(Site $site, SiteDomain $siteDomain, array $attributes): SiteDomain
  {
    $this->assertBelongsToSite($site, $siteDomain);

    return $this->db->transaction(function () use ($site, $siteDomain, $attributes): SiteDomain {
      $updated = $siteDomain->fresh();

      $domain = array_key_exists('domain', $attributes)
              ? $this->normalizer->normalize($attributes['domain'])
              : $updated->domain;

      if ($domain === null) {
        throw ValidationException::withMessages([
          'domain' => 'Enter a valid host without protocol or path.',
        ]);
      }

      $conflict = SiteDomain::query()
        ->where('domain', $domain)
        ->whereKeyNot($updated->id)
        ->first();

      if ($conflict) {
        throw ValidationException::withMessages([
          'domain' => 'That domain is already assigned to another site.',
        ]);
      }

      $updated->update([
        'domain' => $domain,
        'redirect_to_primary' => (bool) ($attributes['redirect_to_primary'] ?? $updated->redirect_to_primary),
        'status' => $attributes['status'] ?? $updated->status,
      ]);

      if ((bool) ($attributes['is_primary'] ?? false)) {
        $site->markDomainAsPrimary($updated->fresh());

        return $updated->fresh();
      }

      if ($updated->is_primary && $updated->status !== SiteDomain::STATUS_ACTIVE) {
        throw ValidationException::withMessages([
          'status' => 'The primary domain must stay active. Choose another primary domain first.',
        ]);
      }

      $site->unsetRelation('siteDomains');

      return $updated->fresh();
    });
  }

  public function deleteDomain(Site $site, SiteDomain $siteDomain): void
  {
    $this->assertBelongsToSite($site, $siteDomain);

    $this->db->transaction(function () use ($site, $siteDomain): void {
      if ($siteDomain->is_primary) {
        $replacement = $site->siteDomains()
          ->whereKeyNot($siteDomain->id)
          ->active()
          ->orderBy('domain')
          ->first();

        if (! $replacement) {
          throw ValidationException::withMessages([
            'domain' => 'A site must keep at least one primary active domain once domains are assigned. Add another domain first.',
          ]);
        }

        $siteDomain->delete();
        $site->markDomainAsPrimary($replacement->fresh());

        return;
      }

      $siteDomain->delete();
      $site->unsetRelation('siteDomains');
    });
  }

  public function setPrimaryDomain(Site $site, SiteDomain $siteDomain): SiteDomain
  {
    $this->assertBelongsToSite($site, $siteDomain);

    $this->db->transaction(function () use ($site, $siteDomain): void {
      $site->markDomainAsPrimary($siteDomain->fresh());
    });

    return $siteDomain->fresh();
  }

  private function assertBelongsToSite(Site $site, SiteDomain $siteDomain): void
  {
    if ((int) $siteDomain->site_id !== (int) $site->id) {
      abort(404);
    }
  }
}
