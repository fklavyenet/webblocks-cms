<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Tickets;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * A stable, anonymous identifier for this WebBlocks CMS installation.
 *
 * WebBlocks CMS is installed once per site, so local user ids restart at 1 in
 * every install. Reporting a support ticket under a bare user id would put
 * every install's "user 1" in the same bucket on Workbench, and the ticket list
 * is keyed on exactly that. This id is what makes the reporter reference
 * unique across installs, and it is sent alongside as `install_ref` so
 * Workbench can enforce the same boundary server-side.
 *
 * It deliberately does NOT reuse the publisher-client's telemetry install id.
 * That one returns null when update telemetry is switched off, and support
 * identity must not depend on an unrelated toggle — falling back to a bare
 * user id is the collision this class exists to prevent.
 *
 * It is a random UUID rather than a hash of APP_KEY or the site URL: a key
 * rotation or a domain change must not silently re-identify the install and
 * detach every reporter from their own history.
 */
final class InstallId
{
  private const FILE = 'webblocks-cms/support-install-id';

  public function value(): string
  {
    $path = storage_path('app/'.self::FILE);

    if (File::isFile($path)) {
      $existing = trim((string) File::get($path));

      if ($existing !== '') {
        return $existing;
      }
    }

    $id = (string) Str::uuid();

    try {
      File::ensureDirectoryExists(dirname($path));
      File::put($path, $id);
    } catch (Throwable) {
      // An unwritable storage directory must not take the support screens
      // down with it. The id is then per-request, which costs the
      // reporter their ticket list — never another school's.
      return $id;
    }

    return $id;
  }

  /**
   * The reference Workbench groups a reporter's tickets by. Namespaced by
   * install, so user 1 on one site and user 1 on another are two people.
   */
  public function userRef(int|string $userId): string
  {
    return $this->value().':'.$userId;
  }
}
