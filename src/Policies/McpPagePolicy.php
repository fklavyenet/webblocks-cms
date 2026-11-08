<?php

namespace WebBlocks\Cms\Policies;

use App\Models\User;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\PersonalApiTokenPolicy;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;

final class McpPagePolicy
{
  public function __construct(
    private readonly CmsAuthorization $acl,
    private readonly PersonalApiTokenPolicy $personal,
    private readonly PageWorkflowManager $workflow,
  ) {}

  public function allowsScopes(User $user, int $siteId, array $scopes): bool
  {
    if (! $this->read($user, $siteId)) {
      return false;
    }

    foreach ($scopes as $scope) {
      $allowed = match ($scope) {
        McpConfiguration::READ, McpConfiguration::READ_CONTENT, McpConfiguration::READ_STRUCTURE, McpConfiguration::READ_TRANSLATION => true,
        McpConfiguration::WRITE, McpConfiguration::CONTENT, McpConfiguration::STRUCTURE => $this->write($user, $siteId),
        McpConfiguration::TRANSLATION => $this->write($user, $siteId)
          && ($this->editTranslations($user, $siteId) || $this->editSeo($user, $siteId)),
        McpConfiguration::SEO => $this->write($user, $siteId) && $this->editSeo($user, $siteId),
        McpConfiguration::CREATE => $this->createDraft($user, $siteId),
        McpConfiguration::READ_MEDIA => $this->readMedia($user, $siteId),
        McpConfiguration::UPLOAD => $this->uploadMedia($user, $siteId),
        default => false,
      };
      if (! $allowed) {
        return false;
      }
    }

    return true;
  }

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
