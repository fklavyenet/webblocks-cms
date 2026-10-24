<?php

namespace WebBlocks\Cms\Queries;

use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

class SiteDomainQuery
{
  public function tableExists(Site $site): bool
  {
    return app(RequestMemo::class)->remember('site.domains-schema', $this->identity($site),
      fn () => $site->getConnection()->getSchemaBuilder()->hasTable($site->siteDomains()->getRelated()->getTable()));
  }

  public function primary(Site $site): ?SiteDomain
  {
    return $this->read('primary', $site, fn () => $site->siteDomains()->where('is_primary', true)->first());
  }

  public function firstActive(Site $site): ?SiteDomain
  {
    return $this->tableExists($site)
      ? $this->read('active', $site, fn () => $site->activeDomains()->first())
      : null;
  }

  private function read(string $group, Site $site, callable $load): ?SiteDomain
  {
    $domain = app(RequestMemo::class)->remember('site.domain-'.$group, [...$this->identity($site), $site->getKey()], $load);

    return $domain === null ? null : clone $domain;
  }

  private function identity(Site $site): array
  {
    $connection = $site->getConnection();

    return [$connection->getName(), $connection->getDatabaseName(), $connection->getTablePrefix(), $site->siteDomains()->getRelated()->getTable()];
  }
}
