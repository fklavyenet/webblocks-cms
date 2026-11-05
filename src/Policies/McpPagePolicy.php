<?php

namespace WebBlocks\Cms\Policies;

use App\Models\User;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\PersonalApiTokenPolicy;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;

final class McpPagePolicy
{
  public function __construct(
    private readonly CmsAuthorization $acl,
    private readonly PersonalApiTokenPolicy $personal,
    private readonly PageWorkflowManager $workflow,
  ) {}

  public function read(User $user, int $siteId): bool
  {
    return $this->acl->allows($user, 'cms.pages.read', $siteId)
      && $this->personal->canUse($user, CmsApiTokenCapabilities::CONTENT_READ);
  }

  public function write(User $user, int $siteId): bool
  {
    return $this->read($user, $siteId)
      && $this->acl->allows($user, 'cms.pages.update-draft', $siteId)
      && $this->personal->canUse($user, CmsApiTokenCapabilities::CONTENT_APPLY);
  }

  public function createDraft(User $user, int $siteId): bool
  {
    return $this->read($user, $siteId)
      && $this->acl->allows($user, 'cms.pages.create', $siteId)
      && $this->personal->canUse($user, CmsApiTokenCapabilities::CONTENT_APPLY);
  }

  public function editTranslations(User $user, int $siteId): bool
  {
    return $this->acl->allows($user, 'cms.translations.update', $siteId);
  }

  public function editSeo(User $user, int $siteId): bool
  {
    return $this->acl->allows($user, 'cms.seo.update', $siteId);
  }

  public function readMedia(User $user, int $siteId): bool
  {
    return $this->acl->allows($user, 'cms.media.read', $siteId)
      && $this->personal->canUse($user, CmsApiTokenCapabilities::MEDIA_READ);
  }

  public function uploadMedia(User $user, int $siteId): bool
  {
    return $this->acl->allows($user, 'cms.media.upload', $siteId)
      && $this->personal->canUse($user, CmsApiTokenCapabilities::MEDIA_UPLOAD);
  }

  public function writeLocale(User $user, int $siteId, bool $translated): bool
  {
    return $this->write($user, $siteId)
      && (! $translated || $this->editTranslations($user, $siteId));
  }

  public function editDraft(User $user, Page $draft, bool $translated): bool
  {
    return $this->writeLocale($user, $draft->site_id, $translated)
      && $this->workflow->canEditContent($user, $draft);
  }
}
